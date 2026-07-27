<?php
/**
 * Strategy F block identity migration service.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_Error;
use WP_Post;

/**
 * Prepares canonical Gutenberg posts for Strategy F identity and extraction.
 *
 * Uses {@see UuidInjector} directly — never duplicates UUID logic. Persists
 * through {@see wp_update_post()} with {@see SavePipeline} suspended so the
 * save pipeline cannot re-enter or double-process content. Reconciliation uses
 * the existing {@see Store::sync_source()} path after extraction.
 *
 * Migration never runs automatically; callers must invoke it explicitly.
 */
final class BlockIdentityMigration {

	public const MAX_BATCH_SIZE = 100;

	/**
	 * Active migration depth — suppresses SavePipeline and save_post sync glue.
	 *
	 * @var int
	 */
	private static int $active_depth = 0;

	/**
	 * Builds the migration service.
	 *
	 * @param UuidInjector         $injector            UUID persistence pipeline.
	 * @param Extractor            $body_classifier     Body status classifier.
	 * @param Extractor            $migration_extractor Extraction-enabled extractor.
	 * @param Store                $store               Segment store.
	 * @param BlockMigrationLogger $logger      Structured logger.
	 */
	public function __construct(
		private UuidInjector $injector,
		private Extractor $body_classifier,
		private Extractor $migration_extractor,
		private Store $store,
		private BlockMigrationLogger $logger,
	) {
	}

	/**
	 * Whether a migration is currently executing.
	 */
	public static function is_active(): bool {
		return self::$active_depth > 0;
	}

	/**
	 * Resets active state between tests.
	 */
	public static function reset_for_tests(): void {
		self::$active_depth = 0;
		SavePipeline::reset_guard_for_tests();
	}

	/**
	 * Migrates one canonical post.
	 *
	 * @param int                   $post_id Post id.
	 * @param BlockMigrationOptions $options Migration options.
	 */
	public function migrate_post( int $post_id, BlockMigrationOptions $options ): BlockMigrationResult {
		$started = $this->now_ms();

		$this->logger->log(
			BlockMigrationLogger::EVENT_STARTED,
			array(
				'post_id' => $post_id,
				'dry_run' => $options->dry_run,
			)
		);

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $this->skipped(
				$post_id,
				'',
				BlockMigrationEligibility::REASON_MISSING_POST,
				$options,
				$started
			);
		}

		$skip_reason = BlockMigrationEligibility::evaluate( $post, $this->body_classifier );
		if ( null !== $skip_reason ) {
			return $this->skipped( (int) $post->ID, (string) $post->post_type, $skip_reason, $options, $started );
		}

		$original_content = (string) $post->post_content;
		$original_hash    = Store::source_hash( $original_content, Store::FORMAT_HTML );
		$inject           = $this->injector->inject_content( $original_content );

		if ( ! $inject->successful ) {
			return $this->failed(
				$post,
				$options,
				$original_hash,
				$original_hash,
				(string) ( $inject->failure_reason ?? 'inject_failed' ),
				$inject,
				$started
			);
		}

		$content_changed = $inject->changed;
		$migrated_hash   = Store::source_hash(
			$content_changed ? $inject->content : $original_content,
			Store::FORMAT_HTML
		);
		$segments        = $this->extract_segments( $post, $inject->content, $content_changed );
		$segment_count   = count( $segments );
		$stats           = $this->normalized_stats( $inject );

		if ( ! $content_changed && ! $options->refresh_extraction ) {
			return $this->skipped(
				(int) $post->ID,
				(string) $post->post_type,
				BlockMigrationEligibility::REASON_ALREADY_COMPLIANT,
				$options,
				$started,
				$original_hash,
				$migrated_hash,
				$stats,
				$segment_count
			);
		}

		if ( $options->dry_run ) {
			return $this->dry_run(
				$post,
				$options,
				$original_hash,
				$migrated_hash,
				$content_changed,
				$stats,
				$segment_count,
				$started
			);
		}

