<?php
/**
 * Composition root.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual;

use AIMultilingual\Jobs\BackgroundTranslationBatchCoordinator;
use AIMultilingual\Jobs\BackgroundTranslationConcurrencyPolicy;
use AIMultilingual\Jobs\BackgroundTranslationDiagnostics;
use AIMultilingual\Jobs\BackgroundTranslationItemProcessor;
use AIMultilingual\Jobs\BackgroundTranslationItemRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobAuditLogger;
use AIMultilingual\Jobs\BackgroundTranslationJobProviderValidator;
use AIMultilingual\Jobs\BackgroundTranslationJobRepository;
use AIMultilingual\Jobs\BackgroundTranslationJobService;
use AIMultilingual\Jobs\BackgroundTranslationBudgetPolicy;
use AIMultilingual\Jobs\BackgroundTranslationRetentionCleanup;
use AIMultilingual\Jobs\BackgroundTranslationRetryPolicy;
use AIMultilingual\Jobs\BackgroundTranslationScheduler;
use AIMultilingual\Jobs\BackgroundTranslationWorker;
use AIMultilingual\Jobs\JobLeaseService;
use AIMultilingual\Jobs\JobProgressReconciler;
use AIMultilingual\Jobs\JobsCapabilities;
use AIMultilingual\Jobs\JobsCli;
use AIMultilingual\Jobs\JobsController;
use AIMultilingual\Jobs\JobsViewModelSerializer;
use AIMultilingual\Admin\Editor;
use AIMultilingual\Admin\GlossaryAdminPage;
use AIMultilingual\Admin\RolloutAdminPage;
use AIMultilingual\Admin\SeoDiagnosticsAdminPage;
use AIMultilingual\Admin\SettingsPage;
use AIMultilingual\Admin\TranslatorWorkspace;
use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\AttributeRegistrar;
use AIMultilingual\Block\BlockExtractionLogger;
use AIMultilingual\Block\BlockIdentityLogger;
use AIMultilingual\Block\BlockHealthService;
use AIMultilingual\Block\BlockIdentityAnalyzer;
use AIMultilingual\Block\BlockIdentityMigration;
use AIMultilingual\Block\BlockMetricsAggregator;
use AIMultilingual\Block\BlockMigrationLogger;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\BlockRenderLogger;
use AIMultilingual\Block\SavePipeline;
use AIMultilingual\Block\UuidInjector;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Database\Migrator;
use AIMultilingual\Elementor\ElementorCacheInvalidation;
use AIMultilingual\Elementor\ElementorCompatibility;
use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDiagnostics;
use AIMultilingual\Elementor\ElementorDocumentDetector;
use AIMultilingual\Elementor\ElementorExtractor;
use AIMultilingual\Elementor\ElementorFrontendBridge;
use AIMultilingual\Elementor\ElementorIdentity;
use AIMultilingual\Elementor\ElementorOverlayApplier;
use AIMultilingual\Elementor\ElementorOverlayResolver;
use AIMultilingual\Frontend\Switcher;
use AIMultilingual\Seo\Diagnostics\SeoDiagnosticsService;
use AIMultilingual\Seo\DocumentSeoHead;
use AIMultilingual\Seo\LanguageRelationshipService;
use AIMultilingual\Glossary\GlossaryCapabilities;
use AIMultilingual\Glossary\GlossaryMatcher;
use AIMultilingual\Glossary\GlossaryNormalizer;
use AIMultilingual\Glossary\GlossaryRepository;
use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Integration\FluentForms\FluentFormsIntegration;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
		use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
		use AIMultilingual\Integration\WooCommerce\OrderTransactionalLanguage;
		use AIMultilingual\Integration\WooCommerce\CustomerEmailBridge;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationDiagnostics;
use AIMultilingual\Integration\IntegrationFrontendBridge;
use AIMultilingual\Integration\IntegrationRegistry;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\LanguageResolver;
use AIMultilingual\Language\Languages;
use AIMultilingual\Rest\GlossaryController;
use AIMultilingual\Rest\ProviderController;
use AIMultilingual\Rest\ViewModel\ReviewQueueItemSerializer;
use AIMultilingual\Rest\ViewModel\WorkspacePageSummarySerializer;
use AIMultilingual\Rest\ViewModel\WorkspaceSegmentSerializer;
use AIMultilingual\Rest\ViewModel\WorkspaceTranslationStatusSerializer;
use AIMultilingual\Rest\WorkspaceController;
use AIMultilingual\Rollout\RolloutCapabilities;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Rollout\GeneralAvailabilityCohortProvider;
use AIMultilingual\Rollout\RolloutPolicyService;
use AIMultilingual\Rollout\RolloutRenderGateBridge;
use AIMultilingual\Rollout\RolloutCli;
use AIMultilingual\Rollout\Cache\RenderCacheInvalidationService;
use AIMultilingual\Rollout\Cache\RenderCacheKeyFactory;
use AIMultilingual\Rollout\Cache\RenderCacheService;
use AIMultilingual\Rollout\Cache\RolloutCacheInvalidationHooks;
use AIMultilingual\Rollout\Cache\RolloutRenderCacheBridge;
use AIMultilingual\Rollout\Metrics\RolloutMetricsCollector;
use AIMultilingual\Routing\Router;
use AIMultilingual\Translation\AI\CredentialVault;
use AIMultilingual\Translation\AI\NullAIProvider;
use AIMultilingual\Translation\AI\PromptProfileRegistry;
use AIMultilingual\Translation\AI\ProviderFactory;
use AIMultilingual\Translation\AI\ProviderRegistry;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\BlockExtractor;
use AIMultilingual\Translation\Publication\PublicationAuditLogger;
use AIMultilingual\Translation\Publication\PublicationEditInvalidationAuditBridge;
use AIMultilingual\Translation\Publication\PublicationPolicy;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Memory\TMEligibilityPolicy;
use AIMultilingual\Translation\Memory\TMGenerationLookup;
use AIMultilingual\Translation\Memory\TMRepository;
use AIMultilingual\Translation\Memory\TranslationMemoryService;
use AIMultilingual\Translation\BlockFrontendRenderer;
use AIMultilingual\Translation\BlockFrontendRenderLogger;
use AIMultilingual\Translation\BlockRenderGate;
use AIMultilingual\Translation\BlockRenderer;
use AIMultilingual\Translation\BlockTranslationLookup;
use AIMultilingual\Translation\BlockTranslationSanitizer;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Renderer;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\QA\Checks\GlossaryTermCheck;
use AIMultilingual\Workspace\QA\QAEngine;
use AIMultilingual\Workspace\PreviewService;
use AIMultilingual\Workspace\Review\ReviewCapabilities;
use AIMultilingual\Workspace\Review\ReviewEditInvalidationAuditBridge;
use AIMultilingual\Workspace\Review\ReviewWorkflowService;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\Suggestion\AISuggestionProvider;
use AIMultilingual\Workspace\Suggestion\GlossarySuggestionProvider;
use AIMultilingual\Workspace\Suggestion\TranslationMemorySuggestionProvider;
use AIMultilingual\Workspace\TranslationService;
use AIMultilingual\Workspace\TranslationStatusCalculator;
use AIMultilingual\Workspace\TranslationSuggestionService;
use AIMultilingual\Workspace\WorkspaceService;
use WP_Post;

/**
 * Builds the object graph once and lets each service register its own hooks.
 *
 * Services are constructed eagerly and wired by hand. There is no container:
 * the graph is small, and an explicit constructor call says more about what
 * depends on what than a service definition would.
 */
