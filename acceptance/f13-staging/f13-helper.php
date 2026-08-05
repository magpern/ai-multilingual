<?php
/**
 * F13 GA staging helper — wp --user=1 eval-file ... action ...
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
use AIMultilingual\Rollout\RolloutPromotionService;
use AIMultilingual\Settings;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

$action  = $args[0] ?? '';
$user_id = 1;

switch ( $action ) {
	case 'apply':
		$proposed = json_decode( $args[1] ?? '{}', true );
		if ( ! is_array( $proposed ) ) {
			fwrite( STDERR, "invalid json\n" );
			exit( 1 );
		}
		$svc    = new RolloutConfigurationService( new RolloutConfigurationRepository(), new RolloutAuditLogger() );
		$result = $svc->apply( $proposed, $user_id, 'f13-staging' );
		if ( ! $result->valid ) {
			fwrite( STDERR, implode( ',', $result->errors ) . "\n" );
			exit( 1 );
		}
		echo (string) ( $result->config?->policy_version ?? 0 );
		break;

	case 'promote':
		$stage  = (int) ( $args[1] ?? -1 );
		$result = ( new RolloutPromotionService( new RolloutConfigurationRepository(), new RolloutAuditLogger() ) )
			->promote( $stage, $user_id, 'f13-staging' );
		if ( ! $result->valid ) {
			fwrite( STDERR, implode( ',', $result->errors ) . "\n" );
			exit( 1 );
		}
		echo (string) ( $result->config?->rollout_stage ?? -1 );
		break;

	case 'create_ga_post':
		$suffix = $args[1] ?? 'a';
		$map    = array(
			'a' => array(
				'uuid' => '550e8400-e29b-41d4-a716-4466554400a1',
				'slug' => 'f13-ga-staging-a',
			),
			'b' => array(
				'uuid' => '550e8400-e29b-41d4-a716-4466554400b2',
				'slug' => 'f13-ga-staging-b',
			),
		);
		if ( ! isset( $map[ $suffix ] ) ) {
			fwrite( STDERR, "unknown suffix\n" );
			exit( 1 );
		}
		$uuid        = $map[ $suffix ]['uuid'];
		$slug        = $map[ $suffix ]['slug'];
		$source_text = 'F13 GA Source ' . $suffix;
		$trans_text  = 'F13 GA Hej ' . $suffix;
		$block       = '<!-- wp:paragraph {"aimlBlockId":"' . $uuid . '"} --><p>' . $source_text . '</p><!-- /wp:paragraph -->';

		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			wp_delete_post( (int) $existing->ID, true );
		}

		$post_id = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'F13 GA Staging ' . $suffix,
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

		echo wp_json_encode(
			array(
				'post_id'     => $post_id,
				'slug'        => $slug,
				'source_text' => $source_text,
				'trans_text'  => $trans_text,
			)
		);
		break;

	case 'emergency_stop':
		$result = ( new RolloutEmergencyService( new RolloutConfigurationRepository(), new RolloutAuditLogger() ) )->stop( $user_id, 'f13-staging' );
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

	case 'policy_version':
		echo (string) ( new RolloutConfigurationRepository() )->get()->policy_version;
		break;

	case 'export_config':
		echo wp_json_encode( ( new RolloutConfigurationRepository() )->export() );
		break;

	case 'restore_snapshot':
		$repo    = new RolloutConfigurationRepository();
		$current = $repo->get();
		$target  = max( 1, $current->policy_version - 1 );
		$result  = $repo->restore( $target, $user_id );
		if ( ! $result->valid ) {
			fwrite( STDERR, implode( ',', $result->errors ) . "\n" );
			exit( 1 );
		}
		echo (string) ( $result->config?->policy_version ?? 0 );
		break;

	case 'supported_blocks':
		echo wp_json_encode( \AIMultilingual\Block\BlockRegistry::SUPPORTED_BLOCKS );
		break;

	case 'delete_post':
		$id = (int) ( $args[1] ?? 0 );
		if ( $id > 0 ) {
			wp_delete_post( $id, true );
		}
		echo 'ok';
		break;

	default:
		fwrite( STDERR, "unknown action\n" );
		exit( 1 );
}
