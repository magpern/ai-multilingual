<?php
/**
 * WP10 staging helper — run via: wp --user=1 eval-file acceptance/f12-staging/wp10-helper.php action ...
 *
 * @package AIMultilingual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Language\Languages;
use AIMultilingual\Rollout\RolloutAuditLogger;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Rollout\RolloutConfigurationService;
use AIMultilingual\Rollout\RolloutEmergencyService;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

$action = $args[0] ?? '';
$user_id = 1;

switch ( $action ) {
	case 'apply':
		$proposed = json_decode( $args[1] ?? '{}', true );
		if ( ! is_array( $proposed ) ) {
			fwrite( STDERR, "invalid json\n" );
			exit( 1 );
		}
		$svc    = new RolloutConfigurationService( new RolloutConfigurationRepository(), new RolloutAuditLogger() );
		$result = $svc->apply( $proposed, $user_id, 'wp10-staging' );
		if ( ! $result->valid ) {
			fwrite( STDERR, implode( ',', $result->errors ) . "\n" );
			exit( 1 );
		}
		echo (string) ( $result->config?->policy_version ?? 0 );
		break;

	case 'create_post':
		$uuid        = '550e8400-e29b-41d4-a716-446655440012';
		$slug        = 'f12-staging-rollout-test';
		$source_text = 'F12 Source Hello';
		$trans_text  = 'F12 Hej Staging';
		$block       = '<!-- wp:paragraph {"aimlBlockId":"' . $uuid . '"} --><p>' . $source_text . '</p><!-- /wp:paragraph -->';

		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			wp_delete_post( (int) $existing->ID, true );
		}

		$post_id = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'F12 Staging Rollout Test',
				'post_name'    => $slug,
				'post_content' => $block,
			)
		);

		$sv = null;
		foreach ( ( new Languages( new Cache() ) )->all() as $language ) {
			if ( 'sv' === $language->code ) {
				$sv = $language;
				break;
			}
		}

		if ( null === $sv ) {
			fwrite( STDERR, "sv language missing\n" );
			exit( 1 );
		}

		( new Store( new Cache() ) )->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => $post_id,
				'source_subtype'  => 'page',
				'language_id'     => (int) $sv->language_id,
				'field_key'       => Extractor::FIELD_CONTENT,
				'segment_key'     => SegmentKey::build( $uuid, Contract::FIELD_CONTENT ),
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => '<p>' . $source_text . '</p>',
				'translated_text' => $trans_text,
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		echo (string) $post_id;
		break;

	case 'create_other':
		$post_id = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'F12 Other',
				'post_name'    => 'f12-staging-other',
				'post_content' => '<!-- wp:paragraph {"aimlBlockId":"550e8400-e29b-41d4-a716-446655440013"} --><p>Other F12 Source Hello</p><!-- /wp:paragraph -->',
			)
		);
		echo (string) $post_id;
		break;

	case 'emergency_stop':
		$result = ( new RolloutEmergencyService( new RolloutConfigurationRepository(), new RolloutAuditLogger() ) )->stop( $user_id, 'wp10-staging' );
		if ( ! $result->valid ) {
			fwrite( STDERR, implode( ',', $result->errors ) . "\n" );
			exit( 1 );
		}
		echo 'ok';
		break;

	case 'set_frontend_render':
		$enabled = ( '1' === ( $args[1] ?? '0' ) );
		$data    = ( new Settings() )->get();
		$data['block_frontend_rendering_enabled'] = $enabled;
		update_option( Settings::OPTION, $data );
		echo $enabled ? '1' : '0';
		break;

	case 'restore_snapshot':
		$repo     = new RolloutConfigurationRepository();
		$versions = $repo->list_snapshot_versions();
		if ( array() === $versions ) {
			echo 'none';
			break;
		}
		$result = $repo->restore( (int) $versions[0], $user_id );
		if ( ! $result->valid ) {
			fwrite( STDERR, implode( ',', $result->errors ) . "\n" );
			exit( 1 );
		}
		echo (string) ( $result->config?->policy_version ?? 0 );
		break;

	case 'policy_version':
		echo (string) ( new RolloutConfigurationRepository() )->get()->policy_version;
		break;

	case 'delete_post':
		wp_delete_post( (int) ( $args[1] ?? 0 ), true );
		echo 'ok';
		break;

	default:
		fwrite( STDERR, "unknown action\n" );
		exit( 1 );
}