final class Plugin {

	/**
	 * Capability gating translation work.
	 *
	 * Translators and editors need this daily; it is deliberately not
	 * `manage_options`, which stays reserved for configuration.
	 */
	public const CAPABILITY = 'aiml_translate';

	/**
	 * Roles granted the translation capability on activation.
	 */
	private const CAPABLE_ROLES = array( 'administrator', 'editor' );

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether services have been wired.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Plugin settings shared across the composition root.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * Returns the shared plugin instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wires services and registers hooks. Idempotent.
	 */
	public function init(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$this->settings = new Settings();
		$settings       = $this->settings;
		$cache          = new Cache();
		$languages      = new Languages( $cache );
		$resolver       = new LanguageResolver();
		$context        = new LanguageContext();
		$store          = new Store( $cache );

		$adapter_registry = new AdapterRegistry();
		$block_registry   = new BlockRegistry( $adapter_registry );
		$block_logger     = new BlockIdentityLogger();
		$uuid_injector    = new UuidInjector( $block_registry, $block_logger );
		$block_extractor  = new BlockExtractor(
			$adapter_registry,
			$block_registry,
			new BlockExtractionLogger()
		);

		$elementor_diagnostics   = new ElementorDiagnostics();
		$elementor_compatibility = new ElementorCompatibility();
		$elementor_detector      = new ElementorDocumentDetector();
		$elementor_registry      = new ElementorControlRegistry();
		$elementor_identity      = new ElementorIdentity();
		$elementor_extractor     = new ElementorExtractor(
			$elementor_detector,
			$elementor_registry,
			$elementor_identity,
			$elementor_diagnostics
		);

		$integration_diagnostics = new IntegrationDiagnostics();
		$integration_registry    = new IntegrationRegistry( $integration_diagnostics );
		$plugin_identity         = new PluginIdentity( $integration_diagnostics );
		$woo_integration         = WooCommerceIntegration::create_default( $plugin_identity );
		$relationships           = new LanguageRelationshipService( $languages, $context );
		$integration_registry->register(
			FluentFormsIntegration::create_default( $plugin_identity )
		);
		$integration_registry->register( $woo_integration );
		$rank_math_integration = RankMathIntegration::create_default(
			$plugin_identity,
			$store,
			$context,
			$relationships
		);
		$integration_registry->register( $rank_math_integration );
		// A.SEOe: Rank Math serves sitemaps on parse_query (before `wp`).
		$rank_math_integration->register_sitemap_hooks();
		/**
		 * Register typed Integration API v1 integrations.
		 *
		 * Callbacks must call `$registry->register( PluginIntegrationInterface )`
		 * with code-owned objects only. Database/serialized callbacks are forbidden.
		 *
		 * @since 1.1.0
		 *
		 * @param IntegrationRegistry $integration_registry Registry.
		 */
		do_action( 'aiml_register_integrations', $integration_registry );

		$order_transactional_language = new OrderTransactionalLanguage(
			$context,
			$languages,
			$integration_diagnostics
		);
		$order_transactional_language->register();
		$email_bridge = new CustomerEmailBridge(
			$woo_integration,
			$order_transactional_language,
			$store,
			$plugin_identity,
			$integration_diagnostics
		);
		// Defer until WooCommerce has loaded so compatibility/hooks are accurate.
		add_action(
			'init',
			static function () use ( $email_bridge ): void {
				$email_bridge->register();
			},
			20
		);

		$extractor           = new Extractor( $settings, $block_extractor, $elementor_extractor, $integration_registry );
		$block_renderer      = new BlockRenderer( $adapter_registry, new BlockRenderLogger() );
		$config_repo         = new RolloutConfigurationRepository();
		$rollout_bridge      = new RolloutRenderGateBridge(
			new RolloutPolicyService( new GeneralAvailabilityCohortProvider() ),
			$config_repo
		);
		$render_cache_bridge = new RolloutRenderCacheBridge(
			new RenderCacheService( $cache ),
			new RenderCacheKeyFactory(),
			$store,
			$config_repo
		);
		$block_frontend      = new BlockFrontendRenderer(
			new BlockRenderGate( $rollout_bridge ),
			new BlockTranslationLookup( $store ),
			new BlockTranslationSanitizer(),
			$block_renderer,
			new BlockFrontendRenderLogger(),
			$settings,
			$context,
			$extractor,
			$render_cache_bridge
		);

		$router = new Router( $languages, $resolver, $context );
		$router->register();
		( new Renderer( $context, $store, $extractor, $block_frontend ) )->register();
		( new DocumentSeoHead( $relationships ) )->register();
		( new Switcher( $settings, $languages, $context, $relationships ) )->register();

		$elementor_resolver = new ElementorOverlayResolver( $store, $elementor_diagnostics );
		$elementor_applier  = new ElementorOverlayApplier( $elementor_registry, $elementor_diagnostics );
		( new ElementorFrontendBridge(
			$settings,
			$context,
			$elementor_compatibility,
			$elementor_detector,
			$elementor_extractor,
			$elementor_resolver,
			$elementor_applier,
			$elementor_diagnostics
		) )->register();
		( new ElementorCacheInvalidation( $elementor_detector, $elementor_compatibility, $settings, $context ) )->register();

		( new IntegrationFrontendBridge(
			$settings,
			$context,
			$integration_registry,
			$store,
			$integration_diagnostics
		) )->register();

		$assembler         = new SegmentAssembler( $extractor, $store, $block_registry );
		$status_calculator = new TranslationStatusCalculator( $store );
		$vault             = new CredentialVault();
		$profiles          = new PromptProfileRegistry();
		$provider_registry = new ProviderRegistry( $this->settings, new NullAIProvider() );
		$provider_registry->register(
			ProviderFactory::openai_from_settings( $this->settings, $vault, $profiles )
		);
		$glossary_service     = new GlossaryService(
			new GlossaryRepository(),
			new GlossaryNormalizer(),
			new GlossaryMatcher( new GlossaryNormalizer() )
		);
		$tm_service           = new TranslationMemoryService( new TMRepository() );
		$tm_lookup            = new TMGenerationLookup(
			$tm_service,
			new TMEligibilityPolicy( $glossary_service ),
			$glossary_service
		);
		$assessment_assembler = new AssessmentAssembler();
		$publication_policy   = new PublicationPolicy();
		$publication_audit    = new PublicationAuditLogger();
		$publication          = new PublicationService(
			$store,
			$assessment_assembler,
			$publication_policy,
			$publication_audit,
			$this->settings
		);
		$translation          = new TranslationService(
			$store,
			$assembler,
			$languages,
			$provider_registry->active(),
			$profiles,
			null,
			$glossary_service,
			null,
			$tm_lookup,
			$tm_service,
			$publication
		);
		$preview              = new PreviewService( $languages, $context, $router );
		$suggestion_service   = new TranslationSuggestionService(
			array(
				new TranslationMemorySuggestionProvider( $tm_service ),
				new GlossarySuggestionProvider( $glossary_service ),
				new AISuggestionProvider( $translation ),
			)
		);
		$qa_engine            = new QAEngine(
			null,
			! empty( $this->settings->get()['qa_block_on_error'] )
		);
		$qa_engine->register( new GlossaryTermCheck( $glossary_service ) );
		$review    = new ReviewWorkflowService( $store );
		$workspace = new WorkspaceService(
			$assembler,
			$status_calculator,
			$translation,
			$preview,
			$languages,
			$store,
			$extractor,
			$suggestion_service,
			$qa_engine,
			$tm_service,
			$review,
			null,
			$assessment_assembler,
			null,
			$publication
		);

		$job_repo        = new BackgroundTranslationJobRepository();
		$item_repo       = new BackgroundTranslationItemRepository();
		$job_leases      = new JobLeaseService( $job_repo, $item_repo );
		$job_reconciler  = new JobProgressReconciler( $job_repo, $item_repo );
		$job_audit       = new BackgroundTranslationJobAuditLogger();
		$job_diagnostics = new BackgroundTranslationDiagnostics( $job_repo, $item_repo );
		$job_scheduler   = new BackgroundTranslationScheduler(
			new BackgroundTranslationRetentionCleanup( $job_repo, $item_repo, $job_diagnostics ),
			$job_diagnostics
		);
		$job_budget      = new BackgroundTranslationBudgetPolicy( $job_repo );
		$job_concurrency = new BackgroundTranslationConcurrencyPolicy( $job_repo );
		$job_retry       = new BackgroundTranslationRetryPolicy();
		$job_provider    = new BackgroundTranslationJobProviderValidator( $provider_registry );
		$job_service     = new BackgroundTranslationJobService(
			$job_repo,
			$item_repo,
			$job_leases,
			$job_reconciler,
			$store,
			$assembler,
			$job_scheduler,
			$job_budget,
			$job_provider,
			$job_audit,
			$job_diagnostics
		);
		$job_processor   = new BackgroundTranslationItemProcessor(
			$store,
			$translation,
			$glossary_service,
			$assembler,
			$job_retry
		);
		$job_worker      = new BackgroundTranslationWorker(
			$job_processor,
			$job_service,
			$job_repo,
			$item_repo,
			$job_leases,
			$job_reconciler,
			$job_retry,
			$job_budget,
			$job_scheduler,
			$job_provider,
			$job_audit,
			$job_diagnostics,
			$job_concurrency
		);
		$job_batches     = new BackgroundTranslationBatchCoordinator( $job_service, $job_repo, $job_scheduler );
		$job_scheduler->register_hooks( $job_worker, $job_leases );
		add_action(
			'init',
			static function () use ( $job_scheduler ): void {
				$job_scheduler->schedule_sweep();
			},
			20
		);

		( new JobsController(
			$job_service,
			$job_batches,
			$job_scheduler,
			$job_worker,
			$languages,
			new JobsViewModelSerializer(),
			$job_diagnostics,
			$job_concurrency,
			$assessment_assembler,
			$assembler
		) )->register();

		// OTL.5: Jobs must be available to Operations bulk enqueue after Jobs stack exists.
		$workspace->set_jobs_service( $job_service );

		( new WorkspaceController(
			$workspace,
			new WorkspaceSegmentSerializer(),
			new WorkspacePageSummarySerializer(),
			new WorkspaceTranslationStatusSerializer(),
			new ReviewQueueItemSerializer()
		) )->register();

		( new ProviderController( $provider_registry ) )->register();
		( new GlossaryController( $glossary_service ) )->register();

		( new AttributeRegistrar( $settings, $block_registry ) )->register();
		( new SavePipeline( $settings, $uuid_injector, $extractor ) )->register();

		$metrics = new BlockMetricsAggregator();
		$metrics->register();

		( new RolloutMetricsCollector() )->register();

		( new RolloutCacheInvalidationHooks(
			new RenderCacheInvalidationService( null, $cache ),
			$languages
		) )->register();

		( new ReviewEditInvalidationAuditBridge() )->register();
		( new PublicationEditInvalidationAuditBridge( $publication_audit ) )->register();

		$this->register_stale_detection( $extractor, $store );

		$seo_diagnostics = new SeoDiagnosticsService(
			$relationships,
			$languages,
			$rank_math_integration
		);

		if ( is_admin() ) {
			( new SettingsPage( $settings, $languages, $vault ) )->register();
			( new RolloutAdminPage() )->register();
			( new SeoDiagnosticsAdminPage( $seo_diagnostics ) )->register();
			( new Editor( $languages, $store, $extractor ) )->register();
			( new TranslatorWorkspace( $languages ) )->register();
			( new GlossaryAdminPage( $languages ) )->register();

			// Bind-mount deployments update files in place and never fire the
			// activation hook, so schema drift has to be caught on its own.
			add_action(
				'admin_init',
				static function () {
					( new Migrator() )->maybe_migrate();
				}
			);
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$migration = new BlockIdentityMigration(
				$uuid_injector,
				$extractor,
				new Extractor(
					new Settings(
						array(
							'block_attr_registration_enabled' => true,
							'block_uuid_injection_enabled' => true,
							'block_extraction_enabled'     => true,
						)
					),
					$block_extractor
				),
				$store,
				new BlockMigrationLogger()
			);
			$health    = new BlockHealthService(
				$store,
				$extractor,
				new BlockIdentityAnalyzer( $block_registry )
			);

			Cli::register( $languages, $store, $extractor, $migration, $health, $metrics, $seo_diagnostics, $publication );
			RolloutCli::register();
			JobsCli::register( $job_service, $job_batches, $job_scheduler, $job_worker, $job_leases, $job_concurrency );
		}
	}

