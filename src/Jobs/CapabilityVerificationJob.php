<?php
/**
 * MSEO.3 capability shape verification + atomic public admission.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use AIMultilingual\Routing\HierarchyPathBuilder;
use AIMultilingual\Routing\RoutingCapabilityAdmission;
use AIMultilingual\Routing\RoutingCapabilityRegistry;
use AIMultilingual\Routing\SlugRouteActivationOutcome;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Settings;
use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;
use WP_Term;

/**
 * Verifies newly implemented shapes; admits only after a full successful pass.
 *
 * Failure must not disable MSEO.2 flat localized URLs (localized_urls_state).
 */
final class CapabilityVerificationJob {

	public const HOOK_TICK = 'aiml_capability_verification_tick';

	public const GROUP = 'aiml-localized-urls';

	public const BATCH_SIZE = 25;

	/**
	 * Constructs the job.
	 *
	 * @param Settings                   $settings     Settings.
	 * @param RoutingCapabilityAdmission $admission    Admission authority.
	 * @param RoutingCapabilityRegistry  $capabilities Capability registry.
	 * @param HierarchyPathBuilder       $hierarchy    Path builder.
	 * @param SlugRouteRepository        $routes       Route repository.
	 */
	public function __construct(
		private Settings $settings,
		private RoutingCapabilityAdmission $admission,
		private RoutingCapabilityRegistry $capabilities,
		private HierarchyPathBuilder $hierarchy,
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
	 * Whether code epoch is ahead of verified epoch.
	 */
	public function needs_verification(): bool {
		return RoutingCapabilityAdmission::CODE_CAPABILITY_EPOCH
			> $this->settings->localized_urls_verified_capability_epoch();
	}

	/**
	 * Enqueues verification when code is ahead of verified epoch.
	 *
	 * @return true|WP_Error
	 */
	public function maybe_enqueue() {
		if ( ! $this->needs_verification() ) {
			return true;
		}

		return $this->enqueue_tick();
	}

	/**
	 * Whether Action Scheduler enqueue APIs are available.
	 */
	public function is_scheduler_available(): bool {
		return function_exists( 'as_enqueue_async_action' ) || function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Enqueues one verification batch.
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
	 * Processes one bounded verification batch.
	 */
	public function tick(): void {
		if ( ! $this->needs_verification() ) {
			return;
		}

		$this->process_batch();
	}

	/**
	 * Runs one batch; exposed for tests.
	 *
	 * @return array{status: string, message?: string}
	 */
	public function process_batch(): array {
		if ( ! $this->needs_verification() ) {
			return array( 'status' => 'noop' );
		}

		$checkpoint = $this->read_checkpoint();
		$shape      = (string) ( $checkpoint['shape'] ?? RoutingCapabilityAdmission::SHAPE_TERM_ARCHIVE );
		$cursor     = (int) ( $checkpoint['cursor'] ?? 0 );
		$passed     = isset( $checkpoint['passed_shapes'] ) && is_array( $checkpoint['passed_shapes'] )
			? array_values( array_map( 'strval', $checkpoint['passed_shapes'] ) )
			: array();

		if ( RoutingCapabilityAdmission::SHAPE_TERM_ARCHIVE === $shape ) {
			$result = $this->verify_term_archive_batch( $cursor );
		} elseif ( RoutingCapabilityAdmission::SHAPE_PAGE_HIERARCHICAL === $shape ) {
			$result = $this->verify_page_hierarchical_batch( $cursor );
		} else {
			$this->fail_pass( 'Unknown capability shape: ' . $shape );

			return array( 'status' => 'failed', 'message' => 'Unknown shape' );
		}

		if ( SlugRouteActivationOutcome::is_blocking( (string) ( $result['outcome'] ?? '' ) ) ) {
			$message = (string) ( $result['message'] ?? 'Capability verification failed.' );
			$this->fail_pass( $message );

			return array( 'status' => 'failed', 'message' => $message );
		}

		$next_cursor = (int) ( $result['next_cursor'] ?? $cursor );
		$shape_done  = ! empty( $result['shape_done'] );

		if ( $shape_done ) {
			if ( ! in_array( $shape, $passed, true ) ) {
				$passed[] = $shape;
			}

			$next_shape = $this->next_shape( $shape );
			if ( null === $next_shape ) {
				// Full pass succeeded — admit atomically. Never touch localized_urls_state.
				$this->admission->commit_admission(
					RoutingCapabilityAdmission::CODE_SHAPES,
					RoutingCapabilityAdmission::CODE_CAPABILITY_EPOCH
				);
				$this->persist_checkpoint( null );

				return array( 'status' => 'admitted' );
			}

			$this->persist_checkpoint(
				array(
					'shape'         => $next_shape,
					'cursor'        => 0,
					'passed_shapes' => $passed,
				)
			);
			$this->enqueue_tick();

			return array( 'status' => 'continue', 'shape' => $next_shape );
		}

		$this->persist_checkpoint(
			array(
				'shape'         => $shape,
				'cursor'        => $next_cursor,
				'passed_shapes' => $passed,
			)
		);
		$this->enqueue_tick();

		return array( 'status' => 'continue', 'shape' => $shape, 'cursor' => $next_cursor );
	}

	/**
	 * Verifies a batch of term archives / path builder invariants.
	 *
	 * @param int $cursor After term_id.
	 * @return array<string, mixed>
	 */
	private function verify_term_archive_batch( int $cursor ): array {
		if ( ! $this->capabilities->supports_term() ) {
			return array(
				'outcome' => SlugRouteActivationOutcome::SYSTEM_ERROR,
				'message' => 'Term archive capability not implemented.',
			);
		}

		$term_ids = $this->list_admitted_term_ids_after( $cursor, self::BATCH_SIZE );
		if ( array() === $term_ids ) {
			if ( 0 === $cursor ) {
				$probe = $this->probe_term_path_builder();
				if ( SlugRouteActivationOutcome::is_blocking( (string) ( $probe['outcome'] ?? '' ) ) ) {
					return $probe;
				}
			}

			return array(
				'outcome'     => SlugRouteActivationOutcome::ADMITTED,
				'shape_done'  => true,
				'next_cursor' => $cursor,
			);
		}

		$last_id = $cursor;
		foreach ( $term_ids as $term_id ) {
			$last_id = $term_id;
			$term    = get_term( $term_id );
			if ( ! $term instanceof WP_Term || is_wp_error( $term ) ) {
				continue;
			}
			$result = $this->classify_term( $term );
			if ( SlugRouteActivationOutcome::is_blocking( (string) ( $result['outcome'] ?? '' ) ) ) {
				$result['next_cursor'] = $last_id;

				return $result;
			}
		}

		$more = array() !== $this->list_admitted_term_ids_after( $last_id, 1 );

		return array(
			'outcome'     => SlugRouteActivationOutcome::ADMITTED,
			'shape_done'  => ! $more,
			'next_cursor' => $last_id,
		);
	}

	/**
	 * Verifies hierarchical pages batch.
	 *
	 * @param int $cursor After post ID.
	 * @return array<string, mixed>
	 */
	private function verify_page_hierarchical_batch( int $cursor ): array {
		$ids = $this->list_hierarchical_page_ids_after( $cursor, self::BATCH_SIZE );

		if ( array() === $ids && 0 === $cursor ) {
			$probe = $this->probe_page_path_builder();
			if ( SlugRouteActivationOutcome::is_blocking( (string) ( $probe['outcome'] ?? '' ) ) ) {
				return $probe;
			}

			return array(
				'outcome'     => SlugRouteActivationOutcome::ADMITTED,
				'shape_done'  => true,
				'next_cursor' => 0,
			);
		}

		if ( array() === $ids ) {
			return array(
				'outcome'     => SlugRouteActivationOutcome::ADMITTED,
				'shape_done'  => true,
				'next_cursor' => $cursor,
			);
		}

		$last_id = $cursor;
		foreach ( $ids as $post_id ) {
			$last_id = $post_id;
			$post    = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$result = $this->classify_hierarchical_page( $post );
			if ( SlugRouteActivationOutcome::is_blocking( (string) ( $result['outcome'] ?? '' ) ) ) {
				$result['next_cursor'] = $last_id;

				return $result;
			}
		}

		$more = array() !== $this->list_hierarchical_page_ids_after( $last_id, 1 );

		return array(
			'outcome'     => SlugRouteActivationOutcome::ADMITTED,
			'shape_done'  => ! $more,
			'next_cursor' => $last_id,
		);
	}

	/**
	 * Classifies one term for capability verification.
	 *
	 * @param WP_Term $term Term.
	 * @return array<string, mixed>
	 */
	private function classify_term( WP_Term $term ): array {
		if ( ! $this->capabilities->supports_term_object( $term ) ) {
			return array( 'outcome' => SlugRouteActivationOutcome::SKIPPED_UNSUPPORTED );
		}

		$source = $this->hierarchy->source_path_for_term( $term );
		if ( $source instanceof WP_Error ) {
			return array(
				'outcome' => SlugRouteActivationOutcome::INVALID_DATA,
				'message' => sprintf( 'Term %d source path: %s', (int) $term->term_id, $source->get_error_message() ),
			);
		}

		$localized = $this->hierarchy->localized_path_for_term( $term, 1, (string) $term->slug );
		if ( $localized instanceof WP_Error ) {
			return array(
				'outcome' => SlugRouteActivationOutcome::INVALID_DATA,
				'message' => sprintf( 'Term %d localized path: %s', (int) $term->term_id, $localized->get_error_message() ),
			);
		}

		return array( 'outcome' => SlugRouteActivationOutcome::ADMITTED );
	}

	/**
	 * Classifies one hierarchical page.
	 *
	 * @param WP_Post $post Page.
	 * @return array<string, mixed>
	 */
	private function classify_hierarchical_page( WP_Post $post ): array {
		if ( ! $this->capabilities->supports_post( $post ) ) {
			return array( 'outcome' => SlugRouteActivationOutcome::SKIPPED_UNSUPPORTED );
		}

		$cap = $this->capabilities->capability_for_post( $post );
		if ( RoutingCapabilityRegistry::PAGE_HIERARCHICAL !== $cap ) {
			return array( 'outcome' => SlugRouteActivationOutcome::SKIPPED_UNSUPPORTED );
		}

		$source = $this->hierarchy->source_path_for_post( $post );
		if ( $source instanceof WP_Error ) {
			return array(
				'outcome' => SlugRouteActivationOutcome::INVALID_DATA,
				'message' => sprintf( 'Page %d source path: %s', (int) $post->ID, $source->get_error_message() ),
			);
		}

		$localized = $this->hierarchy->localized_path_for_post( $post, 1, (string) $post->post_name );
		if ( $localized instanceof WP_Error ) {
			return array(
				'outcome' => SlugRouteActivationOutcome::INVALID_DATA,
				'message' => sprintf( 'Page %d localized path: %s', (int) $post->ID, $localized->get_error_message() ),
			);
		}

		return array( 'outcome' => SlugRouteActivationOutcome::ADMITTED );
	}

	/**
	 * Structural probe when no terms exist yet.
	 *
	 * @return array<string, mixed>
	 */
	private function probe_term_path_builder(): array {
		if ( ! $this->capabilities->supports_term() ) {
			return array(
				'outcome' => SlugRouteActivationOutcome::SYSTEM_ERROR,
				'message' => 'Term archive capability not implemented.',
			);
		}

		return array( 'outcome' => SlugRouteActivationOutcome::ADMITTED );
	}

	/**
	 * Structural probe when no hierarchical pages exist yet.
	 *
	 * @return array<string, mixed>
	 */
	private function probe_page_path_builder(): array {
		return array( 'outcome' => SlugRouteActivationOutcome::ADMITTED );
	}

	/**
	 * Admitted term ids strictly after cursor.
	 *
	 * @param int $after_id After term id.
	 * @param int $limit    Max ids.
	 * @return list<int>
	 */
	private function list_admitted_term_ids_after( int $after_id, int $limit ): array {
		$taxonomies = AdmittedTaxonomies::all();
		if ( array() === $taxonomies ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'number'     => 200,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			if ( (int) $term->term_id <= $after_id ) {
				continue;
			}
			$out[] = (int) $term->term_id;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Hierarchical page ids strictly after cursor.
	 *
	 * @param int $after_id After post id.
	 * @param int $limit    Max ids.
	 * @return list<int>
	 */
	private function list_hierarchical_page_ids_after( int $after_id, int $limit ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'publish', 'private' ),
				'posts_per_page'         => 200,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'post_parent__not_in'    => array( 0 ),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$out = array();
		foreach ( (array) $query->posts as $id ) {
			$id = (int) $id;
			if ( $id <= $after_id ) {
				continue;
			}
			$out[] = $id;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Next shape after the current one, or null when pass complete.
	 *
	 * @param string $current Current shape.
	 */
	private function next_shape( string $current ): ?string {
		$order = RoutingCapabilityAdmission::CODE_SHAPES;
		$idx   = array_search( $current, $order, true );
		if ( false === $idx ) {
			return $order[0] ?? null;
		}
		$next = $idx + 1;

		return $order[ $next ] ?? null;
	}

	/**
	 * Records a failed verification pass without mutating MSEO.2 state.
	 *
	 * @param string $message Diagnostic message.
	 */
	private function fail_pass( string $message ): void {
		$checkpoint = $this->read_checkpoint();
		$this->persist_checkpoint(
			array(
				'shape'   => (string) ( $checkpoint['shape'] ?? RoutingCapabilityAdmission::SHAPE_TERM_ARCHIVE ),
				'cursor'  => (int) ( $checkpoint['cursor'] ?? 0 ),
				'failed'  => true,
				'message' => substr( $message, 0, 500 ),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read_checkpoint(): array {
		$raw = $this->settings->localized_urls_capability_checkpoint();
		if ( null === $raw || '' === $raw ) {
			return array(
				'shape'         => RoutingCapabilityAdmission::SHAPE_TERM_ARCHIVE,
				'cursor'        => 0,
				'passed_shapes' => array(),
			);
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array(
			'shape'         => RoutingCapabilityAdmission::SHAPE_TERM_ARCHIVE,
			'cursor'        => 0,
			'passed_shapes' => array(),
		);
	}

	/**
	 * @param array<string, mixed>|null $checkpoint Checkpoint or null to clear.
	 */
	private function persist_checkpoint( ?array $checkpoint ): void {
		$next = array_merge(
			$this->settings->get(),
			array(
				'localized_urls_capability_checkpoint' => null === $checkpoint
					? null
					: wp_json_encode( $checkpoint ),
			)
		);
		$this->settings->save( Settings::sanitize( $next ) );
		$this->settings->reload();
	}
}
