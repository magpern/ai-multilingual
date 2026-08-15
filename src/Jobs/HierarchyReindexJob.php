<?php
/**
 * Bounded O(depth) DFS hierarchy rematerialization worker (MSEO.3 A3/A4/A5).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use AIMultilingual\Routing\FrontierRecord;
use AIMultilingual\Routing\ReindexFrontierRepository;
use AIMultilingual\Routing\RoutePublicationService;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;

/**
 * Rematerializes descendant prepared routes after ancestor path changes.
 *
 * Checkpoint size is O(depth). Same-root generation supersedes prior work.
 * Collisions mark the frontier degraded without mutating slug candidates.
 */
final class HierarchyReindexJob {

	public const HOOK_TICK = 'aiml_hierarchy_reindex_tick';

	public const GROUP = 'aiml-localized-urls';

	public const MAX_PER_TICK = 100;

	public const MAX_STACK_DEPTH = 64;

	public const MAX_DIRECT_CHILDREN = 50;

	public const MAX_CONFLICT_IDS = 32;

	public const STATUS_PENDING   = 'pending';
	public const STATUS_RUNNING   = 'running';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_DEGRADED  = 'degraded';
	public const STATUS_FAILED    = 'failed';

	/**
	 * Constructs the job.
	 *
	 * @param ReindexFrontierRepository $frontiers   Frontier repository.
	 * @param RoutePublicationService   $publication Route rematerialization.
	 * @param SlugRouteRepository       $routes      Route repository.
	 */
	public function __construct(
		private ReindexFrontierRepository $frontiers,
		private RoutePublicationService $publication,
		private SlugRouteRepository $routes
	) {
	}

	/**
	 * Registers Action Scheduler callback.
	 */
	public function register_hooks(): void {
		add_action( self::HOOK_TICK, array( $this, 'tick' ), 10, 0 );
	}

