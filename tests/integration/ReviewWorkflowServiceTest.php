<?php
/**
 * ReviewWorkflowService integration tests (R3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Review\ReviewWorkflowException;
use AIMultilingual\Workspace\Review\ReviewWorkflowService;

/**
 * Transition matrix and concurrency behaviour for review workflow.
 */
final class ReviewWorkflowServiceTest extends AimlTestCase {

	private ReviewWorkflowService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new ReviewWorkflowService( $this->store );
	}

	public function test_submit_from_not_submitted_sets_pending_metadata(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Submit me', 'Skicka mig' );

		$row = $this->service->submit(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			42
		);

		$this->assertSame( Store::REVIEW_PENDING, $row->review_status );
		$this->assertSame( Store::translation_hash( 'Skicka mig' ), $row->submitted_translation_hash );
		$this->assertSame( 42, $row->review_submitted_by );
		$this->assertNotEmpty( $row->review_submitted_at );
		$this->assertNull( $row->reviewed_by );
		$this->assertSame( '', $row->rejection_reason );
		$this->assertSame( 'Skicka mig', $row->translated_text );
		$this->assertSame( Store::STATUS_MANUALLY_EDITED, $row->status );
	}

	public function test_submit_resubmit_from_rejected_clears_rejection_fields(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Resubmit', 'Om' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
		$this->service->reject(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			2,
			'Fix terminology'
		);

		$row = $this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 3 );

		$this->assertSame( Store::REVIEW_PENDING, $row->review_status );
		$this->assertSame( 3, $row->review_submitted_by );
		$this->assertSame( '', $row->rejection_reason );
		$this->assertNull( $row->rejected_by );
	}

	public function test_submit_duplicate_pending_with_unchanged_hash_is_already_pending(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Dup', 'Dubbel' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );

		try {
			$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_ALREADY_PENDING, $exception->get_error_code() );
			$this->assertSame( Store::REVIEW_PENDING, $exception->get_context()['review_status'] );
		}
	}

	public function test_submit_from_approved_is_invalid_transition(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Approved', 'Godkänd' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
		$this->service->approve( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 2 );

		$this->expectException( ReviewWorkflowException::class );
		$this->expectExceptionMessage( 'Approved translations must be edited' );

		try {
			$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_INVALID_TRANSITION, $exception->get_error_code() );
			throw $exception;
		}
	}

	public function test_submit_missing_segment_is_no_translation(): void {
		$language = $this->add_language();

		$this->expectException( ReviewWorkflowException::class );

		try {
			$this->service->submit( Store::SOURCE_POST, 99999, (int) $language->language_id, 'post_title', 1 );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_NO_TRANSLATION, $exception->get_error_code() );
			throw $exception;
		}
	}

	public function test_submit_empty_translation_is_invalid_translation(): void {
		$language = $this->add_language();
		$post_id  = $this->factory()->post->create( array( 'post_title' => 'Empty' ) );

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Empty',
				'translated_text' => '   ',
			)
		);

		try {
			$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_INVALID_TRANSLATION, $exception->get_error_code() );
		}
	}

	public function test_submit_failed_status_is_invalid_translation(): void {
		$language = $this->add_language();
		$post_id  = $this->factory()->post->create( array( 'post_title' => 'Failed' ) );

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Failed',
				'translated_text' => 'Misslyckades',
				'status'          => Store::STATUS_FAILED,
			)
		);

		try {
			$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_INVALID_TRANSLATION, $exception->get_error_code() );
		}
	}

	public function test_submit_expected_review_status_mismatch_is_conflict(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Conflict', 'Konflikt' );

		try {
			$this->service->submit(
				Store::SOURCE_POST,
				$post_id,
				(int) $language->language_id,
				'post_title',
				1,
				Store::REVIEW_PENDING
			);
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_CONFLICT, $exception->get_error_code() );
			$this->assertSame( Store::REVIEW_NOT_SUBMITTED, $exception->get_context()['review_status'] );
			$this->assertSame( 409, $exception->getCode() );
		}
	}

	public function test_approve_pending_sets_approved_without_changing_content(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Approve', 'Godkänn' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
		$row = $this->service->approve( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 9 );

		$this->assertSame( Store::REVIEW_APPROVED, $row->review_status );
		$this->assertSame( 9, $row->reviewed_by );
		$this->assertNotEmpty( $row->reviewed_at );
		$this->assertSame( 'Godkänn', $row->translated_text );
		$this->assertSame( Store::STATUS_MANUALLY_EDITED, $row->status );
	}

	public function test_approve_idempotent_when_already_approved(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Idem', 'Samma' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
		$first  = $this->service->approve( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 2 );
		$second = $this->service->approve( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 99 );

		$this->assertSame( Store::REVIEW_APPROVED, $second->review_status );
		$this->assertSame( $first->reviewed_by, $second->reviewed_by );
		$this->assertSame( $first->reviewed_at, $second->reviewed_at );
	}

	public function test_approve_not_pending_is_error(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Not pending', 'Inte väntande' );

		try {
			$this->service->approve( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_NOT_PENDING, $exception->get_error_code() );
		}
	}

	public function test_approve_stale_submitted_hash_while_pending_is_conflict(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Stale', 'Gammal' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );

		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			array(
				'submitted_translation_hash' => 'stalehash0000000000000000000000000000',
			)
		);

		try {
			$this->service->approve( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 2 );
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_CONFLICT, $exception->get_error_code() );
			$this->assertSame( ReviewWorkflowService::CODE_TRANSLATION_CHANGED, $exception->get_context()['reason'] );
			$this->assertSame( 409, $exception->getCode() );
		}
	}

	public function test_approve_after_edit_invalidates_to_not_pending(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Edited', 'Redigerad' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Edited',
				'translated_text' => 'Ny text',
			)
		);

		try {
			$this->service->approve( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 2 );
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_NOT_PENDING, $exception->get_error_code() );
		}
	}

	public function test_approve_client_submitted_hash_mismatch_is_conflict(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Client', 'Klient' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );

		try {
			$this->service->approve(
				Store::SOURCE_POST,
				$post_id,
				(int) $language->language_id,
				'post_title',
				2,
				Store::REVIEW_PENDING,
				'deadbeef'
			);
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_CONFLICT, $exception->get_error_code() );
			$this->assertSame( ReviewWorkflowService::CODE_TRANSLATION_CHANGED, $exception->get_context()['reason'] );
		}
	}

	public function test_reject_pending_sets_rejected_preserving_content(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Reject', 'Avvisa' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
		$row = $this->service->reject(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			5,
			'Needs glossary terms'
		);

		$this->assertSame( Store::REVIEW_REJECTED, $row->review_status );
		$this->assertSame( 'Needs glossary terms', $row->rejection_reason );
		$this->assertSame( 5, $row->rejected_by );
		$this->assertNull( $row->reviewed_by );
		$this->assertSame( 'Avvisa', $row->translated_text );
	}

	public function test_reject_idempotent_with_same_reason_and_hash(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Reject idem', 'Avvisa idem' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );
		$first  = $this->service->reject(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			2,
			'Same reason'
		);
		$second = $this->service->reject(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			99,
			'Same reason'
		);

		$this->assertSame( $first->rejected_by, $second->rejected_by );
		$this->assertSame( $first->rejected_at, $second->rejected_at );
	}

	public function test_reject_empty_reason_is_required(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Reason', 'Orsak' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );

		try {
			$this->service->reject(
				Store::SOURCE_POST,
				$post_id,
				(int) $language->language_id,
				'post_title',
				2,
				'   '
			);
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_REASON_REQUIRED, $exception->get_error_code() );
		}
	}

	public function test_reject_too_long_reason_is_required(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Long', 'Lång' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );

		try {
			$this->service->reject(
				Store::SOURCE_POST,
				$post_id,
				(int) $language->language_id,
				'post_title',
				2,
				str_repeat( 'x', ReviewWorkflowService::REASON_MAX_LEN + 1 )
			);
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_REASON_REQUIRED, $exception->get_error_code() );
		}
	}

	public function test_reject_stale_submitted_hash_is_conflict(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Reject stale', 'Gammal avvisning' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );

		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			array(
				'submitted_translation_hash' => 'stalehash0000000000000000000000000000',
			)
		);

		try {
			$this->service->reject(
				Store::SOURCE_POST,
				$post_id,
				(int) $language->language_id,
				'post_title',
				2,
				'Too late'
			);
			$this->fail( 'Expected ReviewWorkflowException was not thrown.' );
		} catch ( ReviewWorkflowException $exception ) {
			$this->assertSame( ReviewWorkflowService::CODE_CONFLICT, $exception->get_error_code() );
		}
	}

	public function test_pending_with_drifted_submitted_hash_allows_resubmit_refresh(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Drift', 'Drift text' );

		$this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 1 );

		$hash = Store::translation_hash( 'Drift text' );
		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			array(
				'submitted_translation_hash' => 'stalehash0000000000000000000000000000',
			)
		);

		$row = $this->service->submit( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title', 2 );

		$this->assertSame( Store::REVIEW_PENDING, $row->review_status );
		$this->assertSame( $hash, $row->submitted_translation_hash );
		$this->assertSame( 2, $row->review_submitted_by );
	}

	/**
	 * Creates a post with one translated title segment.
	 *
	 * @param object $language Target language row.
	 * @param string $source   Source title text.
	 * @param string $target   Translated title text.
	 */
	private function create_translated_segment( object $language, string $source, string $target ): int {
		$post_id = $this->factory()->post->create( array( 'post_title' => $source ) );

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => $source,
				'translated_text' => $target,
			)
		);

		return $post_id;
	}
}
