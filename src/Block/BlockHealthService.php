<?php
/**
 * Strategy F block health service.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\RenderGateContext;
use AIMultilingual\Translation\Store;
use WP_Post;
use WP_Query;

/**
 * Coordinates read-only block health scans for admin and CLI consumers.
 */
final class BlockHealthService {

	public const ERROR_TRANSLATIONS_TABLE_MISSING = 'translations_table_missing';

	public const ERROR_STORE_COUNT_FAILED = 'store_count_failed';

	public const ERROR_POST_SCAN_FAILED = 'post_scan_failed';

	public const ERROR_ELIGIBLE_COUNT_FAILED = 'eligible_count_failed';

	public const ERROR_INVALID_SAMPLE_SIZE = 'invalid_sample_size';

	public const LIMITATION_DUPLICATE_ROWS_NOT_DETECTABLE = 'duplicate_segment_rows_not_detectable';

	public const LIMITATION_SAMPLE_INCOMPLETE = 'sample_incomplete';

	/**
	 * Builds the health service.
	 *
	 * @param Store                 $store     Segment store.
	 * @param Extractor             $extractor Body classifier.
	 * @param BlockIdentityAnalyzer $analyzer  UUID compliance analyzer.
	 */
	public function __construct(
		private Store $store,
		private Extractor $extractor,
		private BlockIdentityAnalyzer $analyzer,
	) {
	}

	/**
	 * Produces one immutable health snapshot.
	 *
	 * @param BlockHealthScanOptions $options Scan configuration.
	 */
	public function scan( BlockHealthScanOptions $options ): BlockHealthSnapshot {
		$started     = hrtime( true );
		$errors      = array();
		$limitations = array();
		$incomplete  = false;

		if ( ! $this->store->translations_table_exists() ) {
			$errors[]   = self::ERROR_TRANSLATIONS_TABLE_MISSING;
			$incomplete = true;
		}

		if ( $options->sample_size < 1 || $options->sample_size > BlockHealthScanOptions::MAX_SAMPLE_SIZE ) {
			$errors[] = self::ERROR_INVALID_SAMPLE_SIZE;
		}

		$sample_size = $options->normalized_sample_size();
		$scan_mode   = $options->full_scan ? BlockHealthSnapshot::SCAN_MODE_FULL : BlockHealthSnapshot::SCAN_MODE_SAMPLE;
		$sampled     = ! $options->full_scan && $options->source_id <= 0;

		if ( $sampled ) {
			$limitations[] = self::LIMITATION_SAMPLE_INCOMPLETE;
		}

		if ( ! $this->store->duplicate_segment_rows_detectable() ) {
			$limitations[] = self::LIMITATION_DUPLICATE_ROWS_NOT_DETECTABLE;
		}

		$post_ids        = $this->resolve_post_ids( $options, $sample_size, $errors, $incomplete );
		$eligible_count  = $this->count_eligible_posts( $options, $errors, $incomplete );
		$post_results    = array();
		$skip_counts     = array();
		$compliant       = 0;
		$non_compliant   = 0;
		$skipped         = 0;
		$missing_posts   = 0;
		$malformed_posts = 0;
		$duplicate_posts = 0;

		foreach ( $post_ids as $post_id ) {
			$result = $this->scan_post_id( (int) $post_id );
			if ( null === $result ) {
				continue;
			}

			$post_results[] = $result;

			if ( null !== $result->skip_reason ) {
				++$skipped;
				$reason                 = $result->skip_reason;
				$skip_counts[ $reason ] = ( $skip_counts[ $reason ] ?? 0 ) + 1;
				continue;
			}

			if ( null !== $result->error_code ) {
				continue;
			}

			$compliance = $result->compliance;
			if ( ! $compliance instanceof BlockIdentityCompliance ) {
				continue;
			}

			if ( $compliance->missing_uuid_count > 0 ) {
				++$missing_posts;
			}

			if ( $compliance->malformed_uuid_count > 0 ) {
				++$malformed_posts;
			}

			if ( $compliance->duplicate_uuid_count > 0 ) {
				++$duplicate_posts;
			}

			if ( $result->is_compliant() ) {
				++$compliant;
			} else {
				++$non_compliant;
			}
		}

		$store_counts = $this->collect_store_counts( $options );

		if ( $sampled && $eligible_count > count( $post_ids ) ) {
			$incomplete = true;
		}

		$elapsed = (int) round( ( hrtime( true ) - $started ) / 1_000_000 );

		return new BlockHealthSnapshot(
			gmdate( 'c' ),
			$scan_mode,
			$sample_size,
			count( $post_results ),
			$eligible_count,
			$compliant,
			$non_compliant,
			$skipped,
			$skip_counts,
			$missing_posts,
			$malformed_posts,
			$duplicate_posts,
			$store_counts['total_block_segments'],
			$store_counts['translated_block_segments'],
			$store_counts['renderable_block_segments'],
			$store_counts['stale_block_segments'],
			$store_counts['orphaned_block_segments'],
			$store_counts['duplicate_segment_rows'],
			$this->store->duplicate_segment_rows_detectable(),
			$errors,
			$limitations,
			$incomplete,
			$sampled,
			$elapsed,
			$post_results
		);
	}

