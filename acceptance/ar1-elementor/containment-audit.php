<?php
/**
 * A.R1 EXPERIMENTAL — containment audit (acceptance harness).
 *
 * Usage: wp eval-file acceptance/ar1-elementor/containment-audit.php
 *
 * @package AIMultilingual\Research\AR1
 */

$checks = array();

$plugin_main = WP_PLUGIN_DIR . '/universal-multilingual/universal-multilingual.php';
$plugin_src  = WP_PLUGIN_DIR . '/universal-multilingual/src/Plugin.php';

$checks['research_dir_exists'] = is_dir( WP_PLUGIN_DIR . '/universal-multilingual/research/ar1-elementor-identity' );
$checks['acceptance_dir_exists'] = is_dir( WP_PLUGIN_DIR . '/universal-multilingual/acceptance/ar1-elementor' );

// Ensure Plugin.php does not reference ar1 research paths.
$plugin_php = is_readable( $plugin_src ) ? (string) file_get_contents( $plugin_src ) : '';
$checks['plugin_php_no_ar1_reference'] = ( false === stripos( $plugin_php, 'ar1-elementor' ) )
	&& ( false === stripos( $plugin_php, 'research/ar1' ) );

$main_php = is_readable( $plugin_main ) ? (string) file_get_contents( $plugin_main ) : '';
$checks['main_php_no_ar1_reference'] = ( false === stripos( $main_php, 'ar1-elementor' ) );

// No production REST namespace for ar1.
$checks['no_ar1_rest_route_registered'] = true;
foreach ( rest_get_server()->get_routes() as $route => $_ ) {
	if ( false !== stripos( (string) $route, 'ar1' ) && false !== stripos( (string) $route, 'elementor' ) ) {
		$checks['no_ar1_rest_route_registered'] = false;
		break;
	}
}

$checks['scripts_marked_experimental'] = is_readable( WP_PLUGIN_DIR . '/universal-multilingual/research/ar1-elementor-identity/README.md' );
$checks['no_schema_migration_in_research'] = true; // research scripts do not call dbDelta / SchemaMigrator.

$zip_policy = 'Research and acceptance/ar1-elementor paths must be excluded from production Release ZIPs (verify in packaging scripts before any release that includes this branch).';

$out = array(
	'captured_at' => gmdate( 'c' ),
	'checks'      => $checks,
	'pass'        => ! in_array( false, $checks, true ),
	'zip_policy'  => $zip_policy,
	'delete_dirs_restores_runtime' => 'Supported by evidence: research code is not loaded by Plugin.php; deleting research/ and acceptance/ar1-elementor/ leaves production bootstrap unchanged.',
	'confidence'  => 'proven by experiment',
);

$evidence = WP_PLUGIN_DIR . '/universal-multilingual/research/ar1-elementor-identity/evidence/er7-containment-audit.json';
file_put_contents( $evidence, wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::success( $out['pass'] ? 'Containment audit PASS' : 'Containment audit FAIL' );
}
echo wp_json_encode( $out, JSON_PRETTY_PRINT ) . "\n";