		if ( $content_changed ) {
			if ( function_exists( 'do_action' ) ) {
				/**
				 * Fires immediately before migration checks post_content for concurrent edits.
				 *
				 * @since 0.1.0
				 *
				 * @param int    $post_id          Post id.
				 * @param string $original_content Content read at migration start.
				 */
				do_action( 'aiml_block_migration_before_persist', (int) $post->ID, $original_content );
			}

			$current = get_post( (int) $post->ID );
			if ( ! $current instanceof WP_Post || (string) $current->post_content !== $original_content ) {
				$this->logger->log(
					BlockMigrationLogger::EVENT_CONCURRENT_MODIFICATION,
					array(
						'post_id'   => (int) $post->ID,
						'post_type' => (string) $post->post_type,
						'dry_run'   => false,
					)
				);

				return $this->failed(
					$post,
					$options,
					$original_hash,
					$migrated_hash,
					'concurrent_modification',
					$inject,
					$started,
					$segment_count
				);
			}

			$source_post = $post;
			$updated     = $this->persist_content( (int) $post->ID, $inject->content );
			if ( $updated instanceof WP_Error ) {
				return $this->failed(
					$source_post,
					$options,
					$original_hash,
					$migrated_hash,
					$updated->get_error_code(),
					$inject,
					$started,
					$segment_count
				);
			}

			$post = get_post( (int) $post->ID );
			if ( ! $post instanceof WP_Post ) {
				return $this->failed(
					$source_post,
					$options,
					$original_hash,
					$migrated_hash,
					'post_missing_after_update',
					$inject,
					$started,
					$segment_count
				);
			}
		}

		$this->reconcile_post( $post );

		$result = new BlockMigrationResult(
			(int) $post->ID,
			(string) $post->post_type,
			BlockMigrationResult::STATUS_COMPLETE,
			false,
			'',
			$content_changed,
			$original_hash,
			$migrated_hash,
			$stats['created_count'],
			$stats['malformed_replaced_count'],
			$stats['duplicate_repaired_count'],
			$segment_count,
			true,
			'',
			$this->elapsed_ms( $started ),
			$this->audit_meta( (int) $post->ID, $original_hash, $migrated_hash, $stats )
		);

		$this->logger->log(
			BlockMigrationLogger::EVENT_POST_COMPLETE,
			$this->log_context( $result )
		);

