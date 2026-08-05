<?php
/**
 * F14 adapter admission helper — wp --user=1 eval-file ... action ...
 *
 * @package AIMultilingual
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Language\Languages;
use AIMultilingual\Rollout\RolloutAuditLogger;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Rollout\RolloutConfigurationService;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;

$action  = $args[0] ?? '';
$user_id = 1;

/**
 * Block fixture definitions for F14 leaf adapters.
 *
 * @return array<string, array{block_name: string, uuid: string, slug: string, source_html: string, source_text: string, trans_text: string, content: string}>
 */
function aiml_f14_fixtures(): array {
	return array(
		'list-item'     => array(
			'block_name'  => 'core/list-item',
			'uuid'        => '550e8400-e29b-41d4-a716-4466554400d1',
			'slug'        => 'f14-admit-list-item',
			'source_text' => 'F14 List Item Source',
			'trans_text'  => 'F14 List Item Hej',
			'source_html' => '<li>F14 List Item Source</li>',
			'content'     => '<!-- wp:list --><!-- wp:list-item {"aimlBlockId":"550e8400-e29b-41d4-a716-4466554400d1"} --><li>F14 List Item Source</li><!-- /wp:list-item --><!-- /wp:list -->',
		),
		'preformatted'  => array(
			'block_name'  => 'core/preformatted',
			'uuid'        => '550e8400-e29b-41d4-a716-4466554400d2',
			'slug'        => 'f14-admit-preformatted',
			'source_text' => 'F14 Preformatted Source',
			'trans_text'  => 'F14 Preformatted Hej',
			'source_html' => '<pre class="wp-block-preformatted">F14 Preformatted Source</pre>',
			'content'     => '<!-- wp:preformatted {"aimlBlockId":"550e8400-e29b-41d4-a716-4466554400d2"} --><pre class="wp-block-preformatted">F14 Preformatted Source</pre><!-- /wp:preformatted -->',
		),
		'verse'         => array(
			'block_name'  => 'core/verse',
			'uuid'        => '550e8400-e29b-41d4-a716-4466554400d3',
			'slug'        => 'f14-admit-verse',
			'source_text' => 'F14 Verse Source',
			'trans_text'  => 'F14 Verse Hej',
			'source_html' => '<pre class="wp-block-verse">F14 Verse Source</pre>',
			'content'     => '<!-- wp:verse {"aimlBlockId":"550e8400-e29b-41d4-a716-4466554400d3"} --><pre class="wp-block-verse">F14 Verse Source</pre><!-- /wp:verse -->',
		),
		'code'          => array(
			'block_name'  => 'core/code',
			'uuid'        => '550e8400-e29b-41d4-a716-4466554400d4',
			'slug'        => 'f14-admit-code',
			'source_text' => 'F14 Code Source',
			'trans_text'  => 'F14 Code Hej',
			'source_html' => '<pre class="wp-block-code"><code>F14 Code Source</code></pre>',
			'content'     => '<!-- wp:code {"aimlBlockId":"550e8400-e29b-41d4-a716-4466554400d4"} --><pre class="wp-block-code"><code>F14 Code Source</code></pre><!-- /wp:code -->',
		),
	);
}

switch ( $action ) {
	case 'apply':
		$proposed = json_decode( $args[1] ?? '{}', true );
		if ( ! is_array( $proposed ) ) {
			fwrite( STDERR, "invalid json\n" );
			exit( 1 );
		}
		$svc    = new RolloutConfigurationService( new RolloutConfigurationRepository(), new RolloutAuditLogger() );
		$result = $svc->apply( $proposed, $user_id, 'f14-staging' );
		if ( ! $result->valid ) {
			fwrite( STDERR, implode( ',', $result->errors ) . "\n" );
			exit( 1 );
		}
		echo (string) ( $result->config?->policy_version ?? 0 );
		break;

	case 'supported_blocks':
		echo wp_json_encode( BlockRegistry::SUPPORTED_BLOCKS );
		break;

	case 'create_adapter_post':
		$key      = $args[1] ?? '';
		$fixtures = aiml_f14_fixtures();
		if ( ! isset( $fixtures[ $key ] ) ) {
			fwrite( STDERR, "unknown fixture\n" );
			exit( 1 );
		}
		$fx = $fixtures[ $key ];

		$existing = get_page_by_path( $fx['slug'], OBJECT, 'page' );
		if ( $existing ) {
			wp_delete_post( (int) $existing->ID, true );
		}

		$post_id = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'F14 Admit ' . $key,
				'post_name'    => $fx['slug'],
				'post_content' => $fx['content'],
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
				'segment_key'     => SegmentKey::build( $fx['uuid'], Contract::FIELD_CONTENT ),
				'segment_kind'    => Store::KIND_BLOCK,
				'text_format'     => Store::FORMAT_HTML,
				'source_text'     => $fx['source_html'],
				'translated_text' => $fx['trans_text'],
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		echo wp_json_encode(
			array(
				'post_id'     => $post_id,
				'slug'        => $fx['slug'],
				'source_text' => $fx['source_text'],
				'trans_text'  => $fx['trans_text'],
				'block_name'  => $fx['block_name'],
			)
		);
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
