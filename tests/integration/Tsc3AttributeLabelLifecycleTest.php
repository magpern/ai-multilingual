<?php
/**
 * Integration tests for TSC.3 rehost + writer denial (DB-backed).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Database\Schema;
use AIMultilingual\Integration\WooCommerce\AttributeLabelIdentity;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Operator\AllowedActionsResolver;

/**
 * @group tsc3
 */
final class Tsc3AttributeLabelLifecycleTest extends AimlTestCase {

	public function test_rehost_moves_canonical_attribute_label_rows(): void {
		global $wpdb;

		$store = new Store( new Cache() );
		$table = Schema::translations();
		$now   = current_time( 'mysql', true );
		$key   = 'p:woocommerce:attribute:7:label';
		$hash  = Store::segment_hash( 'label', $key );

		$wpdb->insert(
			$table,
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => 100,
				'source_subtype'  => 'page',
				'language_id'     => 2,
				'field_key'       => 'label',
				'segment_key'     => $key,
				'segment_hash'    => $hash,
				'segment_kind'    => Store::KIND_BLOCK,
				'segment_order'   => 0,
				'text_format'     => Store::FORMAT_PLAIN,
				'source_text'     => 'Color',
				'source_hash'     => Store::source_hash( 'Color', Store::FORMAT_PLAIN ),
				'translated_text' => 'Färg',
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'review_status'   => Store::REVIEW_APPROVED,
				'publish_status'  => Store::PUBLISH_PUBLISHED,
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);

		$stats = $store->rehost_segments(
			Store::SOURCE_POST,
			100,
			200,
			array( AttributeLabelIdentity::class, 'rehost_predicate' )
		);
		$this->assertSame( 1, $stats['moved'] );

		$row = $store->get( Store::SOURCE_POST, 200, 2, $key );
		$this->assertNotNull( $row );
		$this->assertSame( 'Färg', (string) $row->translated_text );
		$this->assertSame( Store::REVIEW_APPROVED, (string) $row->review_status );
		$this->assertNull( $store->get( Store::SOURCE_POST, 100, 2, $key ) );
	}

	public function test_taxonomy_compat_write_denied(): void {
		$row = (object) array(
			'source_type' => Store::SOURCE_POST,
			'source_id'   => 10,
			'segment_key' => 'p:woocommerce:product:10:attribute_name:pa_color',
		);
		$this->assertTrue( AllowedActionsResolver::denies_row_write( $row ) );
	}

	public function test_local_attribute_not_write_denied(): void {
		$row = (object) array(
			'source_type' => Store::SOURCE_POST,
			'source_id'   => 10,
			'segment_key' => 'p:woocommerce:product:10:attribute_name:material',
		);
		$this->assertFalse( AllowedActionsResolver::denies_row_write( $row ) );
	}
}
