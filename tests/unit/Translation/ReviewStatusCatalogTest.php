<?php
/**
 * Review status catalog unit tests (R2).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation;

use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Pins the review-status catalog and clear-field contract.
 */
final class ReviewStatusCatalogTest extends TestCase {

	public function test_review_statuses_are_frozen(): void {
		$this->assertSame(
			array(
				Store::REVIEW_NOT_SUBMITTED,
				Store::REVIEW_PENDING,
				Store::REVIEW_APPROVED,
				Store::REVIEW_REJECTED,
			),
			Store::review_statuses()
		);
	}

	public function test_review_clear_fields_reset_to_not_submitted(): void {
		$fields = Store::review_clear_fields();

		$this->assertSame( Store::REVIEW_NOT_SUBMITTED, $fields['review_status'] );
		$this->assertSame( '', $fields['submitted_translation_hash'] );
		$this->assertSame( '', $fields['rejection_reason'] );
		$this->assertNull( $fields['reviewed_by'] );
		$this->assertNull( $fields['rejected_by'] );
		$this->assertNull( $fields['review_submitted_by'] );
	}

	public function test_translation_hash_determinism_for_submitted_hash(): void {
		$text = "Café\nline";
		$this->assertSame( sha1( $text ), Store::translation_hash( $text ) );
		$this->assertSame(
			Store::translation_hash( $text ),
			Store::translation_hash( $text )
		);
	}
}
