<?php
/**
 * Review audit logger unit tests (ADR-0015 §12).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Workspace\Review;

use AIMultilingual\Workspace\Review\ReviewAuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * Audit payloads must never retain translation content or full reasons.
 */
final class ReviewAuditLoggerTest extends TestCase {

	/**
	 * Disallowed content keys are stripped; safe keys survive.
	 */
	public function test_sanitize_strips_translation_content(): void {
		$logger = new ReviewAuditLogger();
		$clean  = $logger->sanitize_payload(
			array(
				'post_id'                    => 42,
				'segment_key'                => 'f:post_title',
				'language_id'                => 2,
				'old_review_status'          => 'not_submitted',
				'new_review_status'          => 'pending',
				'user_id'                    => 7,
				'source_surface'             => 'workspace',
				'submitted_hash_fingerprint' => 'abcd1234',
				'reason_present'             => true,
				'reason_length'              => 42,
				'translated_text'            => 'secret translation body',
				'source_text'                => 'secret source body',
				'rejection_reason'           => 'the full reason text',
			)
		);

		$this->assertSame( 42, $clean['post_id'] );
		$this->assertSame( 'f:post_title', $clean['segment_key'] );
		$this->assertSame( 2, $clean['language_id'] );
		$this->assertSame( 'not_submitted', $clean['old_review_status'] );
		$this->assertSame( 'pending', $clean['new_review_status'] );
		$this->assertSame( 7, $clean['user_id'] );
		$this->assertSame( 'workspace', $clean['source_surface'] );
		$this->assertSame( 'abcd1234', $clean['submitted_hash_fingerprint'] );
		$this->assertTrue( $clean['reason_present'] );
		$this->assertSame( 42, $clean['reason_length'] );

		$this->assertArrayNotHasKey( 'translated_text', $clean );
		$this->assertArrayNotHasKey( 'source_text', $clean );
		$this->assertArrayNotHasKey( 'rejection_reason', $clean );
		$this->assertArrayHasKey( 'timestamp', $clean );
	}

	/**
	 * Hash fingerprints are truncated to a short, non-reversible prefix.
	 */
	public function test_hash_fingerprint_truncates_to_eight_chars(): void {
		$full = str_repeat( 'a', 40 );

		$this->assertSame( str_repeat( 'a', 8 ), ReviewAuditLogger::hash_fingerprint( $full ) );
		$this->assertSame( '', ReviewAuditLogger::hash_fingerprint( '' ) );
	}

	/**
	 * Logging without WordPress loaded is a safe no-op (no do_action).
	 */
	public function test_log_without_wordpress_is_a_safe_noop(): void {
		$logger = new ReviewAuditLogger();

		$logger->log( 'review_submitted', array( 'post_id' => 1 ) );

		$this->addToAssertionCount( 1 );
	}
}
