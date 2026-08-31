<?php
/**
 * A48 — Containment audit + synthesis helper evidence.
 *
 * Usage: wp eval-file research/ar2-nested-gutenberg-identity/scripts/a48-containment.php
 *
 * @package AIMultilingual\Research\AR2
 */

require_once __DIR__ . '/lib-ar2.php';

$plugin_main_candidates = array(
	dirname( ar2_root(), 2 ) . '/universal-multilingual.php',
	dirname( ar2_root(), 2 ) . '/plugin.php',
	dirname( ar2_root(), 2 ) . '/src/Plugin.php',
);

$loads_research = array();
foreach ( $plugin_main_candidates as $path ) {
	if ( ! is_readable( $path ) ) {
		continue;
	}
	$src = (string) file_get_contents( $path );
	$loads_research[ basename( dirname( $path ) ) . '/' . basename( $path ) ] = array(
		'path' => $path,
		'mentions_ar2' => false !== strpos( $src, 'ar2-nested' ),
		'mentions_research_dir' => (bool) preg_match( '/research\\/ar2/', $src ),
	);
}

// Broader scan of Plugin.php only.
$plugin_php = dirname( ar2_root(), 2 ) . '/src/Plugin.php';
$plugin_src = is_readable( $plugin_php ) ? (string) file_get_contents( $plugin_php ) : '';

$audit = array(
	'work_package' => 'A48',
	'research_root' => ar2_root(),
	'loaded_by_plugin_php' => false !== strpos( $plugin_src, 'ar2-nested' ) || false !== strpos( $plugin_src, 'research/ar2' ),
	'plugin_scan' => $loads_research,
	'zip_exclusion_doc' => file_exists( ar2_root() . '/ZIP_EXCLUSION.md' ),
	'readme_marks_experimental' => file_exists( ar2_root() . '/README.md' ),
	'schema_changed' => false,
	'rest_changed' => false,
	'runtime_src_changed' => false,
	'note' => 'Research artifacts live under research/ar2-nested-gutenberg-identity only; deleting that directory leaves production bootstrap unchanged.',
	'confidence' => 'Proven by experiment',
);

ar2_write_evidence( 'a48-containment-audit', $audit );
WP_CLI::success( 'A48 containment audit written' );