	/**
	 * Resolves post ids to scan in deterministic ascending order.
	 *
	 * @param BlockHealthScanOptions $options     Scan configuration.
	 * @param int                    $sample_size Normalized sample size.
	 * @param array                  $errors      Collected error codes.
	 * @param bool                   $incomplete  Incomplete flag updated by reference.
	 * @return list<int>
	 */
	private function resolve_post_ids( BlockHealthScanOptions $options, int $sample_size, array &$errors, bool &$incomplete ): array {
		if ( $options->source_id > 0 ) {
			return array( $options->source_id );
		}

		$query_args = array(
			'post_type'              => $this->resolved_post_types( $options ),
			'post_status'            => BlockMigrationEligibility::ALLOWED_STATUSES,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'posts_per_page'         => $options->full_scan ? -1 : $sample_size,
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$query = new WP_Query( $query_args );
		if ( $query instanceof WP_Query ) {
			$ids = array_map( 'intval', (array) $query->posts );

			return array_values( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) );
		}

		$errors[]   = self::ERROR_ELIGIBLE_COUNT_FAILED;
		$incomplete = true;

		return array();
	}

	/**
	 * Counts structurally eligible posts in the scan population.
	 *
	 * @param BlockHealthScanOptions $options    Scan configuration.
	 * @param array                  $errors     Collected error codes.
	 * @param bool                   $incomplete Incomplete flag updated by reference.
	 */
	private function count_eligible_posts( BlockHealthScanOptions $options, array &$errors, bool &$incomplete ): int {
		if ( $options->source_id > 0 ) {
			$post = get_post( $options->source_id );

			return $post instanceof WP_Post ? 1 : 0;
		}

		$query = new WP_Query(
			array(
				'post_type'              => $this->resolved_post_types( $options ),
				'post_status'            => BlockMigrationEligibility::ALLOWED_STATUSES,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! $query instanceof WP_Query ) {
			$errors[]   = self::ERROR_ELIGIBLE_COUNT_FAILED;
			$incomplete = true;

			return 0;
		}

		return max( 0, (int) $query->found_posts );
	}

	/**
	 * Scans one post for eligibility and UUID compliance.
	 *
	 * @param int $post_id Post id.
	 */
	private function scan_post_id( int $post_id ): ?BlockHealthPostResult {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new BlockHealthPostResult(
				$post_id,
				'unknown',
				BlockMigrationEligibility::REASON_MISSING_POST
			);
		}

		$skip_reason = BlockMigrationEligibility::evaluate( $post, $this->extractor );
		if ( null !== $skip_reason ) {
			return new BlockHealthPostResult(
				(int) $post->ID,
				(string) $post->post_type,
				$skip_reason
			);
		}

		$compliance = $this->analyzer->analyze_content( (string) $post->post_content );

		return new BlockHealthPostResult(
			(int) $post->ID,
			(string) $post->post_type,
			null,
			$compliance
		);
	}

	/**
	 * Collects scoped Store aggregate counts.
	 *
	 * @param BlockHealthScanOptions $options Scan configuration.
	 * @return array{
	 *     total_block_segments: int,
	 *     translated_block_segments: int,
	 *     renderable_block_segments: int,
	 *     stale_block_segments: int,
	 *     orphaned_block_segments: int,
	 *     duplicate_segment_rows: int|null
	 * }
	 */
	private function collect_store_counts( BlockHealthScanOptions $options ): array {
		$scope_id = max( 0, $options->source_id );

		if ( ! $this->store->translations_table_exists() ) {
			return array(
				'total_block_segments'      => 0,
				'translated_block_segments' => 0,
				'renderable_block_segments' => 0,
				'stale_block_segments'      => 0,
				'orphaned_block_segments'   => 0,
				'duplicate_segment_rows'    => null,
			);
		}

		return array(
			'total_block_segments'      => $this->store->count_block_segments( $options->source_type, $scope_id ),
			'translated_block_segments' => $this->store->count_translated_block_segments( $options->source_type, $scope_id ),
			'renderable_block_segments' => $this->store->count_renderable_block_segments( $options->source_type, $scope_id ),
			'stale_block_segments'      => $this->store->count_stale_block_segments( $options->source_type, $scope_id ),
			'orphaned_block_segments'   => $this->store->count_orphaned_block_segments( $options->source_type, $scope_id ),
			'duplicate_segment_rows'    => $this->store->duplicate_segment_rows_detectable()
				? $this->store->count_duplicate_segment_rows( $options->source_type, $scope_id )
				: null,
		);
	}

	/**
	 * Resolves post types for population queries.
	 *
	 * @param BlockHealthScanOptions $options Scan configuration.
	 * @return list<string>
	 */
	private function resolved_post_types( BlockHealthScanOptions $options ): array {
		if ( is_string( $options->post_type ) && '' !== $options->post_type ) {
			return array( $options->post_type );
		}

		return RenderGateContext::SUPPORTED_POST_TYPES;
	}
}
