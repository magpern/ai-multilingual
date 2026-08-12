<?php
/**
 * Bounded Operations bulk orchestration (OTL.5).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Operator;

use AIMultilingual\Jobs\BackgroundTranslationJobService;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Plugin;
use AIMultilingual\Surface\SurfaceCapability;
use AIMultilingual\Surface\SurfaceCapabilityNames;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Operator\AllowedActionsResolver;
use AIMultilingual\Workspace\WorkspaceService;
use WP_Error;
use WP_Post;

/**
 * Owns bounded bulk iteration and partial-result aggregation for Operations.
 *
 * Orchestration only — PublicationService / Jobs remain mutation authorities.
 * UI admission is never authoritative.
 */
final class OperationsBulkCoordinator {

	public const BATCH_LIMIT = 50;

	public const ACTION_PUBLISH             = 'publish';
	public const ACTION_UNPUBLISH           = 'unpublish';
	public const ACTION_ENQUEUE_RETRANSLATE = 'enqueue_retranslate';

	public const OUTCOME_PUBLISHED    = 'published';
	public const OUTCOME_UNPUBLISHED  = 'unpublished';
	public const OUTCOME_NOOP         = 'noop';
	public const OUTCOME_SKIPPED      = 'skipped';
	public const OUTCOME_BLOCKED      = 'blocked';
	public const OUTCOME_CONFLICT     = 'conflict';
	public const OUTCOME_UNAUTHORIZED = 'unauthorized';
	public const OUTCOME_FAILED       = 'failed';
	public const OUTCOME_ENQUEUED     = 'enqueued';

	/**
	 * Workspace command facade.
	 *
	 * @var WorkspaceService
	 */
	private WorkspaceService $workspace;

	/**
	 * Translation store.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * TI.7 publication service.
	 *
	 * @var PublicationService|null
	 */
	private ?PublicationService $publication;

	/**
	 * TI.6 Jobs service (optional until Plugin wires it).
	 *
	 * @var BackgroundTranslationJobService|null
	 */
	private ?BackgroundTranslationJobService $jobs;

	/**
	 * Surface registry for source facts and authorization (TSC.0).
	 *
	 * @var SurfaceRegistry|null
	 */
	private ?SurfaceRegistry $surfaces;

	/**
	 * Builds the coordinator.
	 *
	 * @param WorkspaceService                     $workspace   Workspace facade.
	 * @param Store                                $store       Translation store.
	 * @param PublicationService|null              $publication TI.7 service.
	 * @param BackgroundTranslationJobService|null $jobs        TI.6 job service.
	 * @param SurfaceRegistry|null                 $surfaces    Surface registry.
	 */
	public function __construct(
		WorkspaceService $workspace,
		Store $store,
		?PublicationService $publication = null,
		?BackgroundTranslationJobService $jobs = null,
		?SurfaceRegistry $surfaces = null
	) {
		$this->workspace   = $workspace;
		$this->store       = $store;
		$this->publication = $publication;
		$this->jobs        = $jobs;
		$this->surfaces    = $surfaces;
	}

	/**
	 * Injects Jobs after Plugin constructs the Jobs stack.
	 *
	 * @param BackgroundTranslationJobService $jobs Jobs service.
	 */
	public function set_jobs( BackgroundTranslationJobService $jobs ): void {
		$this->jobs = $jobs;
	}

	/**
	 * Legal bulk actions for OTL.5.
	 *
	 * @return list<string>
	 */
	public static function actions(): array {
		return array(
			self::ACTION_PUBLISH,
			self::ACTION_UNPUBLISH,
			self::ACTION_ENQUEUE_RETRANSLATE,
		);
	}

