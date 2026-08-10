<?php
/**
 * Integration test bootstrap: loads the WordPress test suite with this plugin
 * active. WooCommerce is installed alongside it for parity with the later
 * WooCommerce milestones, even though Milestone 1 does not depend on it.
 *
 * The WordPress core install is provisioned by tests/bin/install-wp.sh. The
 * plugin is loaded through the symlink inside the test install's plugins
 * directory so plugin-path helpers resolve the way they do in production.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

$aiml_root = dirname( __DIR__, 2 );

require_once $aiml_root . '/vendor/autoload.php';

$aiml_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $aiml_tests_dir ) {
	$aiml_tests_dir = $aiml_root . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
}

require_once $aiml_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		require WP_PLUGIN_DIR . '/ai-multilingual/ai-multilingual.php';

		// Create this plugin's tables here rather than alongside the rest of
		// the fixture setup below. Two things read them earlier than
		// `setup_theme`: the router resolves the request language on
		// `plugins_loaded`, and WooCommerce's installer creates its pages on
		// `setup_theme`, which fires `save_post` and therefore stale detection.
		// DDL also has to land before the first test transaction opens, since
		// it would implicitly commit.
		( new \AIMultilingual\Database\Migrator() )->migrate();
	}
);

/**
 * Prepares Action Scheduler so WooCommerce install can call AS APIs safely.
 *
 * AS defers `store()->init()` to `init` priority 1 and only then sets
 * `$data_store_initialized`. The harness must install WooCommerce on
 * `setup_theme` (before `init`) so tables exist when `WooCommerce::init`
 * runs at priority 0. Without this early store init, `WC_Install::install()`
 * hits `as_unschedule_all_actions()` while the store is null, which emits
 * `_doing_it_wrong` (AS 3.1.6+ / Woo 10.9.x). PHPUnit promotes that notice to
 * an error for `@runTestsInSeparateProcesses` classes that re-bootstrap WP.
 *
 * Production never needs this: AIML schedules AS work on `init` priority 20.
 */
$aiml_ensure_action_scheduler_store = static function (): void {
	if ( ! class_exists( \ActionScheduler::class ) || \ActionScheduler::is_initialized() ) {
		return;
	}

	\ActionScheduler::store()->init();
	\ActionScheduler::logger()->init();

	$aiml_flag = new \ReflectionProperty( \ActionScheduler::class, 'data_store_initialized' );
	$aiml_flag->setAccessible( true );
	$aiml_flag->setValue( null, true );

	do_action( 'action_scheduler_init' );
};

tests_add_filter(
	'setup_theme',
	function () use ( $aiml_ensure_action_scheduler_store ) {
		$aiml_ensure_action_scheduler_store();

		\WC_Install::install();

		// Parity with the production stack (HPOS is enabled on the target site).
		// Tables are created here, before any test transaction, so no runtime
		// DDL (which would implicitly commit) happens mid-test.
		update_option( 'woocommerce_feature_custom_order_tables_enabled', 'yes' );

		$aiml_sync = \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class;
		if ( function_exists( 'wc_get_container' ) && class_exists( $aiml_sync ) ) {
			wc_get_container()->get( $aiml_sync )->create_database_tables();
			update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );
			update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
		}

		$GLOBALS['wp_roles'] = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Seed the default language and grant the translation capability
		// against the freshly built roles object. Migrations already ran on
		// `muplugins_loaded`; activation is idempotent, so re-running them is a
		// no-op.
		\AIMultilingual\Plugin::activate();
	}
);

require_once $aiml_tests_dir . '/includes/bootstrap.php';

// Shared base class. Required explicitly because it extends WP_UnitTestCase,
// which only exists once the bootstrap above has run, and because PHPUnit only
// autoloads files matching the *Test.php suffix.
require_once __DIR__ . '/AimlTestCase.php';
require_once __DIR__ . '/JobsEchoAIProvider.php';
require_once __DIR__ . '/ScriptedAIProvider.php';
require_once __DIR__ . '/AvailableSchedulerStub.php';
require_once __DIR__ . '/UnavailableJobsSchedulerStub.php';
require_once __DIR__ . '/RecordingJobsSchedulerStub.php';
require_once __DIR__ . '/WorkspaceTestHelpers.php';