	/**
	 * Whether Action Scheduler is available.
	 */
	public function is_scheduler_available(): bool {
		return function_exists( 'as_enqueue_async_action' ) || function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Enqueues a frontier for a parent root (same-root generation supersede).
	 *
	 * @param string $parent_source_type Parent source type.
	 * @param int    $parent_source_id   Parent source id.
	 * @return object|WP_Error Frontier row.
	 */
	public function enqueue_root( string $parent_source_type, int $parent_source_id ) {
		$checkpoint = array(
			'generation'       => 0,
			'stack'            => array(
				array(
					'source_type'   => $parent_source_type,
					'source_id'     => $parent_source_id,
					'last_child_id' => 0,
				),
			),
			'processed_count'  => 0,
			'conflict_ids'     => array(),
			'conflict_overflow'=> false,
		);

		$row = $this->frontiers->upsert_checkpoint(
			new FrontierRecord(
				$parent_source_type,
				$parent_source_id,
				wp_json_encode( $checkpoint ),
				1,
				self::STATUS_PENDING
			)
		);

		if ( $row instanceof WP_Error ) {
			return $row;
		}

		$this->enqueue_tick();

		return $row;
	}

	/**
	 * Enqueues one tick.
	 *
	 * @return true|WP_Error
	 */
	public function enqueue_tick() {
		if ( $this->is_scheduler_available() ) {
			as_enqueue_async_action( self::HOOK_TICK, array(), self::GROUP );

			return true;
		}

		$this->tick();

		return true;
	}

	/**
	 * Processes pending/running frontiers (one root per tick for predictability).
	 */
	public function tick(): void {
		$this->process_batch();
	}

	/**
	 * Runs one bounded tick; exposed for tests.
	 *
	 * @param string|null $parent_source_type Optional specific root type.
	 * @param int|null    $parent_source_id   Optional specific root id.
	 * @return array<string, mixed>
	 */
	public function process_batch( ?string $parent_source_type = null, ?int $parent_source_id = null ): array {
		$row = null;
		if ( null !== $parent_source_type && null !== $parent_source_id ) {
			$row = $this->frontiers->find_by_parent( $parent_source_type, $parent_source_id );
		} else {
			$row = $this->find_workable_frontier();
		}

		if ( null === $row ) {
			return array( 'status' => 'idle' );
		}

		$parent_type = (string) ( $row->parent_source_type ?? '' );
		$parent_id   = (int) ( $row->parent_source_id ?? 0 );
		$generation  = (int) ( $row->generation ?? 1 );
		$checkpoint  = $this->decode_checkpoint( (string) ( $row->checkpoint_json ?? '' ), $parent_type, $parent_id, $generation );

		if ( self::STATUS_FAILED === (string) ( $row->status ?? '' ) ) {
			return array( 'status' => 'failed', 'parent_source_id' => $parent_id );
		}

		if ( in_array( (string) ( $row->status ?? '' ), array( self::STATUS_COMPLETED, self::STATUS_DEGRADED ), true )
			&& empty( $checkpoint['stack'] )
		) {
			return array( 'status' => (string) $row->status, 'parent_source_id' => $parent_id );
		}

		$checkpoint['generation'] = $generation;
		$this->persist_frontier( $parent_type, $parent_id, $checkpoint, self::STATUS_RUNNING, $generation );

		$processed = 0;
		while ( $processed < self::MAX_PER_TICK && array() !== $checkpoint['stack'] ) {
			if ( count( $checkpoint['stack'] ) > self::MAX_STACK_DEPTH ) {
				$this->persist_frontier( $parent_type, $parent_id, $checkpoint, self::STATUS_FAILED, $generation );

				return array(
					'status'  => 'failed',
					'message' => 'MAX_STACK_DEPTH exceeded',
				);
			}

			$frame_index = count( $checkpoint['stack'] ) - 1;
			$frame       = $checkpoint['stack'][ $frame_index ];
			$source_type = (string) ( $frame['source_type'] ?? Store::SOURCE_POST );
			$source_id   = (int) ( $frame['source_id'] ?? 0 );
			$last_child  = (int) ( $frame['last_child_id'] ?? 0 );

			$children = $this->direct_children_after( $source_type, $source_id, $last_child, self::MAX_DIRECT_CHILDREN );
			if ( array() === $children ) {
				array_pop( $checkpoint['stack'] );
				continue;
			}

			$child = $children[0];
			$checkpoint['stack'][ $frame_index ]['last_child_id'] = (int) $child['source_id'];

			// Rematerialize this child from current hierarchy + ancestor routes.
			$result = $this->rematerialize_child( $child['source_type'], (int) $child['source_id'] );
			++$processed;
			++$checkpoint['processed_count'];

			if ( $result instanceof WP_Error ) {
				$this->record_conflict( $checkpoint, (int) $child['source_id'] );
				// Do not mutate candidates; retain prior route; continue siblings.
				continue;
			}

			// DFS: push child frame so its descendants are visited next.
			$checkpoint['stack'][] = array(
				'source_type'   => $child['source_type'],
				'source_id'     => (int) $child['source_id'],
				'last_child_id' => 0,
			);
		}

		$status = self::STATUS_RUNNING;
		if ( array() === $checkpoint['stack'] ) {
			$status = array() !== $checkpoint['conflict_ids'] || ! empty( $checkpoint['conflict_overflow'] )
				? self::STATUS_DEGRADED
				: self::STATUS_COMPLETED;
		}

		$this->persist_frontier( $parent_type, $parent_id, $checkpoint, $status, $generation );

		if ( self::STATUS_RUNNING === $status ) {
			$this->enqueue_tick();
		}

		return array(
			'status'          => $status,
			'processed'       => $processed,
			'parent_source_id'=> $parent_id,
			'conflicts'       => $checkpoint['conflict_ids'],
		);
	}

	/**
	 * Rematerializes all language routes for a child object.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 * @return true|WP_Error
	 */
	private function rematerialize_child( string $source_type, int $source_id ) {
		$language_ids = $this->routes->list_language_ids_for_source( $source_type, $source_id );
		if ( array() === $language_ids ) {
			return true;
		}

		foreach ( $language_ids as $language_id ) {
			$result = $this->publication->rematerialize_route( $source_type, $source_id, (int) $language_id );
			if ( $result instanceof WP_Error ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Direct children after last_child_id (pages only for TARGET 8).
	 *
	 * @param string $source_type Parent source type.
	 * @param int    $source_id   Parent source id.
	 * @param int    $after_id    Last child id.
	 * @param int    $limit       Bound.
	 * @return list<array{source_type: string, source_id: int}>
	 */
	private function direct_children_after( string $source_type, int $source_id, int $after_id, int $limit ): array {
		if ( Store::SOURCE_POST !== $source_type ) {
			return array();
		}

		$post = get_post( $source_id );
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
			return array();
		}

		$pages = get_pages(
			array(
				'parent'      => $source_id,
				'sort_column' => 'ID',
				'sort_order'  => 'ASC',
				'post_status' => array( 'publish', 'private', 'draft' ),
				'number'      => 0,
			)
		);
		if ( ! is_array( $pages ) ) {
			return array();
		}

		$out = array();
		foreach ( $pages as $page ) {
			if ( ! $page instanceof WP_Post ) {
				continue;
			}
			if ( (int) $page->ID <= $after_id ) {
				continue;
			}
			$out[] = array(
				'source_type' => Store::SOURCE_POST,
				'source_id'   => (int) $page->ID,
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $checkpoint Checkpoint.
	 * @param int                  $source_id  Conflicting child id.
	 */
	private function record_conflict( array &$checkpoint, int $source_id ): void {
		$ids = isset( $checkpoint['conflict_ids'] ) && is_array( $checkpoint['conflict_ids'] )
			? $checkpoint['conflict_ids']
			: array();
		if ( count( $ids ) >= self::MAX_CONFLICT_IDS ) {
			$checkpoint['conflict_overflow'] = true;

			return;
		}
		if ( ! in_array( $source_id, $ids, true ) ) {
			$ids[] = $source_id;
		}
		$checkpoint['conflict_ids'] = $ids;
	}

	/**
	 * Finds a pending/running frontier row.
	 */
	private function find_workable_frontier(): ?object {
		return $this->frontiers->find_workable();
	}

	/**
	 * @param string               $parent_type Parent type.
	 * @param int                  $parent_id   Parent id.
	 * @param array<string, mixed> $checkpoint  Checkpoint.
	 * @param string               $status      Status.
	 * @param int                  $generation  Generation (preserve; do not bump mid-tick).
	 */
	private function persist_frontier(
		string $parent_type,
		int $parent_id,
		array $checkpoint,
		string $status,
		int $generation
	): void {
		$this->frontiers->update_checkpoint(
			$parent_type,
			$parent_id,
			$generation,
			wp_json_encode( $checkpoint ),
			$status
		);
	}

	/**
	 * @param string $json        Checkpoint JSON.
	 * @param string $parent_type Parent type.
	 * @param int    $parent_id   Parent id.
	 * @param int    $generation  Generation.
	 * @return array<string, mixed>
	 */
	private function decode_checkpoint( string $json, string $parent_type, int $parent_id, int $generation ): array {
		$decoded = '' !== $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		$stack = isset( $decoded['stack'] ) && is_array( $decoded['stack'] ) ? $decoded['stack'] : array();
		if ( array() === $stack ) {
			$stack = array(
				array(
					'source_type'   => $parent_type,
					'source_id'     => $parent_id,
					'last_child_id' => 0,
				),
			);
		}

		return array(
			'generation'        => $generation,
			'stack'             => $stack,
			'processed_count'   => (int) ( $decoded['processed_count'] ?? 0 ),
			'conflict_ids'      => isset( $decoded['conflict_ids'] ) && is_array( $decoded['conflict_ids'] )
				? array_values( array_map( 'intval', $decoded['conflict_ids'] ) )
				: array(),
			'conflict_overflow' => ! empty( $decoded['conflict_overflow'] ),
		);
	}
}