	/**
	 * Runs one bounded bulk action.
	 *
	 * @param string                           $action  One of self::actions().
	 * @param array<int, array<string, mixed>> $items   Per-item payloads keyed by translation_id.
	 * @param int                              $user_id Acting user.
	 * @return array<string, mixed>|WP_Error
	 */
	public function run( string $action, array $items, int $user_id ) {
		if ( ! in_array( $action, self::actions(), true ) ) {
			return new WP_Error(
				'aiml_invalid_bulk_action',
				__( 'Unknown operations bulk action.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		if ( count( $items ) > self::BATCH_LIMIT ) {
			return new WP_Error(
				'aiml_batch_too_large',
				sprintf(
					/* translators: %d: maximum batch size */
					__( 'Batch size exceeds the limit of %d translations.', 'ai-multilingual' ),
					self::BATCH_LIMIT
				),
				array( 'status' => 422 )
			);
		}

		if ( array() === $items ) {
			return new WP_Error(
				'aiml_bulk_empty',
				__( 'No translations were selected.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		return match ( $action ) {
			self::ACTION_PUBLISH             => $this->run_publish( $items, $user_id ),
			self::ACTION_UNPUBLISH           => $this->run_unpublish( $items, $user_id ),
			self::ACTION_ENQUEUE_RETRANSLATE => $this->run_enqueue_retranslate( $items, $user_id ),
			default                          => new WP_Error(
				'aiml_invalid_bulk_action',
				__( 'Unknown operations bulk action.', 'ai-multilingual' ),
				array( 'status' => 422 )
			),
		};
	}

	/**
	 * Bounded bulk publish via PublicationService.
	 *
	 * @param array<int, array<string, mixed>> $items   Items.
	 * @param int                              $user_id User id.
	 * @return array<string, mixed>
	 */
	private function run_publish( array $items, int $user_id ): array {
		$results = array();

		foreach ( $items as $item ) {
			$results[] = $this->publish_one( is_array( $item ) ? $item : array(), $user_id );
		}

		return $this->aggregate( $results, false );
	}

	/**
	 * Bounded bulk unpublish via PublicationService.
	 *
	 * @param array<int, array<string, mixed>> $items   Items.
	 * @param int                              $user_id User id.
	 * @return array<string, mixed>
	 */
	private function run_unpublish( array $items, int $user_id ): array {
		$results = array();

		foreach ( $items as $item ) {
			$results[] = $this->unpublish_one( is_array( $item ) ? $item : array(), $user_id );
		}

		return $this->aggregate( $results, false );
	}

	/**
	 * Bounded Jobs enqueue for selected translations only.
	 *
	 * @param array<int, array<string, mixed>> $items   Items.
	 * @param int                              $user_id User id.
	 * @return array<string, mixed>
	 */
	private function run_enqueue_retranslate( array $items, int $user_id ): array {
		if ( null === $this->jobs ) {
			$failed = array();
			foreach ( $items as $item ) {
				$tid      = (int) ( is_array( $item ) ? ( $item['translation_id'] ?? 0 ) : 0 );
				$failed[] = $this->item_result(
					$tid,
					self::OUTCOME_FAILED,
					'aiml_jobs_unavailable',
					__( 'Jobs service is not available.', 'ai-multilingual' )
				);
			}

			return $this->aggregate( $failed, true );
		}

		$resolved   = array();
		$item_map   = array();
		$operations = array();

		foreach ( $items as $raw ) {
			$item = is_array( $raw ) ? $raw : array();
			$tid  = (int) ( $item['translation_id'] ?? 0 );
			$row  = $this->resolve_row( $tid );
			if ( $row instanceof WP_Error ) {
				$item_map[ $tid ] = $this->from_resolve_error( $tid, $row );
				continue;
			}

			$access = $this->assert_source_access( $row );
			if ( $access instanceof WP_Error ) {
				$item_map[ $tid ] = $this->from_access_error( $tid, $access );
				continue;
			}

			if ( ! $this->source_supports_jobs( $row ) ) {
				$item_map[ $tid ] = $this->item_result(
					$tid,
					self::OUTCOME_BLOCKED,
					'aiml_mutation_unsupported_type',
					__( 'This translation source cannot be retranslated in the background.', 'ai-multilingual' )
				);
				continue;
			}

			if ( empty( $row->is_stale ) ) {
				$item_map[ $tid ] = $this->item_result(
					$tid,
					self::OUTCOME_BLOCKED,
					'aiml_not_stale',
					__( 'Translation is not stale.', 'ai-multilingual' )
				);
				continue;
			}

			$group_key = (string) ( $row->source_type ?? '' ) . ':' . (int) $row->source_id . ':' . (int) $row->language_id;
			if ( ! isset( $resolved[ $group_key ] ) ) {
				$resolved[ $group_key ] = array(
					'source_type' => (string) $row->source_type,
					'source_id'   => (int) $row->source_id,
					'language_id' => (int) $row->language_id,
					'keys'        => array(),
					'snapshots'   => array(),
					'tids'        => array(),
				);
			}

			$segment_key                                    = (string) $row->segment_key;
			$resolved[ $group_key ]['keys'][]               = $segment_key;
			$resolved[ $group_key ]['tids'][ $segment_key ] = $tid;
			$resolved[ $group_key ]['snapshots'][ $segment_key ] = array(
				'source_hash_captured'      => (string) ( $item['source_hash'] ?? $row->source_hash ?? '' ),
				'translation_hash_captured' => (string) ( $item['translation_hash'] ?? Store::translation_hash( (string) ( $row->translated_text ?? '' ) ) ),
			);
		}

		foreach ( $resolved as $group_key => $group ) {
			$keys = array_values( array_unique( $group['keys'] ) );
			$args = array(
				'job_type'          => JobTypes::TRANSLATE_SELECTED,
				'source_type'       => $group['source_type'],
				'source_id'         => $group['source_id'],
				'language_id'       => $group['language_id'],
				'segment_keys'      => $keys,
				'segment_snapshots' => $group['snapshots'],
				'created_by'        => $user_id,
			);

			$created = $this->jobs->create_job( $args );
			if ( $created instanceof WP_Error ) {
				$operations[] = array(
					'operation_key'  => $group_key,
					'action'         => self::ACTION_ENQUEUE_RETRANSLATE,
					'job_id'         => null,
					'outcome'        => self::OUTCOME_FAILED,
					'code'           => $created->get_error_code(),
					'affected_items' => array_values( $group['tids'] ),
					'message'        => $created->get_error_message(),
				);
				foreach ( $group['tids'] as $tid ) {
					$item_map[ $tid ] = $this->item_result(
						(int) $tid,
						self::OUTCOME_FAILED,
						$created->get_error_code(),
						$created->get_error_message()
					);
				}
				continue;
			}

			$job_id       = (int) ( $created->job_id ?? 0 );
			$operations[] = array(
				'operation_key'  => $group_key,
				'action'         => self::ACTION_ENQUEUE_RETRANSLATE,
				'job_id'         => $job_id,
				'outcome'        => 'created',
				'code'           => null,
				'affected_items' => array_values( $group['tids'] ),
				'message'        => null,
			);

			foreach ( $group['tids'] as $tid ) {
				$item_map[ $tid ] = $this->item_result(
					(int) $tid,
					self::OUTCOME_ENQUEUED,
					null,
					__( 'Accepted for asynchronous Jobs processing.', 'ai-multilingual' ),
					$job_id
				);
			}
		}

		// Preserve request order for items[].
		$ordered = array();
		foreach ( $items as $raw ) {
			$tid = (int) ( is_array( $raw ) ? ( $raw['translation_id'] ?? 0 ) : 0 );
			if ( isset( $item_map[ $tid ] ) ) {
				$ordered[] = $item_map[ $tid ];
			}
		}

		return $this->aggregate( $ordered, true, $operations );
	}

	/**
	 * Publishes one resolved translation.
	 *
	 * @param array<string, mixed> $item    Item payload.
	 * @param int                  $user_id User id.
	 * @return array<string, mixed>
	 */
	private function publish_one( array $item, int $user_id ): array {
		$tid = (int) ( $item['translation_id'] ?? 0 );
		$row = $this->resolve_row( $tid );
		if ( $row instanceof WP_Error ) {
			return $this->from_resolve_error( $tid, $row );
		}

		$access = $this->assert_source_access( $row );
		if ( $access instanceof WP_Error ) {
			return $this->from_access_error( $tid, $access );
		}

		if ( null === $this->publication ) {
			return $this->item_result(
				$tid,
				self::OUTCOME_FAILED,
				'aiml_publication_unavailable',
				__( 'Publication service is not available.', 'ai-multilingual' )
			);
		}

		$expected = isset( $item['expected_publish_status'] ) && '' !== (string) $item['expected_publish_status']
			? (string) $item['expected_publish_status']
			: null;

		$result = $this->publication->publish(
			(string) $row->source_type,
			(int) $row->source_id,
			(int) $row->language_id,
			(string) $row->segment_key,
			false,
			$user_id,
			'operations_bulk',
			$expected
		);

		return $this->map_publication_result( $tid, $result, self::OUTCOME_PUBLISHED );
	}

	/**
	 * Unpublishes one resolved translation.
	 *
	 * @param array<string, mixed> $item    Item payload.
	 * @param int                  $user_id User id.
	 * @return array<string, mixed>
	 */
	private function unpublish_one( array $item, int $user_id ): array {
		$tid = (int) ( $item['translation_id'] ?? 0 );
		$row = $this->resolve_row( $tid );
		if ( $row instanceof WP_Error ) {
			return $this->from_resolve_error( $tid, $row );
		}

		$access = $this->assert_source_access( $row );
		if ( $access instanceof WP_Error ) {
			return $this->from_access_error( $tid, $access );
		}

		if ( null === $this->publication ) {
			return $this->item_result(
				$tid,
				self::OUTCOME_FAILED,
				'aiml_publication_unavailable',
				__( 'Publication service is not available.', 'ai-multilingual' )
			);
		}

		$result = $this->publication->unpublish(
			(string) $row->source_type,
			(int) $row->source_id,
			(int) $row->language_id,
			(string) $row->segment_key,
			$user_id
		);

		return $this->map_publication_result( $tid, $result, self::OUTCOME_UNPUBLISHED );
	}

	/**
	 * Maps PublicationService array|WP_Error to an item result.
	 *
	 * @param int                           $tid             Translation id.
	 * @param array<string, mixed>|WP_Error $result     Service result.
	 * @param string                        $success_outcome Published/unpublished label.
	 * @return array<string, mixed>
	 */
	private function map_publication_result( int $tid, $result, string $success_outcome ): array {
		if ( $result instanceof WP_Error ) {
			$code = $result->get_error_code();
			$data = $result->get_error_data();
			$http = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;

			$outcome = self::OUTCOME_FAILED;
			if ( in_array( $code, array( 'aiml_publish_conflict', 'expected_status_mismatch' ), true ) || 409 === $http ) {
				$outcome = self::OUTCOME_CONFLICT;
			} elseif ( 403 === $http ) {
				$outcome = self::OUTCOME_UNAUTHORIZED;
			}

			return $this->item_result( $tid, $outcome, $code, $result->get_error_message() );
		}

		$status = (string) ( $result['status'] ?? '' );
		$codes  = isset( $result['reason_codes'] ) && is_array( $result['reason_codes'] )
			? array_values( array_map( 'strval', $result['reason_codes'] ) )
			: array();

		if ( 'noop' === $status ) {
			return $this->item_result( $tid, self::OUTCOME_NOOP, $codes[0] ?? 'noop', null, null, $codes );
		}

		if ( 'skipped' === $status ) {
			return $this->item_result(
				$tid,
				self::OUTCOME_SKIPPED,
				$codes[0] ?? 'skipped',
				null,
				null,
				$codes
			);
		}

		if ( in_array( $status, array( 'published', 'unpublished' ), true ) ) {
			return $this->item_result( $tid, $success_outcome, null, null, null, $codes );
		}

		return $this->item_result(
			$tid,
			self::OUTCOME_FAILED,
			'aiml_bulk_unexpected_result',
			__( 'Unexpected publication result.', 'ai-multilingual' ),
			null,
			$codes
		);
	}

	/**
	 * Loads a Store row by translation_id.
	 *
	 * @param int $translation_id Translation PK.
	 * @return object|WP_Error
	 */
	private function resolve_row( int $translation_id ) {
		if ( $translation_id <= 0 ) {
			return new WP_Error(
				'aiml_invalid_translation_id',
				__( 'translation_id is required.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		$row = $this->store->get_by_translation_id( $translation_id );
		if ( null === $row ) {
			return new WP_Error(
				'aiml_translation_missing',
				__( 'Translation not found.', 'ai-multilingual' ),
				array( 'status' => 404 )
			);
		}

		return $row;
	}

	/**
	 * Per-item source existence + edit capability check.
	 *
	 * Any registered surface that declares MUTATE may be bulk-mutated; the
	 * surface answers existence and authorization, so a term is checked with
	 * `edit_term` rather than being refused for not being a post.
	 *
	 * @param object $row Store row.
	 * @return true|WP_Error
	 */
	private function assert_source_access( object $row ) {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			return new WP_Error(
				'aiml_capability_denied',
				__( 'You do not have permission to mutate translations.', 'ai-multilingual' ),
				array( 'status' => 403 )
			);
		}

		if ( AllowedActionsResolver::denies_row_write( $row ) ) {
			return new WP_Error(
				'aiml_segment_write_denied',
				__( 'This translation segment is not independently writable.', 'ai-multilingual' ),
				array( 'status' => 403 )
			);
		}

		$source_type = (string) ( $row->source_type ?? '' );
		$source_id   = (int) ( $row->source_id ?? 0 );
		$surface     = $this->surface_for( $source_type, SurfaceCapabilityNames::MUTATE );

		if ( null === $surface ) {
			return $this->assert_legacy_post_access( $source_type, $source_id );
		}

		if ( ! $surface->exists( $source_id ) ) {
			return new WP_Error(
				'aiml_source_missing',
				__( 'Source object not found.', 'ai-multilingual' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $surface->user_can_edit_source( 0, $source_id ) ) {
			return new WP_Error(
				'aiml_source_access_denied',
				__( 'You cannot edit this source object.', 'ai-multilingual' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Whether the row's source can carry background translation work.
	 *
	 * @param object $row Store row.
	 */
	private function source_supports_jobs( object $row ): bool {
		$source_type = (string) ( $row->source_type ?? '' );

		if ( null === $this->surfaces ) {
			return Store::SOURCE_POST === $source_type;
		}

		return null !== $this->surface_for( $source_type, SurfaceCapabilityNames::JOBS );
	}

	/**
	 * Registered surface declaring a capability, or null.
	 *
	 * @param string $source_type Source type.
	 * @param string $capability  Capability name.
	 */
	private function surface_for( string $source_type, string $capability ): ?SurfaceCapability {
		if ( null === $this->surfaces ) {
			return null;
		}

		$surface = $this->surfaces->for( $source_type );

		return null !== $surface && $surface->supports( $capability ) ? $surface : null;
	}

	/**
	 * Post-only access path for callers that run without a wired registry.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 * @return true|WP_Error
	 */
	private function assert_legacy_post_access( string $source_type, int $source_id ) {
		if ( Store::SOURCE_POST !== $source_type ) {
			return new WP_Error(
				'aiml_mutation_unsupported_type',
				__( 'This translation source does not support this mutation.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		if ( ! get_post( $source_id ) instanceof WP_Post ) {
			return new WP_Error(
				'aiml_source_missing',
				__( 'Source post not found.', 'ai-multilingual' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $source_id ) ) {
			return new WP_Error(
				'aiml_source_access_denied',
				__( 'You cannot edit this source post.', 'ai-multilingual' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Maps resolve errors.
	 *
	 * @param int      $tid   Translation id.
	 * @param WP_Error $error Error.
	 * @return array<string, mixed>
	 */
	private function from_resolve_error( int $tid, WP_Error $error ): array {
		$data = $error->get_error_data();
		$http = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
		$out  = 404 === $http ? self::OUTCOME_BLOCKED : self::OUTCOME_FAILED;

		return $this->item_result( $tid, $out, $error->get_error_code(), $error->get_error_message() );
	}

	/**
	 * Maps access errors.
	 *
	 * @param int      $tid   Translation id.
	 * @param WP_Error $error Error.
	 * @return array<string, mixed>
	 */
	private function from_access_error( int $tid, WP_Error $error ): array {
		$data    = $error->get_error_data();
		$http    = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
		$outcome = 403 === $http ? self::OUTCOME_UNAUTHORIZED : self::OUTCOME_BLOCKED;

		return $this->item_result( $tid, $outcome, $error->get_error_code(), $error->get_error_message() );
	}

	/**
	 * Builds one item result row.
	 *
	 * @param int               $translation_id Translation id.
	 * @param string            $outcome        Outcome code.
	 * @param string|null       $code           Machine code.
	 * @param string|null       $message        Message.
	 * @param int|null          $job_id         Optional job id.
	 * @param list<string>|null $reason_codes   Optional reason codes.
	 * @return array<string, mixed>
	 */
	private function item_result(
		int $translation_id,
		string $outcome,
		?string $code,
		?string $message,
		?int $job_id = null,
		?array $reason_codes = null
	): array {
		$row = array(
			'translation_id' => $translation_id,
			'outcome'        => $outcome,
			'code'           => $code,
			'message'        => $message,
		);

		if ( null !== $job_id && $job_id > 0 ) {
			$row['job_id'] = $job_id;
		}

		if ( null !== $reason_codes && array() !== $reason_codes ) {
			$row['reason_codes'] = $reason_codes;
		}

		return $row;
	}

	/**
	 * Aggregates item results into a bulk response.
	 *
	 * @param list<array<string, mixed>>      $items      Item results.
	 * @param bool                            $with_ops   Whether to include operations.
	 * @param list<array<string, mixed>>|null $operations Optional operations.
	 * @return array<string, mixed>
	 */
	private function aggregate( array $items, bool $with_ops, ?array $operations = null ): array {
		$terminal_ok = array(
			self::OUTCOME_PUBLISHED,
			self::OUTCOME_UNPUBLISHED,
			self::OUTCOME_NOOP,
			self::OUTCOME_ENQUEUED,
		);

		$ok     = 0;
		$failed = 0;
		foreach ( $items as $item ) {
			$outcome = (string) ( $item['outcome'] ?? '' );
			if ( in_array( $outcome, $terminal_ok, true ) ) {
				++$ok;
			} else {
				++$failed;
			}
		}

		if ( 0 === $failed ) {
			$status = 'completed';
		} elseif ( 0 === $ok ) {
			$status = 'failed';
		} else {
			$status = 'partial';
		}

		$response = array(
			'status'  => $status,
			'items'   => $items,
			'summary' => array(
				'total'  => count( $items ),
				'ok'     => $ok,
				'failed' => $failed,
			),
		);

		if ( $with_ops ) {
			$response['operations'] = $operations ?? array();
		}

		return $response;
	}
}
