<?php
/**
 * WP-CLI commands.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual;

use AIMultilingual\Block\BlockHealthScanOptions;
use AIMultilingual\Block\BlockHealthService;
use AIMultilingual\Block\BlockHealthSnapshot;
use AIMultilingual\Block\BlockIdentityMigration;
use AIMultilingual\Block\BlockMetricsAggregator;
use AIMultilingual\Block\BlockMetricsSnapshot;
use AIMultilingual\Block\BlockMigrationOptions;
use AIMultilingual\Language\Languages;
use AIMultilingual\Routing\LocalizedUrlsActivationService;
use AIMultilingual\Jobs\SlugRouteActivationJob;
use AIMultilingual\Settings;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsCheck;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsOptions;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsService;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsSnapshot;
use AIMultilingual\Translation\AI\FieldSemanticMapper;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use WP_CLI;
use WP_Error;
use WP_Post;

/**
 * The four commands Milestone 1 needs.
 *
 * Scope is deliberately narrow: these exist so the acceptance walkthrough can
 * be scripted and so translations can be seeded without clicking. Slug, job,
 * memory, glossary, provider and usage commands are not scaffolded here — each
 * arrives with the milestone that owns the feature, where it can actually be
 * tested.
 *
 * Commands are registered as closures rather than a command class so the
 * services they need can be injected instead of rebuilt.
 */
final class Cli {