	/**
	 * Reloads settings from the database for long-lived processes and tests.
	 */
	public function reload_settings(): void {
		$this->settings?->reload();
	}

	/**
	 * Creates tables, seeds the default language and grants the capability.
	 *
	 * Also called directly by the integration bootstrap, which needs the schema
	 * in place before the first test transaction opens.
	 */
	public static function activate(): void {
		( new Migrator() )->migrate();

		$cache     = new Cache();
		$languages = new Languages( $cache );

		$languages->ensure_default( get_locale() );

		self::grant_capability();
		RolloutCapabilities::grant_default_roles();
		GlossaryCapabilities::grant_default_roles();
		ReviewCapabilities::grant_default_roles();
		JobsCapabilities::grant_default_roles();
	}

	/**
	 * Adds the translation capability to the roles that should hold it.
	 */
	private static function grant_capability(): void {
		foreach ( self::CAPABLE_ROLES as $role_name ) {
			$role = get_role( $role_name );

			if ( null !== $role && ! $role->has_cap( self::CAPABILITY ) ) {
				$role->add_cap( self::CAPABILITY );
			}
		}
	}

	/**
	 * Flags translations whose source has changed.
	 *
	 * Lives here rather than in its own class because it is the one line of
	 * glue between extraction and storage: re-extract the source, hand it to
	 * the store, and let the store decide what changed. Translated text and
	 * workflow status are never touched (invariant I6) — an edit to the English
	 * copy marks the Swedish for review, it does not discard it.
	 *
	 * @param Extractor $extractor Source extractor.
	 * @param Store     $store     Segment store.
	 */
	private function register_stale_detection( Extractor $extractor, Store $store ): void {
		add_action(
			'save_post',
			static function ( $post_id, $post ) use ( $extractor, $store ) {
				if ( BlockIdentityMigration::is_active() ) {
					return;
				}

				if ( ! $post instanceof WP_Post ) {
					return;
				}

				if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
					return;
				}

				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					return;
				}

				$store->sync_source(
					Store::SOURCE_POST,
					(int) $post_id,
					(string) $post->post_type,
					$extractor->extract( $post )
				);
			},
			20,
			2
		);
	}
}
