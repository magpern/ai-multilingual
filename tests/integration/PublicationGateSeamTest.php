<?php
/**
 * TI.7 publication gate seam coverage (integration).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Settings;
use AIMultilingual\Translation\Store;

/**
 * Ensures unpublished translations cannot leak when the gate is enabled.
 */
final class PublicationGateSeamTest extends AimlTestCase {

	protected function setUp(): void {
		parent::setUp();
		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array_merge(
					Settings::defaults(),
					array( 'segment_publication_gate_enabled' => true )
				)
			)
		);
	}

	public function test_translated_value_respects_gate(): void {
		$language = $this->languages->default();
		$this->assertNotNull( $language );
		$lang_id = (int) $language->language_id;

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => 9201,
				'language_id'     => $lang_id,
				'field_key'       => 'post_title',
				'segment_key'     => 'gate-seam-title',
				'source_text'     => 'Hello',
				'translated_text' => 'Hej',
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
			)
		);

		$this->assertNull(
			$this->store->translated_value( Store::SOURCE_POST, 9201, $lang_id, 'gate-seam-title' )
		);

		$this->store->update_publish_metadata(
			Store::SOURCE_POST,
			9201,
			$lang_id,
			'gate-seam-title',
			array(
				'publish_status' => Store::PUBLISH_PUBLISHED,
				'published_at'   => current_time( 'mysql', true ),
				'published_by'   => 1,
			)
		);

		$this->assertSame(
			'Hej',
			$this->store->translated_value( Store::SOURCE_POST, 9201, $lang_id, 'gate-seam-title' )
		);
	}

	public function test_edit_invalidates_publication(): void {
		$language = $this->languages->default();
		$this->assertNotNull( $language );
		$lang_id = (int) $language->language_id;

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => 9202,
				'language_id'     => $lang_id,
				'field_key'       => 'post_title',
				'segment_key'     => 'gate-edit-title',
				'source_text'     => 'Hello',
				'translated_text' => 'Hej',
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
			)
		);
		$this->store->update_publish_metadata(
			Store::SOURCE_POST,
			9202,
			$lang_id,
			'gate-edit-title',
			array(
				'publish_status' => Store::PUBLISH_PUBLISHED,
				'published_at'   => current_time( 'mysql', true ),
				'published_by'   => 1,
			)
		);

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => 9202,
				'language_id'     => $lang_id,
				'field_key'       => 'post_title',
				'segment_key'     => 'gate-edit-title',
				'source_text'     => 'Hello',
				'translated_text' => 'Hejsan',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$row = $this->store->get( Store::SOURCE_POST, 9202, $lang_id, 'gate-edit-title' );
		$this->assertNotNull( $row );
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, (string) $row->publish_status );
	}

	public function test_gate_off_preserves_legacy_overlay(): void {
		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array_merge(
					Settings::defaults(),
					array( 'segment_publication_gate_enabled' => false )
				)
			)
		);

		$language = $this->languages->default();
		$this->assertNotNull( $language );
		$lang_id = (int) $language->language_id;

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => 9203,
				'language_id'     => $lang_id,
				'field_key'       => 'post_title',
				'segment_key'     => 'gate-off-title',
				'source_text'     => 'Hello',
				'translated_text' => 'Hej',
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
			)
		);

		$this->assertSame(
			'Hej',
			$this->store->translated_value( Store::SOURCE_POST, 9203, $lang_id, 'gate-off-title' )
		);
	}
}