	/**
	 * Registers the commands.
	 *
	 * @param Languages                           $languages Language configuration.
	 * @param Store                               $store     Segment store.
	 * @param Extractor                           $extractor Source extractor.
	 * @param BlockIdentityMigration              $migration Block identity migration service.
	 * @param BlockHealthService                  $health    Block health diagnostics service.
	 * @param BlockMetricsAggregator              $metrics     Request-scoped metrics aggregator.
	 * @param SeoDiagnosticsService               $seo         SEO diagnostics core (A.SEOf).
	 * @param PublicationService|null             $publication Optional TI.7 publication service.
	 * @param LocalizedUrlsActivationService|null $localized_urls Optional localized URL activation.
	 * @param SlugRouteActivationJob|null         $activation_job Optional activation job diagnostics.
	 */
	public static function register(
		Languages $languages,
		Store $store,
		Extractor $extractor,
		BlockIdentityMigration $migration,
		BlockHealthService $health,
		BlockMetricsAggregator $metrics,
		SeoDiagnosticsService $seo,
		?PublicationService $publication = null,
		?LocalizedUrlsActivationService $localized_urls = null,
		?SlugRouteActivationJob $activation_job = null,
	): void {
		if ( ! class_exists( WP_CLI::class ) ) {
			return;
		}

		WP_CLI::add_command(
			'aiml language list',
			static function () use ( $languages ): void {
				self::language_list( $languages );
			},
			array(
				'shortdesc' => 'Lists configured languages.',
			)
		);

		WP_CLI::add_command(
			'aiml language add',
			static function ( array $args, array $assoc ) use ( $languages ): void {
				self::language_add( $languages, $args, $assoc );
			},
			array(
				'shortdesc' => 'Adds a target language.',
				'synopsis'  => array(
					array(
						'type'        => 'positional',
						'name'        => 'code',
						'description' => 'URL code, for example sv.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'locale',
						'description' => 'WordPress locale, for example sv_SE.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'name',
						'description' => 'English name, for example Swedish.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'native-name',
						'optional'    => true,
						'description' => 'Name in the language itself.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'status',
						'optional'    => true,
						'options'     => array( 'disabled', 'preview', 'published' ),
						'description' => 'Initial state. Defaults to preview.',
					),
				),
			)
		);

		WP_CLI::add_command(
			'aiml translation get',
			static function ( array $args, array $assoc ) use ( $languages, $store ): void {
				self::translation_get( $languages, $store, $args, $assoc );
			},
			array(
				'shortdesc' => 'Prints one translated field.',
				'synopsis'  => self::translation_synopsis(),
			)
		);

		WP_CLI::add_command(
			'aiml assessment get',
			static function ( array $args, array $assoc ) use ( $languages, $store ): void {
				self::assessment_get( $languages, $store, $args, $assoc );
			},
			array(
				'shortdesc' => 'Prints TI.5 evidence-based risk assessment JSON for one field.',
				'synopsis'  => self::translation_synopsis(),
			)
		);

		if ( null !== $publication ) {
			WP_CLI::add_command(
				'aiml publication explain',
				static function ( array $args, array $assoc ) use ( $languages, $publication ): void {
					self::publication_explain( $languages, $publication, $args, $assoc );
				},
				array(
					'shortdesc' => 'Explains TI.7 publication eligibility for one field (non-mutating).',
					'synopsis'  => array_merge(
						self::translation_synopsis(),
						array(
							array(
								'type'        => 'flag',
								'name'        => 'automatic',
								'optional'    => true,
								'description' => 'Evaluate the automatic publication path.',
							),
						)
					),
				)
			);

			WP_CLI::add_command(
				'aiml publication publish',
				static function ( array $args, array $assoc ) use ( $languages, $publication ): void {
					self::publication_publish( $languages, $publication, $args, $assoc );
				},
				array(
					'shortdesc' => 'Publishes one translation segment when eligible (TI.7).',
					'synopsis'  => self::translation_synopsis(),
				)
			);

			WP_CLI::add_command(
				'aiml publication unpublish',
				static function ( array $args, array $assoc ) use ( $languages, $publication ): void {
					self::publication_unpublish( $languages, $publication, $args, $assoc );
				},
				array(
					'shortdesc' => 'Unpublishes one translation segment (TI.7).',
					'synopsis'  => self::translation_synopsis(),
				)
			);

			WP_CLI::add_command(
				'aiml publication status',
				static function ( array $args, array $assoc ) use ( $languages, $store, $publication ): void {
					self::publication_status( $languages, $store, $publication, $args, $assoc );
				},
				array(
					'shortdesc' => 'Prints publish_status metadata for one field.',
					'synopsis'  => self::translation_synopsis(),
				)
			);
		}

		if ( null !== $localized_urls && null !== $activation_job ) {
			WP_CLI::add_command(
				'aiml localized-urls status',
				static function () use ( $localized_urls, $activation_job ): void {
					self::localized_urls_status( $localized_urls, $activation_job );
				},
				array(
					'shortdesc' => 'Prints localized URL activation state and frontier diagnostics.',
				)
			);
		}

		WP_CLI::add_command(
			'aiml translation set',
			static function ( array $args, array $assoc ) use ( $languages, $store, $extractor ): void {
				self::translation_set( $languages, $store, $extractor, $args, $assoc );
			},
			array(
				'shortdesc' => 'Stores one translated field.',
				'synopsis'  => array_merge(
					self::translation_synopsis(),
					array(
						array(
							'type'        => 'assoc',
							'name'        => 'value',
							'optional'    => true,
							'description' => 'Translated text. Omit and pass --stdin to read from standard input.',
						),
						array(
							'type'        => 'flag',
							'name'        => 'stdin',
							'optional'    => true,
							'description' => 'Read the translated text from standard input.',
						),
					)
				),
			)
		);

		WP_CLI::add_command(
			'aiml block migrate',
			static function ( array $args, array $assoc ) use ( $migration ): void {
				self::block_migrate( $migration, $assoc );
			},
			array(
				'shortdesc' => 'Migrates Strategy F block identity on canonical posts.',
				'synopsis'  => array(
					array(
						'type'        => 'assoc',
						'name'        => 'post-id',
						'optional'    => true,
						'description' => 'Migrate one canonical post by id.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'post-type',
						'optional'    => true,
						'description' => 'Migrate a bounded batch for one post type.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'batch-size',
						'optional'    => true,
						'description' => 'Batch size when using --post-type (max 100).',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'offset',
						'optional'    => true,
						'description' => 'Batch offset when using --post-type.',
					),
					array(
						'type'        => 'flag',
						'name'        => 'dry-run',
						'optional'    => true,
						'description' => 'Analyze without writing posts or reconciling.',
					),
					array(
						'type'        => 'flag',
						'name'        => 'refresh-extraction',
						'optional'    => true,
						'description' => 'Run extraction reconciliation even when identity is already compliant.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'format',
						'optional'    => true,
						'options'     => array( 'table', 'json' ),
						'description' => 'Output format. Defaults to table.',
					),
				),
			)
		);

		WP_CLI::add_command(
			'aiml block status',
			static function ( array $args, array $assoc ) use ( $health, $metrics ): void {
				self::block_status( $health, $metrics, $assoc );
			},
			array(
				'shortdesc' => 'Reports Strategy F block health (read-only).',
				'synopsis'  => array(
					array(
						'type'        => 'assoc',
						'name'        => 'sample-size',
						'optional'    => true,
						'description' => 'Bounded post sample size (default 100, max 1000).',
					),
					array(
						'type'        => 'flag',
						'name'        => 'full-scan',
						'optional'    => true,
						'description' => 'Scan all eligible posts instead of a sample.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'source-type',
						'optional'    => true,
						'description' => 'Filter by post type, for example page.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'source-id',
						'optional'    => true,
						'description' => 'Scope the scan to one canonical post id.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'format',
						'optional'    => true,
						'options'     => array( 'table', 'json' ),
						'description' => 'Output format. Defaults to table.',
					),
				),
				'longdesc'  => "Examples:\n"
					. "  wp aiml block status\n"
					. "  wp aiml block status --full-scan\n"
					. "  wp aiml block status --format=json\n"
					. "  wp aiml block status --sample-size=250\n"
					. '  wp aiml block status --source-type=page --source-id=42',
			)
		);

		WP_CLI::add_command(
			'aiml seo status',
			static function ( array $args, array $assoc ) use ( $seo ): void {
				self::seo_status( $seo, $assoc );
			},
			array(
				'shortdesc' => 'Reports SEO diagnostics health (read-only; A.SEOf).',
				'synopsis'  => array(
					array(
						'type'        => 'assoc',
						'name'        => 'doc-path',
						'optional'    => true,
						'description' => 'Unprefixed document path for SB11 contract checks (default /). Avoids WP-CLI --path.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'check-url',
						'optional'    => true,
						'description' => 'Absolute URL for bounded HTTP emission checks (not WP-CLI --url).',
					),
					array(
						'type'        => 'flag',
						'name'        => 'no-http',
						'optional'    => true,
						'description' => 'Skip bounded HTTP redirect/title checks.',
					),
					array(
						'type'        => 'assoc',
						'name'        => 'format',
						'optional'    => true,
						'options'     => array( 'table', 'json' ),
						'description' => 'Output format. Defaults to table.',
					),
				),
				'longdesc'  => "Examples:\n"
					. "  wp aiml seo status\n"
					. "  wp aiml seo status --doc-path=/ --check-url=https://example.com/sv/\n"
					. "  wp aiml seo status --doc-path=/product/sample/ --format=json\n"
					. '  wp aiml seo status --no-http --format=json',
			)
		);
	}