		return $result;
	}

	/**
	 * Migrates a bounded batch of posts for one post type.
	 *
	 * @param string                $post_type  Post type.
	 * @param int                   $batch_size Posts per batch.
	 * @param int                   $offset     Query offset.
	 * @param BlockMigrationOptions $options    Migration options.
	 */
	public function migrate_batch(
		string $post_type,
		int $batch_size,
		int $offset,
		BlockMigrationOptions $options,
	): BlockBatchMigrationResult {
		$started    = $this->now_ms();
		$batch_size = max( 1, min( self::MAX_BATCH_SIZE, $batch_size ) );
		$offset     = max( 0, $offset );

		$query = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => BlockMigrationEligibility::ALLOWED_STATUSES,
				'posts_per_page'         => $batch_size,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$results = array();
		foreach ( (array) $query->posts as $post_id ) {
			$results[] = $this->migrate_post( (int) $post_id, $options );
		}

		$processed = count( $results );
		$next      = $offset + $processed;
		$has_more  = $next < (int) $query->found_posts;

		$batch = new BlockBatchMigrationResult(
			$results,
			$next,
			$has_more,
			$this->elapsed_ms( $started )
		);

		$this->logger->log(
			BlockMigrationLogger::EVENT_BATCH_COMPLETE,
			array(
				'post_type'   => $post_type,
				'processed'   => $processed,
				'next_offset' => $next,
				'has_more'    => $has_more,
				'dry_run'     => $options->dry_run,
				'elapsed_ms'  => $batch->elapsed_ms,
			)
		);

		return $batch;
	}

	/**
	 * Persists migrated content while suppressing save pipeline recursion.
	 *
	 * @param int    $post_id Post id.
	 * @param string $content Serialized block content.
	 * @return true|WP_Error
	 */
	private function persist_content( int $post_id, string $content ) {
		self::begin();

		try {
			/**
			 * Short-circuits post persistence during explicit migration.
			 *
			 * @since 0.1.0
			 *
			 * @param null|true|\WP_Error $short_circuit Replace with true on success or WP_Error on failure.
			 * @param int                 $post_id       Post id.
			 * @param string              $content       Serialized block content.
			 */
			$short_circuit = apply_filters( 'aiml_block_migration_persist_post', null, $post_id, $content );
			if ( $short_circuit instanceof WP_Error ) {
				return $short_circuit;
			}

			if ( true === $short_circuit ) {
				return true;
			}

			$updated = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				),
				true
			);
		} finally {
			self::end();
		}

		if ( $updated instanceof WP_Error ) {
			return $updated;
		}

		return true;
	}

	/**
	 * Runs block extraction and store reconciliation for one post.
	 *
	 * @param WP_Post $post Canonical post.
	 */
	private function reconcile_post( WP_Post $post ): void {
		$segments = $this->migration_extractor->extract( $post );
		$this->store->sync_source(
			Store::SOURCE_POST,
			(int) $post->ID,
			(string) $post->post_type,
			$segments
		);
	}

	/**
	 * Extracts block segments from post content, using injected content when supplied.
	 *
	 * @param WP_Post $post            Source post.
	 * @param string  $injected_content Content after UUID injection.
	 * @param bool    $content_changed Whether injection changed content.
	 * @return array<string, array<string, mixed>>
	 */
	private function extract_segments( WP_Post $post, string $injected_content, bool $content_changed ): array {
		if ( ! $content_changed ) {
			return $this->migration_extractor->extract( $post );
		}

		$clone               = clone $post;
		$clone->post_content = $injected_content;

		return $this->migration_extractor->extract( $clone );
	}

	/**
	 * Marks migration active and suspends the save pipeline.
	 */
	private static function begin(): void {
		++self::$active_depth;
		SavePipeline::suspend_for_migration();
	}

	/**
	 * Resumes the save pipeline when the outermost migration completes.
	 */
	private static function end(): void {
		--self::$active_depth;

		if ( self::$active_depth <= 0 ) {
			self::$active_depth = 0;
			SavePipeline::resume_after_migration();
		}
	}

	/**
	 * Normalizes injector stats for migration reporting.
	 *
	 * @param InjectResult $inject Injection outcome.
	 * @return array{created_count: int, malformed_replaced_count: int, duplicate_repaired_count: int}
	 */
	private function normalized_stats( InjectResult $inject ): array {
		$stats = $inject->stats + InjectResult::default_stats();

		return array(
			'created_count'            => (int) ( $stats['uuid_created'] ?? 0 ),
			'malformed_replaced_count' => (int) ( $stats['uuid_replaced_invalid'] ?? 0 ),
			'duplicate_repaired_count' => (int) ( $stats['uuid_duplicate_repaired'] ?? 0 ),
		);
	}

	/**
	 * Builds rollback metadata without storing body text.
	 *
	 * @param int                                                                                     $post_id       Post id.
	 * @param string                                                                                  $original_hash Original content hash.
	 * @param string                                                                                  $migrated_hash Migrated content hash.
	 * @param array{created_count: int, malformed_replaced_count: int, duplicate_repaired_count: int} $stats         UUID counters.
	 * @return array<string, mixed>
	 */
	private function audit_meta( int $post_id, string $original_hash, string $migrated_hash, array $stats ): array {
		return array(
			'post_id'                  => $post_id,
			'original_hash'            => $original_hash,
			'migrated_hash'            => $migrated_hash,
			'migrated_at'              => function_exists( 'current_time' ) ? current_time( 'mysql', true ) : '',
			'created_count'            => $stats['created_count'],
			'malformed_replaced_count' => $stats['malformed_replaced_count'],
			'duplicate_repaired_count' => $stats['duplicate_repaired_count'],
		);
	}

	/**
	 * Builds a skipped migration result and logs it.
	 *
	 * @param int                                                                                     $post_id       Post id.
	 * @param string                                                                                  $post_type     Post type.
	 * @param string                                                                                  $skip_reason   Skip reason code.
	 * @param BlockMigrationOptions                                                                   $options       Migration options.
	 * @param int                                                                                     $started       Start timestamp in milliseconds.
	 * @param string                                                                                  $original_hash Original content hash.
	 * @param string                                                                                  $migrated_hash Migrated content hash.
	 * @param array{created_count: int, malformed_replaced_count: int, duplicate_repaired_count: int} $stats         UUID counters.
	 * @param int                                                                                     $segment_count Extracted segment count.
	 */
	private function skipped(
		int $post_id,
		string $post_type,
		string $skip_reason,
		BlockMigrationOptions $options,
		int $started,
		string $original_hash = '',
		string $migrated_hash = '',
		array $stats = array(
			'created_count'            => 0,
			'malformed_replaced_count' => 0,
			'duplicate_repaired_count' => 0,
		),
		int $segment_count = 0,
	): BlockMigrationResult {
		$result = new BlockMigrationResult(
			$post_id,
			$post_type,
			BlockMigrationResult::STATUS_SKIPPED,
			$options->dry_run,
			$skip_reason,
			false,
			$original_hash,
			$migrated_hash,
			$stats['created_count'],
			$stats['malformed_replaced_count'],
			$stats['duplicate_repaired_count'],
			$segment_count,
			false,
			'',
			$this->elapsed_ms( $started )
		);

		$this->logger->log(
			BlockMigrationLogger::EVENT_SKIPPED,
			array_merge(
				$this->log_context( $result ),
				array( 'skip_reason' => $skip_reason )
			)
		);

		return $result;
	}

	/**
	 * Builds a dry-run migration result and logs it.
	 *
	 * @param WP_Post                                                                                 $post            Source post.
	 * @param BlockMigrationOptions                                                                   $options         Migration options.
	 * @param string                                                                                  $original_hash   Original content hash.
	 * @param string                                                                                  $migrated_hash   Migrated content hash.
	 * @param bool                                                                                    $content_changed Whether content would change.
	 * @param array{created_count: int, malformed_replaced_count: int, duplicate_repaired_count: int} $stats           UUID counters.
	 * @param int                                                                                     $segment_count   Extracted segment count.
	 * @param int                                                                                     $started         Start timestamp in milliseconds.
	 */
	private function dry_run(
		WP_Post $post,
		BlockMigrationOptions $options,
		string $original_hash,
		string $migrated_hash,
		bool $content_changed,
		array $stats,
		int $segment_count,
		int $started,
	): BlockMigrationResult {
		$result = new BlockMigrationResult(
			(int) $post->ID,
			(string) $post->post_type,
			BlockMigrationResult::STATUS_DRY_RUN,
			true,
			'',
			$content_changed,
			$original_hash,
			$migrated_hash,
			$stats['created_count'],
			$stats['malformed_replaced_count'],
			$stats['duplicate_repaired_count'],
			$segment_count,
			false,
			'',
			$this->elapsed_ms( $started ),
			$this->audit_meta( (int) $post->ID, $original_hash, $migrated_hash, $stats )
		);

		$this->logger->log(
			BlockMigrationLogger::EVENT_DRY_RUN,
			$this->log_context( $result )
		);

		return $result;
	}

	/**
	 * Builds a failed migration result and logs it.
	 *
	 * @param WP_Post               $post           Source post.
	 * @param BlockMigrationOptions $options        Migration options.
	 * @param string                $original_hash  Original content hash.
	 * @param string                $migrated_hash  Migrated content hash.
	 * @param string                $failure_reason Failure reason code.
	 * @param InjectResult          $inject         Injection outcome.
	 * @param int                   $started        Start timestamp in milliseconds.
	 * @param int                   $segment_count  Extracted segment count.
	 */
	private function failed(
		WP_Post $post,
		BlockMigrationOptions $options,
		string $original_hash,
		string $migrated_hash,
		string $failure_reason,
		InjectResult $inject,
		int $started,
		int $segment_count = 0,
	): BlockMigrationResult {
		$stats = $this->normalized_stats( $inject );

		$result = new BlockMigrationResult(
			(int) $post->ID,
			(string) $post->post_type,
			BlockMigrationResult::STATUS_FAILED,
			$options->dry_run,
			'',
			false,
			$original_hash,
			$migrated_hash,
			$stats['created_count'],
			$stats['malformed_replaced_count'],
			$stats['duplicate_repaired_count'],
			$segment_count,
			false,
			$failure_reason,
			$this->elapsed_ms( $started )
		);

		$this->logger->log(
			BlockMigrationLogger::EVENT_POST_FAILED,
			array_merge(
				$this->log_context( $result ),
				array( 'failure_reason' => $failure_reason )
			)
		);

		return $result;
	}

	/**
	 * Builds structured log metadata for one migration result.
	 *
	 * @param BlockMigrationResult $result Migration result.
	 * @return array<string, mixed>
	 */
	private function log_context( BlockMigrationResult $result ): array {
		return array(
			'post_id'                  => $result->post_id,
			'post_type'                => $result->post_type,
			'dry_run'                  => $result->dry_run,
			'content_changed'          => $result->content_changed,
			'created_count'            => $result->created_count,
			'malformed_replaced_count' => $result->malformed_replaced_count,
			'duplicate_repaired_count' => $result->duplicate_repaired_count,
			'segment_count'            => $result->segment_count,
			'original_hash'            => $result->original_hash,
			'migrated_hash'            => $result->migrated_hash,
			'failure_reason'           => $result->failure_reason,
			'elapsed_ms'               => $result->elapsed_ms,
		);
	}

	/**
	 * Returns the current wall time in milliseconds.
	 */
	private function now_ms(): int {
		return (int) round( microtime( true ) * 1000 );
	}

	/**
	 * Returns elapsed milliseconds since a start timestamp.
	 *
	 * @param int $started Start timestamp in milliseconds.
	 */
	private function elapsed_ms( int $started ): int {
		return max( 0, $this->now_ms() - $started );
	}
}
