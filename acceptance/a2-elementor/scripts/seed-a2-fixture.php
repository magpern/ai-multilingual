<?php
/**
 * A.2 acceptance fixture: create/update disposable Elementor page + seed SV overlays.
 *
 * Usage (wpcli): wp eval-file wp-content/plugins/ai-multilingual/acceptance/a2-elementor/scripts/seed-a2-fixture.php
 *
 * @package AIMultilingual
 */

use AIMultilingual\Cache\Cache;
use AIMultilingual\Elementor\Contract;
use AIMultilingual\Language\Languages;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;

$title = 'A2 Elementor Foundation Fixture';
$existing = get_page_by_title( $title, OBJECT, 'page' );
$post_id  = $existing instanceof WP_Post ? (int) $existing->ID : 0;

$payload = array(
	array(
		'id'       => 'a2sec01',
		'elType'   => 'container',
		'settings' => array(),
		'elements' => array(
			array(
				'id'         => 'a2hd01',
				'elType'     => 'widget',
				'widgetType' => 'heading',
				'settings'   => array( 'title' => 'A2 Heading Source' ),
				'elements'   => array(),
			),
			array(
				'id'         => 'a2te01',
				'elType'     => 'widget',
				'widgetType' => 'text-editor',
				'settings'   => array( 'editor' => '<p>A2 Text Editor Source</p>' ),
				'elements'   => array(),
			),
			array(
				'id'         => 'a2bt01',
				'elType'     => 'widget',
				'widgetType' => 'button',
				'settings'   => array( 'text' => 'A2 Button Source' ),
				'elements'   => array(),
			),
			array(
				'id'         => 'a2ac01',
				'elType'     => 'widget',
				'widgetType' => 'accordion',
				'settings'   => array(
					'tabs' => array(
						array(
							'tab_title'   => 'A2 Accordion Source Title',
							'tab_content' => 'A2 Accordion Source Body',
						),
					),
				),
				'elements'   => array(),
			),
		),
	),
);

if ( $post_id <= 0 ) {
	$post_id = (int) wp_insert_post(
		array(
			'post_title'   => $title,
			'post_name'    => 'a2-elementor-foundation-fixture',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		),
		true
	);
	if ( $post_id <= 0 ) {
		WP_CLI::error( 'Failed to create fixture page.' );
	}
}

update_post_meta( $post_id, Contract::META_DATA, wp_json_encode( $payload ) );
update_post_meta( $post_id, Contract::META_EDIT_MODE, 'builder' );
update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.2.1' );
update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $post_id, '_wp_page_template', 'elementor_header_footer' );

$settings = new Settings();
$current  = $settings->get();
$current['elementor_extraction_enabled']         = true;
$current['elementor_frontend_rendering_enabled'] = true;
$settings->save( $current );

$languages = new Languages( new Cache() );
$sv        = null;
foreach ( $languages->all() as $lang ) {
	if ( 'sv' === (string) ( $lang->code ?? '' ) ) {
		$sv = $lang;
		break;
	}
}
if ( null === $sv ) {
	WP_CLI::error( 'Swedish language missing.' );
}

$store = new Store( new Cache() );
$pairs = array(
	array( 'a2hd01', 'title', 'A2 Heading Source', 'A2 Rubrik Mål' ),
	array( 'a2te01', 'editor', '<p>A2 Text Editor Source</p>', '<p>A2 Textredigerare Mål</p>' ),
	array( 'a2bt01', 'text', 'A2 Button Source', 'A2 Knapp Mål' ),
);

foreach ( $pairs as [ $eid, $control, $source, $translated ] ) {
	$key = sprintf( 'e:d:%d:%s:%s', $post_id, $eid, $control );
	$ok  = $store->save_translation(
		array(
			'source_type'     => Store::SOURCE_POST,
			'source_id'       => $post_id,
			'language_id'     => (int) $sv->language_id,
			'field_key'       => Contract::FIELD_KEY,
			'segment_key'     => $key,
			'segment_kind'    => Store::KIND_FIELD,
			'source_text'     => $source,
			'translated_text' => $translated,
			'text_format'     => ( 'editor' === $control ) ? Store::FORMAT_HTML : Store::FORMAT_PLAIN,
			'status'          => Store::STATUS_MANUALLY_EDITED,
		)
	);
	if ( true !== $ok ) {
		WP_CLI::warning( 'Save failed for ' . $key );
	}
}

if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}
wp_cache_flush();

$url = get_permalink( $post_id );
WP_CLI::success(
	wp_json_encode(
		array(
			'post_id' => $post_id,
			'url'     => $url,
			'sv_url'  => trailingslashit( home_url( '/sv/' . get_page_uri( $post_id ) ) ),
			'flags'   => array(
				'extraction' => true,
				'frontend'   => true,
			),
		)
	)
);