	/**
	 * Shared positional/assoc definition for the translation commands.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function translation_synopsis(): array {
		return array(
			array(
				'type'        => 'positional',
				'name'        => 'post_id',
				'description' => 'Canonical post ID.',
			),
			array(
				'type'        => 'positional',
				'name'        => 'language',
				'description' => 'Target language code, for example sv.',
			),
			array(
				'type'        => 'assoc',
				'name'        => 'field',
				'options'     => array( 'title', 'excerpt', 'content' ),
				'description' => 'Which field to read or write.',
			),
		);
	}

	// -- Commands --

	/**
	 * Runs A.SEOf SEO diagnostics (shared SF13 core).
	 *
	 * @param SeoDiagnosticsService $seo   SEO diagnostics service.
	 * @param array<string, mixed>  $assoc Associative arguments.
	 */
	private static function seo_status( SeoDiagnosticsService $seo, array $assoc ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			WP_CLI::error( 'SEO status requires the manage_options capability.' );
		}

		$format = (string) ( $assoc['format'] ?? 'table' );
		if ( ! in_array( $format, array( 'table', 'json' ), true ) ) {
			WP_CLI::error( 'Unsupported --format value. Use table or json.' );
		}

		$options  = new SeoDiagnosticsOptions(
			url: isset( $assoc['check-url'] ) ? (string) $assoc['check-url'] : '',
			path: isset( $assoc['doc-path'] ) ? (string) $assoc['doc-path'] : '/',
			include_http: empty( $assoc['no-http'] ),
		);
		$snapshot = $seo->scan( $options );

		if ( 'json' === $format ) {
			// Use line + wp_json_encode (not print_value) to avoid WP-CLI
			// formatter collisions observed with `seo status --format=json`.
			WP_CLI::line( (string) wp_json_encode( $snapshot->to_array() ) );
			return;
		}

