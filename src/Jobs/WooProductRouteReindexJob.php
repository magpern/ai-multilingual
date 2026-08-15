<?php
/**
 * Bounded product_dep / woo_product_config rematerialization (MSEO.4).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Jobs;

use AIMultilingual\Routing\FrontierRecord;
use AIMultilingual\Routing\ReindexFrontierRepository;
use AIMultilingual\Routing\RoutePublicationService;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Routing\WooProductPermalinkFingerprint;
use AIMultilingual\Translation\Store;
use WP_Error;

/**
 * Rematerializes product routes after category/config changes.
 */
final class WooProductRouteReindexJob {

	public const HOOK_TICK = 'aiml_woo_product_reindex_tick';

	public const GROUP = 'aiml-localized-urls';

	public const MAX_PER_TICK = 100;

	public const MAX_CONFLICT_IDS = 32;

	public const TYPE_PRODUCT_DEP = 'product_dep';

	public const TYPE_WOO_CONFIG = 'woo_product_config';

	public const CONFIG_ROOT_ID = 1;

	public const STATUS_PENDING   = 'pending';
	public const STATUS_RUNNING   = 'running';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_DEGRADED  = 'degraded';
	public const STATUS_FAILED    = 'failed';

	/**
	 * Constructs the Woo product rematerialization job.
	 *
	 * @param ReindexFrontierRepository $frontiers   Frontier repository.
	 * @param RoutePublicationService   $publication Rematerialization.
	 * @param SlugRouteRepository       $routes      Routes.
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
	 * Enqueues category→product dependency frontier.
	 *
	 * @param int $term_id product_cat term id.
	 * @return object|WP_Error
	 */
	public function enqueue_product_dep( int $term_id ) {
		$checkpoint = array(
			'generation'        => 0,
			'mode'              => 'dependent_products',
			'last_product_id'   => 0,
			'processed_count'   => 0,
			'conflict_ids'      => array(),
			'conflict_overflow' => false,
		);

		$row = $this->frontiers->upsert_checkpoint(
			new FrontierRecord(
				self::TYPE_PRODUCT_DEP,
				$term_id,
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
	 * Enqueues global Woo product config rematerialization.
	 *
	 * @return object|WP_Error
	 */
	public function enqueue_woo_config() {
		$fingerprint = ( new WooProductPermalinkFingerprint() )->hash();
		$checkpoint  = array(
			'generation'        => 0,
			'mode'              => 'all_products',
			'last_product_id'   => 0,
			'processed_count'   => 0,
			'conflict_ids'      => array(),
			'conflict_overflow' => false,
			'fingerprint'       => $fingerprint,
		);

		$row = $this->frontiers->upsert_checkpoint(
			new FrontierRecord(
				self::TYPE_WOO_CONFIG,
				self::CONFIG_ROOT_ID,
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
		if ( function_exists( 'as_enqueue_async_action' ) || function_exists( 'as_schedule_single_action' ) ) {
			as_enqueue_async_action( self::HOOK_TICK, array(), self::GROUP );

			return true;
		}
		$this->tick();

		return true;
	}

	/**
	 * Processes one workable Woo frontier.
	 */
	public function tick(): void {
		$this->process_batch();
	}

	/**
	 * Runs one bounded tick; exposed for tests.
	 *
	 * @return array<string, mixed>
	 */
	public function process_batch(): array {
		$row = $this->frontiers->find_workable( array( self::TYPE_PRODUCT_DEP, self::TYPE_WOO_CONFIG ) );
		if ( null === $row ) {
			return array( 'status' => 'idle' );
		}

		$parent_type = (string) ( $row->parent_source_type ?? '' );
		$parent_id   = (int) ( $row->parent_source_id ?? 0 );
		$generation  = (int) ( $row->generation ?? 1 );
		$checkpoint  = $this->decode_checkpoint( (string) ( $row->checkpoint_json ?? '' ), $parent_type );

		if ( ! in_array( $parent_type, array( self::TYPE_PRODUCT_DEP, self::TYPE_WOO_CONFIG ), true ) ) {
			return array(
				'status'  => 'rejected',
				'message' => 'Wrong frontier type',
			);
		}

		$mode = (string) ( $checkpoint['mode'] ?? '' );
		if ( self::TYPE_PRODUCT_DEP === $parent_type && 'dependent_products' !== $mode ) {
			return array(
				'status'  => 'rejected',
				'message' => 'Mode mismatch',
			);
		}
		if ( self::TYPE_WOO_CONFIG === $parent_type && 'all_products' !== $mode ) {
			return array(
				'status'  => 'rejected',
				'message' => 'Mode mismatch',
			);
		}

		$checkpoint['generation'] = $generation;
		$this->persist( $parent_type, $parent_id, $checkpoint, self::STATUS_RUNNING, $generation );

		$processed = 0;
		$last_id   = (int) ( $checkpoint['last_product_id'] ?? 0 );

		while ( $processed < self::MAX_PER_TICK ) {
			$ids = self::TYPE_PRODUCT_DEP === $parent_type
				? $this->list_products_for_category_subtree( $parent_id, $last_id, 1 )
				: $this->list_all_products_after( $last_id, 1 );

			if ( array() === $ids ) {
				$status = ( array() !== (array) ( $checkpoint['conflict_ids'] ?? array() ) || ! empty( $checkpoint['conflict_overflow'] ) )
					? self::STATUS_DEGRADED
					: self::STATUS_COMPLETED;
				$this->persist( $parent_type, $parent_id, $checkpoint, $status, $generation );

				return array(
					'status'    => $status,
					'processed' => $processed,
				);
			}

			$product_id                    = (int) $ids[0];
			$last_id                       = $product_id;
			$checkpoint['last_product_id'] = $last_id;
			++$processed;
			++$checkpoint['processed_count'];

			$result = $this->rematerialize_product( $product_id );
			if ( $result instanceof WP_Error ) {
				$this->record_conflict( $checkpoint, $product_id );
			}
		}

		$this->persist( $parent_type, $parent_id, $checkpoint, self::STATUS_RUNNING, $generation );
		$this->enqueue_tick();

		return array(
			'status'    => self::STATUS_RUNNING,
			'processed' => $processed,
		);
	}

	/**
	 * Rematerializes all language routes for a product.
	 *
	 * @param int $product_id Product id.
	 * @return true|WP_Error
	 */
	private function rematerialize_product( int $product_id ) {
		$language_ids = $this->routes->list_language_ids_for_source( Store::SOURCE_POST, $product_id );
		if ( array() === $language_ids ) {
			return true;
		}
		foreach ( $language_ids as $language_id ) {
			$result = $this->publication->rematerialize_route( Store::SOURCE_POST, $product_id, (int) $language_id );
			if ( $result instanceof WP_Error ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Products assigned to term or its descendants, after cursor.
	 *
	 * @param int $term_id  Root category.
	 * @param int $after_id Last product id.
	 * @param int $limit    Bound.
	 * @return list<int>
	 */
	private function list_products_for_category_subtree( int $term_id, int $after_id, int $limit ): array {
		global $wpdb;

		$term_ids = array( $term_id );
		$children = get_term_children( $term_id, 'product_cat' );
		if ( is_array( $children ) ) {
			foreach ( $children as $child ) {
				$term_ids[] = (int) $child;
			}
		}
		$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
		if ( array() === $term_ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
		$params       = array_merge( $term_ids, array( $after_id, $limit ) );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $wpdb->prepare(
			"SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} AS tr
			INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->posts} AS p ON p.ID = tr.object_id
			WHERE tt.taxonomy = 'product_cat'
			  AND tt.term_id IN ($placeholders)
			  AND p.post_type = 'product'
			  AND p.ID > %d
			ORDER BY p.ID ASC
			LIMIT %d",
			...$params
		);
		$ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// phpcs:enable

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Lists product IDs after a cursor for global config rematerialization.
	 *
	 * @param int $after_id After product id.
	 * @param int $limit    Bound.
	 * @return list<int>
	 */
	private function list_all_products_after( int $after_id, int $limit ): array {
		global $wpdb;

		$limit = max( 1, min( 50, $limit ) );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'product'
				  AND post_status IN ('publish','private','draft')
				  AND ID > %d
				ORDER BY ID ASC
				LIMIT %d",
				$after_id,
				$limit
			)
		);
		// phpcs:enable

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Records a bounded conflict product id on the checkpoint.
	 *
	 * @param array<string, mixed> $checkpoint Checkpoint.
	 * @param int                  $product_id Conflict id.
	 */
	private function record_conflict( array &$checkpoint, int $product_id ): void {
		$ids = isset( $checkpoint['conflict_ids'] ) && is_array( $checkpoint['conflict_ids'] )
			? $checkpoint['conflict_ids']
			: array();
		if ( count( $ids ) >= self::MAX_CONFLICT_IDS ) {
			$checkpoint['conflict_overflow'] = true;

			return;
		}
		if ( ! in_array( $product_id, $ids, true ) ) {
			$ids[] = $product_id;
		}
		$checkpoint['conflict_ids'] = $ids;
	}

	/**
	 * Persists frontier checkpoint and status for the current generation.
	 *
	 * @param string               $parent_type Parent type.
	 * @param int                  $parent_id   Parent id.
	 * @param array<string, mixed> $checkpoint  Checkpoint.
	 * @param string               $status      Status.
	 * @param int                  $generation  Generation.
	 */
	private function persist( string $parent_type, int $parent_id, array $checkpoint, string $status, int $generation ): void {
		$this->frontiers->update_checkpoint(
			$parent_type,
			$parent_id,
			$generation,
			wp_json_encode( $checkpoint ),
			$status
		);
	}

	/**
	 * Decodes a Woo product rematerialization checkpoint JSON blob.
	 *
	 * @param string $json        JSON.
	 * @param string $parent_type Type.
	 * @return array<string, mixed>
	 */
	private function decode_checkpoint( string $json, string $parent_type ): array {
		$decoded = '' !== $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}
		$mode = self::TYPE_WOO_CONFIG === $parent_type ? 'all_products' : 'dependent_products';

		return array(
			'generation'        => (int) ( $decoded['generation'] ?? 0 ),
			'mode'              => (string) ( $decoded['mode'] ?? $mode ),
			'last_product_id'   => (int) ( $decoded['last_product_id'] ?? 0 ),
			'processed_count'   => (int) ( $decoded['processed_count'] ?? 0 ),
			'conflict_ids'      => isset( $decoded['conflict_ids'] ) && is_array( $decoded['conflict_ids'] )
				? array_values( array_map( 'intval', $decoded['conflict_ids'] ) )
				: array(),
			'conflict_overflow' => ! empty( $decoded['conflict_overflow'] ),
			'fingerprint'       => (string) ( $decoded['fingerprint'] ?? '' ),
		);
	}
}
