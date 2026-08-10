<?php
/**
 * TI.7 Store overlay eligibility and edit invalidation (unit).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation;

use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Publication gate helper without WordPress bootstrap (override gate flag).
 */
final class PublicationOverlayEligibilityTest extends TestCase {

	public function test_gate_off_allows_non_empty_non_ignored(): void {
		$row = (object) array(
			'status'          => Store::STATUS_MACHINE_TRANSLATED,
			'translated_text' => 'Hej',
			'publish_status'  => Store::PUBLISH_UNPUBLISHED,
		);

		$this->assertTrue( Store::is_publicly_overlay_eligible( $row, false ) );
	}

	public function test_gate_on_requires_published(): void {
		$row = (object) array(
			'status'          => Store::STATUS_MACHINE_TRANSLATED,
			'translated_text' => 'Hej',
			'publish_status'  => Store::PUBLISH_UNPUBLISHED,
		);

		$this->assertFalse( Store::is_publicly_overlay_eligible( $row, true ) );

		$row->publish_status = Store::PUBLISH_PUBLISHED;
		$this->assertTrue( Store::is_publicly_overlay_eligible( $row, true ) );
	}

	public function test_ignored_and_missing_never_overlay(): void {
		foreach ( array( Store::STATUS_IGNORED, Store::STATUS_MISSING ) as $status ) {
			$row = (object) array(
				'status'          => $status,
				'translated_text' => 'x',
				'publish_status'  => Store::PUBLISH_PUBLISHED,
			);
			$this->assertFalse( Store::is_publicly_overlay_eligible( $row, true ) );
			$this->assertFalse( Store::is_publicly_overlay_eligible( $row, false ) );
		}
	}

	public function test_empty_text_never_overlay(): void {
		$row = (object) array(
			'status'          => Store::STATUS_MACHINE_TRANSLATED,
			'translated_text' => '',
			'publish_status'  => Store::PUBLISH_PUBLISHED,
		);
		$this->assertFalse( Store::is_publicly_overlay_eligible( $row, true ) );
	}

	public function test_publish_clear_fields(): void {
		$fields = Store::publish_clear_fields();
		$this->assertSame( Store::PUBLISH_UNPUBLISHED, $fields['publish_status'] );
		$this->assertNull( $fields['published_at'] );
		$this->assertNull( $fields['published_by'] );
	}
}