		self::render_seo_status_table( $snapshot );
	}

	/**
	 * Prints operator-focused SEO diagnostics table output.
	 *
	 * @param SeoDiagnosticsSnapshot $snapshot SF13 snapshot.
	 */
	private static function render_seo_status_table( SeoDiagnosticsSnapshot $snapshot ): void {
		WP_CLI::log( 'SEO diagnostics (model ' . $snapshot->to_array()['model'] . ')' );
		WP_CLI::log( 'generated: ' . $snapshot->generated_at );
		WP_CLI::log( 'path: ' . $snapshot->scope_path );
		WP_CLI::log( 'url: ' . $snapshot->scope_url );
		WP_CLI::log( 'http_fetches: ' . (string) $snapshot->http_fetches );
		WP_CLI::log( 'elapsed_ms: ' . (string) $snapshot->elapsed_ms );
		if ( $snapshot->limitations ) {
			WP_CLI::log( 'limitations: ' . implode( ', ', $snapshot->limitations ) );
		}

		$rows = array();
		foreach ( $snapshot->checks as $check ) {
			if ( ! $check instanceof SeoDiagnosticsCheck ) {
				continue;
			}
			$rows[] = array(
				'id'        => $check->id,
				'status'    => $check->status,
				'ownership' => $check->ownership,
				'code'      => $check->code,
				'message'   => $check->message,
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'status', 'ownership', 'code', 'message' ) );
	}

	/**
	 * Runs Strategy F block health diagnostics.
	 *
	 * @param BlockHealthService     $health  Block health service.
	 * @param BlockMetricsAggregator $metrics Request-scoped metrics aggregator.
	 * @param array<string, mixed>   $assoc   Associative arguments.
	 */
	private static function block_status( BlockHealthService $health, BlockMetricsAggregator $metrics, array $assoc ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			WP_CLI::error( 'Block status requires the manage_options capability.' );
		}

		$format = (string) ( $assoc['format'] ?? 'table' );
		if ( ! in_array( $format, array( 'table', 'json' ), true ) ) {
			WP_CLI::error( 'Unsupported --format value. Use table or json.' );
		}

		$options          = self::block_status_options( $assoc );
		$snapshot         = $health->scan( $options );
		$metrics_snapshot = $metrics->snapshot();

		if ( 'json' === $format ) {
			WP_CLI::print_value(
				array_merge(
					$snapshot->to_array(),
					array(
						'metrics' => $metrics_snapshot->to_array(),
					)
				),
				array( 'format' => 'json' )
			);

			return;
		}

		self::render_block_status_table( $snapshot, $metrics_snapshot );
	}

	/**
	 * Parses block status CLI options.
	 *
	 * @param array<string, mixed> $assoc Associative arguments.
	 */
	private static function block_status_options( array $assoc ): BlockHealthScanOptions {
		if ( ! empty( $assoc['full-scan'] ) && isset( $assoc['sample-size'] ) ) {
			WP_CLI::warning( '--sample-size is ignored when --full-scan is set.' );
		}

		$sample_size = isset( $assoc['sample-size'] ) ? (int) $assoc['sample-size'] : BlockHealthScanOptions::DEFAULT_SAMPLE_SIZE;
		if ( $sample_size < 1 || $sample_size > BlockHealthScanOptions::MAX_SAMPLE_SIZE ) {
			WP_CLI::error(
				sprintf(
					'Invalid --sample-size value. Must be between 1 and %d.',
					BlockHealthScanOptions::MAX_SAMPLE_SIZE
				)
			);
		}

		$source_id = isset( $assoc['source-id'] ) ? (int) $assoc['source-id'] : 0;
		if ( isset( $assoc['source-id'] ) && $source_id <= 0 ) {
			WP_CLI::error( 'Invalid --source-id value.' );
		}

		$post_type = null;
		if ( isset( $assoc['source-type'] ) ) {
			$post_type = (string) $assoc['source-type'];
			if ( '' === $post_type ) {
				WP_CLI::error( 'Invalid --source-type value.' );
			}
		}

		return new BlockHealthScanOptions(
			source_id: $source_id,
			post_type: $post_type,
			sample_size: $sample_size,
			full_scan: ! empty( $assoc['full-scan'] ),
		);
	}

	/**
	 * Prints operator-focused block health table output.
	 *
	 * @param BlockHealthSnapshot  $snapshot Health snapshot.
	 * @param BlockMetricsSnapshot $metrics  Metrics snapshot.
	 */
	private static function render_block_status_table( BlockHealthSnapshot $snapshot, BlockMetricsSnapshot $metrics ): void {
		$duplicate_rows = $snapshot->duplicate_segment_rows_detectable
			? (string) ( $snapshot->duplicate_segment_rows ?? 0 )
			: 'N/A (UNIQUE constraint)';

		$sections = array(
			'Health'   => array(
				array(
					'metric' => 'generated',
					'value'  => $snapshot->generated_at,
				),
				array(
					'metric' => 'elapsed ms',
					'value'  => (string) $snapshot->elapsed_ms,
				),
				array(
					'metric' => 'scan mode',
					'value'  => $snapshot->scan_mode,
				),
				array(
					'metric' => 'sample size',
					'value'  => (string) $snapshot->requested_sample_size,
				),
				array(
					'metric' => 'scanned posts',
					'value'  => (string) $snapshot->scanned_post_count,
				),
				array(
					'metric' => 'eligible posts',
					'value'  => (string) $snapshot->eligible_post_count,
				),
				array(
					'metric' => 'compliant posts',
					'value'  => (string) $snapshot->compliant_post_count,
				),
				array(
					'metric' => 'non-compliant posts',
					'value'  => (string) $snapshot->non_compliant_post_count,
				),
				array(
					'metric' => 'skipped posts',
					'value'  => (string) $snapshot->skipped_post_count,
				),
			),
			'UUID'     => array(
				array(
					'metric' => 'missing',
					'value'  => (string) $snapshot->posts_with_missing_uuids,
				),
				array(
					'metric' => 'malformed',
					'value'  => (string) $snapshot->posts_with_malformed_uuids,
				),
				array(
					'metric' => 'duplicate',
					'value'  => (string) $snapshot->posts_with_duplicate_uuids,
				),
			),
			'Segments' => array(
				array(
					'metric' => 'total',
					'value'  => (string) $snapshot->total_block_segments,
				),
				array(
					'metric' => 'translated',
					'value'  => (string) $snapshot->translated_block_segments,
				),
				array(
					'metric' => 'renderable',
					'value'  => (string) $snapshot->renderable_block_segments,
				),
				array(
					'metric' => 'stale',
					'value'  => (string) $snapshot->stale_block_segments,
				),
				array(
					'metric' => 'orphaned',
					'value'  => (string) $snapshot->orphaned_block_segments,
				),
				array(
					'metric' => 'duplicate rows',
					'value'  => $duplicate_rows,
				),
			),
			'Status'   => array(
				array(
					'metric' => 'complete',
					'value'  => $snapshot->incomplete ? 'incomplete' : 'complete',
				),
				array(
					'metric' => 'limitations',
					'value'  => '' === implode( ', ', $snapshot->limitations ) ? 'none' : implode( ', ', $snapshot->limitations ),
				),
				array(
					'metric' => 'error count',
					'value'  => (string) count( $snapshot->errors ),
				),
			),
		);

		$metric_rows = array(
			array(
				'metric' => 'render count',
				'value'  => (string) $metrics->render_count,
			),
			array(
				'metric' => 'render total ms',
				'value'  => (string) $metrics->render_total_elapsed_ms,
			),
			array(
				'metric' => 'render average ms',
				'value'  => (string) $metrics->render_average_elapsed_ms,
			),
			array(
				'metric' => 'render maximum ms',
				'value'  => (string) $metrics->render_max_elapsed_ms,
			),
			array(
				'metric' => 'ignored event count',
				'value'  => (string) $metrics->ignored_event_count,
			),
			array(
				'metric' => 'metrics completeness',
				'value'  => $metrics->incomplete ? 'incomplete' : 'complete',
			),
		);

		foreach ( self::metrics_counter_groups() as $group => $keys ) {
			foreach ( $keys as $key ) {
				$metric_rows[] = array(
					'metric' => $group . ': ' . $key,
					'value'  => (string) ( $metrics->counters[ $key ] ?? 0 ),
				);
			}
		}

		$sections['Metrics'] = $metric_rows;

		foreach ( $sections as $title => $rows ) {
			WP_CLI::log( $title );
			WP_CLI\Utils\format_items( 'table', $rows, array( 'metric', 'value' ) );
		}
	}

	/**
	 * Counter groups for metrics table output.
	 *
	 * @return array<string, list<string>>
	 */
	private static function metrics_counter_groups(): array {
		return array(
			'uuid'       => array(
				BlockMetricsAggregator::COUNTER_UUID_CREATED,
				BlockMetricsAggregator::COUNTER_MALFORMED_UUID_DETECTED,
				BlockMetricsAggregator::COUNTER_DUPLICATE_UUID_DETECTED,
				BlockMetricsAggregator::COUNTER_UUID_REPAIRED,
				BlockMetricsAggregator::COUNTER_UUID_REPAIR_FAILED,
			),
			'extraction' => array(
				BlockMetricsAggregator::COUNTER_EXTRACTION_STARTED,
				BlockMetricsAggregator::COUNTER_EXTRACTION_COMPLETED,
				BlockMetricsAggregator::COUNTER_FIELDS_EXTRACTED,
				BlockMetricsAggregator::COUNTER_FIELDS_SKIPPED,
				BlockMetricsAggregator::COUNTER_EXTRACTION_FAILED,
			),
			'render'     => array(
				BlockMetricsAggregator::COUNTER_RENDER_ATTEMPTED,
				BlockMetricsAggregator::COUNTER_RENDER_COMPLETED,
				BlockMetricsAggregator::COUNTER_RENDER_SKIPPED,
				BlockMetricsAggregator::COUNTER_RENDER_FAILED,
			),
			'migration'  => array(
				BlockMetricsAggregator::COUNTER_POSTS_SCANNED,
				BlockMetricsAggregator::COUNTER_POSTS_MIGRATED,
				BlockMetricsAggregator::COUNTER_POSTS_ALREADY_COMPLIANT,
				BlockMetricsAggregator::COUNTER_POSTS_SKIPPED,
				BlockMetricsAggregator::COUNTER_MIGRATIONS_FAILED,
				BlockMetricsAggregator::COUNTER_CONCURRENT_MODIFICATIONS,
			),
			'settings'   => array(
				BlockMetricsAggregator::COUNTER_FEATURE_FLAGS_CHANGED,
				BlockMetricsAggregator::COUNTER_FLAG_COMBINATIONS_REJECTED,
			),
		);
	}

	/**
	 * Runs Strategy F block identity migration.
	 *
	 * @param BlockIdentityMigration $migration Migration service.
	 * @param array<string, mixed>   $assoc     Associative arguments.
	 */
	private static function block_migrate( BlockIdentityMigration $migration, array $assoc ): void {
		if ( ! isset( $assoc['post-id'] ) && ! isset( $assoc['post-type'] ) ) {
			WP_CLI::error( 'Pass --post-id=<id> or --post-type=<type>.' );
		}

		if ( isset( $assoc['post-id'] ) && isset( $assoc['post-type'] ) ) {
			WP_CLI::error( 'Pass only one selector: --post-id or --post-type.' );
		}

		$options = new BlockMigrationOptions(
			! empty( $assoc['dry-run'] ),
			! empty( $assoc['refresh-extraction'] )
		);
		$format  = (string) ( $assoc['format'] ?? 'table' );

		if ( isset( $assoc['post-id'] ) ) {
			$post_id = (int) $assoc['post-id'];
			if ( $post_id <= 0 ) {
				WP_CLI::error( 'Invalid --post-id value.' );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				WP_CLI::error( 'You do not have permission to migrate that post.' );
			}

			$result  = $migration->migrate_post( $post_id, $options );
			$payload = array( 'posts' => array( $result->to_array() ) );
		} else {
			if ( ! current_user_can( 'manage_options' ) ) {
				WP_CLI::error( 'Batch migration requires the manage_options capability.' );
			}

			$post_type  = (string) $assoc['post-type'];
			$batch_size = isset( $assoc['batch-size'] ) ? (int) $assoc['batch-size'] : 20;
			$offset     = isset( $assoc['offset'] ) ? (int) $assoc['offset'] : 0;
			$batch      = $migration->migrate_batch( $post_type, $batch_size, $offset, $options );
			$payload    = $batch->to_array();
		}

		if ( 'json' === $format ) {
			WP_CLI::print_value( $payload, array( 'format' => 'json' ) );

			return;
		}

		$rows = isset( $batch ) ? $payload['results'] : $payload['posts'];
		WP_CLI\Utils\format_items(
			'table',
			$rows,
			array(
				'post_id',
				'post_type',
				'status',
				'skip_reason',
				'content_changed',
				'created_count',
				'duplicate_repaired_count',
				'segment_count',
				'failure_reason',
			)
		);

		if ( isset( $batch ) ) {
			WP_CLI::log(
				sprintf(
					'Batch complete. next_offset=%d has_more=%s elapsed_ms=%d',
					(int) $payload['next_offset'],
					$payload['has_more'] ? 'true' : 'false',
					(int) $payload['elapsed_ms']
				)
			);
		}
	}

	/**
	 * Prints the language table.
	 *
	 * @param Languages $languages Language configuration.
	 */
	private static function language_list( Languages $languages ): void {
		$rows = array();

		foreach ( $languages->all() as $language ) {
			$rows[] = array(
				'id'      => (int) $language->language_id,
				'code'    => (string) $language->code,
				'locale'  => (string) $language->locale,
				'name'    => (string) $language->name,
				'status'  => (string) $language->status,
				'default' => $language->is_default ? 'yes' : 'no',
			);
		}

		if ( array() === $rows ) {
			WP_CLI::log( 'No languages configured.' );

			return;
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'code', 'locale', 'name', 'status', 'default' ) );
	}

	/**
	 * Adds a target language.
	 *
	 * @param Languages            $languages Language configuration.
	 * @param array<int, string>   $args      Positional arguments.
	 * @param array<string, mixed> $assoc     Associative arguments.
	 */
	private static function language_add( Languages $languages, array $args, array $assoc ): void {
		$code = (string) ( $args[0] ?? '' );

		$result = $languages->insert(
			array(
				'code'        => $code,
				'locale'      => (string) ( $assoc['locale'] ?? '' ),
				'name'        => (string) ( $assoc['name'] ?? '' ),
				'native_name' => (string) ( $assoc['native-name'] ?? '' ),
				'status'      => (string) ( $assoc['status'] ?? Languages::STATUS_PREVIEW ),
			)
		);

		if ( $result instanceof WP_Error ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Added language %s (id %d).', $code, (int) $result ) );
	}

	/**
	 * Prints one translated field.
	 *
	 * @param Languages            $languages Language configuration.
	 * @param Store                $store     Segment store.
	 * @param array<int, string>   $args      Positional arguments.
	 * @param array<string, mixed> $assoc     Associative arguments.
	 */
	private static function translation_get( Languages $languages, Store $store, array $args, array $assoc ): void {
		list( $post, $language, $field_key ) = self::resolve_target( $languages, $args, $assoc );

		$segment = $store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $field_key );

		if ( null === $segment || Store::STATUS_MISSING === $segment->status ) {
			WP_CLI::error( 'No translation stored for that field.' );
		}

		if ( ! empty( $segment->is_stale ) ) {
			WP_CLI::warning( 'The source has changed since this translation was written.' );
		}

		WP_CLI::print_value( (string) ( $segment->translated_text ?? '' ) );
	}

	/**
	 * Prints TI.5 assessment JSON for one stored translation field.
	 *
	 * @param Languages            $languages Language configuration.
	 * @param Store                $store     Segment store.
	 * @param array<int, string>   $args      Positional arguments.
	 * @param array<string, mixed> $assoc     Associative arguments.
	 */
	private static function assessment_get( Languages $languages, Store $store, array $args, array $assoc ): void {
		list( $post, $language, $field_key ) = self::resolve_target( $languages, $args, $assoc );

		$segment = $store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $field_key );

		$dto = array(
			'source_text'     => is_object( $segment ) ? (string) ( $segment->source_text ?? '' ) : '',
			'translated_text' => is_object( $segment ) ? (string) ( $segment->translated_text ?? '' ) : '',
			'text_format'     => is_object( $segment ) ? (string) ( $segment->text_format ?? Store::FORMAT_PLAIN ) : Store::FORMAT_PLAIN,
			'status'          => is_object( $segment ) ? (string) ( $segment->status ?? Store::STATUS_MISSING ) : Store::STATUS_MISSING,
			'review_status'   => is_object( $segment ) ? (string) ( $segment->review_status ?? Store::REVIEW_NOT_SUBMITTED ) : Store::REVIEW_NOT_SUBMITTED,
			'provider'        => is_object( $segment ) ? (string) ( $segment->provider ?? '' ) : '',
			'model'           => is_object( $segment ) ? (string) ( $segment->model ?? '' ) : '',
			'prompt_profile'  => is_object( $segment ) ? (string) ( $segment->prompt_profile ?? '' ) : '',
			'prompt_version'  => is_object( $segment ) ? (string) ( $segment->prompt_version ?? '' ) : '',
			'field_key'       => $field_key,
			'segment_key'     => $field_key,
			'source_subtype'  => (string) $post->post_type,
		);

		$mapper     = new FieldSemanticMapper();
		$assembler  = new AssessmentAssembler();
		$assessment = $assembler->assess_segment(
			$dto,
			$mapper->map( $dto, (string) $post->post_type ),
			array(),
			false
		);

		WP_CLI::print_value( wp_json_encode( $assessment->to_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Explains TI.7 publication eligibility for one field.
	 *
	 * @param Languages            $languages   Language configuration.
	 * @param PublicationService   $publication Publication service.
	 * @param array<int, string>   $args        Positional arguments.
	 * @param array<string, mixed> $assoc       Associative arguments.
	 */
	private static function publication_explain(
		Languages $languages,
		PublicationService $publication,
		array $args,
		array $assoc
	): void {
		list( $post, $language, $field_key ) = self::resolve_target( $languages, $args, $assoc );

		$decision = $publication->explain(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$field_key,
			! empty( $assoc['automatic'] )
		);

		if ( $decision instanceof WP_Error ) {
			WP_CLI::error( $decision->get_error_message() );
		}

		WP_CLI::print_value( wp_json_encode( $decision->to_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Publishes one translation segment when eligible.
	 *
	 * @param Languages            $languages   Language configuration.
	 * @param PublicationService   $publication Publication service.
	 * @param array<int, string>   $args        Positional arguments.
	 * @param array<string, mixed> $assoc       Associative arguments.
	 */
	private static function publication_publish(
		Languages $languages,
		PublicationService $publication,
		array $args,
		array $assoc
	): void {
		list( $post, $language, $field_key ) = self::resolve_target( $languages, $args, $assoc );

		$result = $publication->publish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$field_key,
			false,
			(int) get_current_user_id(),
			'cli'
		);

		if ( $result instanceof WP_Error ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::print_value( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Unpublishes one translation segment.
	 *
	 * @param Languages            $languages   Language configuration.
	 * @param PublicationService   $publication Publication service.
	 * @param array<int, string>   $args        Positional arguments.
	 * @param array<string, mixed> $assoc       Associative arguments.
	 */
	private static function publication_unpublish(
		Languages $languages,
		PublicationService $publication,
		array $args,
		array $assoc
	): void {
		list( $post, $language, $field_key ) = self::resolve_target( $languages, $args, $assoc );

		$result = $publication->unpublish(
			Store::SOURCE_POST,
			(int) $post->ID,
			(int) $language->language_id,
			$field_key,
			(int) get_current_user_id()
		);

		if ( $result instanceof WP_Error ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::print_value( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Prints localized URL activation diagnostics.
	 *
	 * @param LocalizedUrlsActivationService $activation Activation service.
	 * @param SlugRouteActivationJob         $job          Activation job.
	 */
	private static function localized_urls_status(
		LocalizedUrlsActivationService $activation,
		SlugRouteActivationJob $job
	): void {
		$settings = new Settings();

		WP_CLI::print_value(
			wp_json_encode(
				array(
					'state'               => $settings->localized_urls_state(),
					'checkpoint_route_id' => $activation->checkpoint_route_id(),
					'error'               => $settings->localized_urls_activation_error(),
					'active_route_count'  => $job->count_active_routes(),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			)
		);
	}

	/**
	 * Prints publish_status metadata for one field.
	 *
	 * @param Languages            $languages   Language configuration.
	 * @param Store                $store       Segment store.
	 * @param PublicationService   $publication Publication service.
	 * @param array<int, string>   $args        Positional arguments.
	 * @param array<string, mixed> $assoc       Associative arguments.
	 */
	private static function publication_status(
		Languages $languages,
		Store $store,
		PublicationService $publication,
		array $args,
		array $assoc
	): void {
		list( $post, $language, $field_key ) = self::resolve_target( $languages, $args, $assoc );

		$segment = $store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, $field_key );
		if ( null === $segment ) {
			WP_CLI::error( 'No translation stored for that field.' );
		}

		WP_CLI::print_value(
			wp_json_encode(
				array(
					'publish_status' => (string) ( $segment->publish_status ?? Store::PUBLISH_UNPUBLISHED ),
					'published_at'   => $segment->published_at ?? null,
					'published_by'   => isset( $segment->published_by ) ? (int) $segment->published_by : null,
					'mode'           => $publication->current_mode(),
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			)
		);
	}

	/**
	 * Stores one translated field.
	 *
	 * @param Languages            $languages Language configuration.
	 * @param Store                $store     Segment store.
	 * @param Extractor            $extractor Source extractor.
	 * @param array<int, string>   $args      Positional arguments.
	 * @param array<string, mixed> $assoc     Associative arguments.
	 */
	private static function translation_set( Languages $languages, Store $store, Extractor $extractor, array $args, array $assoc ): void {
		list( $post, $language, $field_key ) = self::resolve_target( $languages, $args, $assoc );

		// Same refusal the editor applies, enforced here so the scriptable path
		// cannot corrupt block or Elementor content.
		if ( Extractor::FIELD_CONTENT === $field_key && ! $extractor->can_translate_body( $post ) ) {
			WP_CLI::error( Extractor::body_notice( $extractor->body_status( $post ) ) );
		}

		$sources = $extractor->extract( $post );

		if ( ! isset( $sources[ $field_key ] ) ) {
			WP_CLI::error( 'That field is empty on the source post, so there is nothing to translate.' );
		}

		if ( ! empty( $assoc['stdin'] ) ) {
			$value = (string) file_get_contents( 'php://stdin' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} elseif ( isset( $assoc['value'] ) ) {
			$value = (string) $assoc['value'];
		} else {
			WP_CLI::error( 'Pass --value=<text> or --stdin.' );

			return;
		}

		$spec = Extractor::fields()[ $field_key ];

		$result = $store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => (int) $language->language_id,
				'field_key'       => $field_key,
				'segment_key'     => $field_key,
				'segment_order'   => (int) $spec['order'],
				'text_format'     => (string) $spec['format'],
				'source_text'     => (string) $sources[ $field_key ]['source_text'],
				'translated_text' => $value,
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		if ( $result instanceof WP_Error ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Saved %s for post %d in %s.', $field_key, (int) $post->ID, (string) $language->code ) );
	}

	/**
	 * Resolves and validates the post, language and field of a command.
	 *
	 * @param Languages            $languages Language configuration.
	 * @param array<int, string>   $args      Positional arguments.
	 * @param array<string, mixed> $assoc     Associative arguments.
	 * @return array{0: WP_Post, 1: object, 2: string}
	 */
	private static function resolve_target( Languages $languages, array $args, array $assoc ): array {
		$post = get_post( (int) ( $args[0] ?? 0 ) );

		if ( ! $post instanceof WP_Post ) {
			WP_CLI::error( 'Unknown post.' );
		}

		$language = $languages->find_by_code( (string) ( $args[1] ?? '' ) );

		if ( null === $language ) {
			WP_CLI::error( 'Unknown language code.' );
		}

		if ( ! empty( $language->is_default ) ) {
			WP_CLI::error( 'The default language is the source; it is not translated.' );
		}

		$field_key = Extractor::field_key( (string) ( $assoc['field'] ?? '' ) );

		if ( null === $field_key ) {
			WP_CLI::error( 'Use --field=title, --field=excerpt or --field=content.' );
		}

		return array( $post, $language, $field_key );
	}
}
