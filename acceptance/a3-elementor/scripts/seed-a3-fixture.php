<?php
/**
 * A.3 acceptance fixture: Accordion/Toggle/Image/Icon List/CTA + A.2 controls + SV overlays.
 *
 * Usage: wp eval-file wp-content/plugins/universal-multilingual/acceptance/a3-elementor/scripts/seed-a3-fixture.php
 *
 * @package AIMultilingual
 */

use AIMultilingual\Cache\Cache;
use AIMultilingual\Elementor\Contract;
use AIMultilingual\Language\Languages;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;

/**
 * Seeds the A.3 Elementor coverage fixture page.
 */
function aiml_a3_seed_elementor_fixture(): void {
	$title   = 'A3 Elementor Widget Coverage Fixture';
	$query   = new \WP_Query(
		array(
			'post_type'              => 'page',
			'title'                  => $title,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	$post_id = ( $query->have_posts() ) ? (int) $query->posts[0]->ID : 0;

	$payload = array(
		array(
			'id'       => 'a3sec01',
			'elType'   => 'container',
			'settings' => array(),
			'elements' => array(
				array(
					'id'         => 'a3hd01',
					'elType'     => 'widget',
					'widgetType' => 'heading',
					'settings'   => array( 'title' => 'A3 Heading Source' ),
					'elements'   => array(),
				),
				array(
					'id'         => 'a3te01',
					'elType'     => 'widget',
					'widgetType' => 'text-editor',
					'settings'   => array( 'editor' => '<p>A3 Text Editor Source</p>' ),
					'elements'   => array(),
				),
				array(
					'id'         => 'a3bt01',
					'elType'     => 'widget',
					'widgetType' => 'button',
					'settings'   => array( 'text' => 'A3 Button Source' ),
					'elements'   => array(),
				),
				array(
					'id'         => 'a3ac01',
					'elType'     => 'widget',
					'widgetType' => 'accordion',
					'settings'   => array(
						'tabs' => array(
							array(
								'_id'         => 'accrow1',
								'tab_title'   => 'A3 Accordion Title One',
								'tab_content' => '<p>A3 Accordion Body One</p>',
							),
							array(
								'_id'         => 'accrow2',
								'tab_title'   => 'A3 Accordion Title Two',
								'tab_content' => '<p>A3 Accordion Body Two</p>',
							),
						),
					),
					'elements'   => array(),
				),
				array(
					'id'         => 'a3tg01',
					'elType'     => 'widget',
					'widgetType' => 'toggle',
					'settings'   => array(
						'tabs' => array(
							array(
								'_id'         => 'togrow1',
								'tab_title'   => 'A3 Toggle Title One',
								'tab_content' => '<p>A3 Toggle Body One</p>',
							),
							array(
								'_id'         => 'togrow2',
								'tab_title'   => 'A3 Toggle Title Two',
								'tab_content' => '<p>A3 Toggle Body Two</p>',
							),
						),
					),
					'elements'   => array(),
				),
				array(
					'id'         => 'a3im01',
					'elType'     => 'widget',
					'widgetType' => 'image',
					'settings'   => array(
						'caption_source' => 'custom',
						'caption'        => 'A3 Image Custom Caption',
						'image'          => array(
							'url' => 'https://dev.biopentra.eu/wp-content/uploads/2024/01/placeholder.png',
							'id'  => 0,
						),
					),
					'elements'   => array(),
				),
				array(
					'id'         => 'a3il01',
					'elType'     => 'widget',
					'widgetType' => 'icon-list',
					'settings'   => array(
						'icon_list' => array(
							array(
								'_id'  => 'ilrow1',
								'text' => 'A3 Icon List Item One',
							),
							array(
								'_id'  => 'ilrow2',
								'text' => 'A3 Icon List Item Two',
							),
						),
					),
					'elements'   => array(),
				),
				array(
					'id'         => 'a3ct01',
					'elType'     => 'widget',
					'widgetType' => 'call-to-action',
					'settings'   => array(
						'title'       => 'A3 CTA Title',
						'description' => 'A3 CTA Description',
						'button'      => 'A3 CTA Button',
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
				'post_name'    => 'a3-elementor-widget-coverage-fixture',
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
	update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.2.2' );
	update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $post_id, '_wp_page_template', 'elementor_header_footer' );
	delete_post_meta( $post_id, '_elementor_element_cache' );

	$settings                                = new Settings();
	$current                                 = $settings->get();
	$current['elementor_extraction_enabled'] = true;
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

	$flat = array(
		array( 'a3hd01', 'title', 'A3 Heading Source', 'A3 Rubrik Mål', 'plain' ),
		array( 'a3te01', 'editor', '<p>A3 Text Editor Source</p>', '<p>A3 Textredigerare Mål</p>', 'html' ),
		array( 'a3bt01', 'text', 'A3 Button Source', 'A3 Knapp Mål', 'plain' ),
		array( 'a3im01', 'caption', 'A3 Image Custom Caption', 'A3 Bildtext Mål', 'plain' ),
		array( 'a3ct01', 'title', 'A3 CTA Title', 'A3 CTA Rubrik', 'plain' ),
		array( 'a3ct01', 'description', 'A3 CTA Description', 'A3 CTA Beskrivning', 'plain' ),
		array( 'a3ct01', 'button', 'A3 CTA Button', 'A3 CTA Knapp', 'plain' ),
	);

	foreach ( $flat as [ $eid, $control, $source, $translated, $format ] ) {
		$key = sprintf( 'e:d:%d:%s:%s', $post_id, $eid, $control );
		$store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_FIELD,
				'source_text'     => $source,
				'translated_text' => $translated,
				'text_format'     => 'html' === $format ? Store::FORMAT_HTML : Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);
	}

	$nested = array(
		array( 'a3ac01', 'tab_title', 'accrow1', 'A3 Accordion Title One', 'A3 Dragspel Rubrik Ett', 'plain' ),
		array( 'a3ac01', 'tab_content', 'accrow1', '<p>A3 Accordion Body One</p>', '<p>A3 Dragspel Kropp Ett</p>', 'html' ),
		array( 'a3ac01', 'tab_title', 'accrow2', 'A3 Accordion Title Two', 'A3 Dragspel Rubrik Två', 'plain' ),
		array( 'a3ac01', 'tab_content', 'accrow2', '<p>A3 Accordion Body Two</p>', '<p>A3 Dragspel Kropp Två</p>', 'html' ),
		array( 'a3tg01', 'tab_title', 'togrow1', 'A3 Toggle Title One', 'A3 Växel Rubrik Ett', 'plain' ),
		array( 'a3tg01', 'tab_content', 'togrow1', '<p>A3 Toggle Body One</p>', '<p>A3 Växel Kropp Ett</p>', 'html' ),
		array( 'a3tg01', 'tab_title', 'togrow2', 'A3 Toggle Title Two', 'A3 Växel Rubrik Två', 'plain' ),
		array( 'a3tg01', 'tab_content', 'togrow2', '<p>A3 Toggle Body Two</p>', '<p>A3 Växel Kropp Två</p>', 'html' ),
		array( 'a3il01', 'text', 'ilrow1', 'A3 Icon List Item One', 'A3 Ikonlista Ett', 'plain' ),
		array( 'a3il01', 'text', 'ilrow2', 'A3 Icon List Item Two', 'A3 Ikonlista Två', 'plain' ),
	);

	foreach ( $nested as [ $eid, $control, $nid, $source, $translated, $format ] ) {
		$key = sprintf( 'e:d:%d:%s:%s:%s', $post_id, $eid, $control, $nid );
		$store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Contract::FIELD_KEY,
				'segment_key'     => $key,
				'segment_kind'    => Store::KIND_FIELD,
				'source_text'     => $source,
				'translated_text' => $translated,
				'text_format'     => 'html' === $format ? Store::FORMAT_HTML : Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);
	}

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	wp_cache_flush();

	WP_CLI::success(
		wp_json_encode(
			array(
				'post_id' => $post_id,
				'url'     => get_permalink( $post_id ),
				'sv_url'  => trailingslashit( home_url( '/sv/' . get_page_uri( $post_id ) ) ),
			)
		)
	);
}

aiml_a3_seed_elementor_fixture();
