<?php
/**
 * Review Workflow audit event integration tests (R7 / ADR-0015 §12).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Review\ReviewAuditEvents;
use AIMultilingual\Workspace\Review\ReviewBatchCoordinator;

/**
 * Every review lifecycle transition emits a safe `aiml_review_audit` event.
 */
final class ReviewAuditTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	/**
	 * Captures every `aiml_review_audit` call for the duration of a closure.
	 *
	 * @param callable $callback Code to run while listening.
	 * @return array<int, array{0: string, 1: array<string, mixed>}>
	 */
	private function capture_audit( callable $callback ): array {
		$captured = array();
		$listener = static function ( $event, $payload ) use ( &$captured ): void {
			$captured[] = array( $event, $payload );
		};

		add_action( 'aiml_review_audit', $listener, 10, 2 );
		$callback();
		remove_action( 'aiml_review_audit', $listener, 10 );

		return $captured;
	}

	public function test_submit_emits_review_submitted_with_safe_payload(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Submit me', 'Skicka mig' );

		$events = $this->capture_audit(
			function () use ( $post_id, $language ): void {
				wp_set_current_user( $this->create_translator() );
				$response = rest_do_request( $this->submit_review_request( $post_id, 'post_title' ) );
				$this->assertSame( 200, $response->get_status() );
			}
		);

		$submitted = $this->find_event( $events, ReviewAuditEvents::SUBMITTED );
		$this->assertNotNull( $submitted );

		$payload = $submitted[1];
		$this->assertSame( $post_id, $payload['post_id'] );
		$this->assertSame( 'post_title', $payload['segment_key'] );
		$this->assertSame( (int) $language->language_id, $payload['language_id'] );
		$this->assertSame( Store::REVIEW_NOT_SUBMITTED, $payload['old_review_status'] );
		$this->assertSame( Store::REVIEW_PENDING, $payload['new_review_status'] );
		$this->assertArrayHasKey( 'submitted_hash_fingerprint', $payload );
		$this->assertSame( 8, strlen( (string) $payload['submitted_hash_fingerprint'] ) );

		$this->assert_no_translation_content( $payload );
	}

	public function test_resubmit_after_rejection_emits_review_resubmitted(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Resubmit', 'Om' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post_id, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		rest_do_request( $this->reject_review_request( $post_id, 'post_title', array( 'reason' => 'Fix it' ) ) );

		$events = $this->capture_audit(
			function () use ( $post_id ): void {
				wp_set_current_user( $this->create_translator() );
				rest_do_request( $this->submit_review_request( $post_id, 'post_title' ) );
			}
		);

		$resubmitted = $this->find_event( $events, ReviewAuditEvents::RESUBMITTED );
		$this->assertNotNull( $resubmitted );
		$this->assertSame( Store::REVIEW_REJECTED, $resubmitted[1]['old_review_status'] );
		$this->assertSame( Store::REVIEW_PENDING, $resubmitted[1]['new_review_status'] );
	}

	public function test_approve_emits_review_approved(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Approve', 'Godkänn' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post_id, 'post_title' ) );

		$events = $this->capture_audit(
			function () use ( $post_id ): void {
				wp_set_current_user( $this->create_reviewer() );
				$response = rest_do_request( $this->approve_review_request( $post_id, 'post_title' ) );
				$this->assertSame( 200, $response->get_status() );
			}
		);

		$approved = $this->find_event( $events, ReviewAuditEvents::APPROVED );
		$this->assertNotNull( $approved );
		$this->assertSame( Store::REVIEW_PENDING, $approved[1]['old_review_status'] );
		$this->assertSame( Store::REVIEW_APPROVED, $approved[1]['new_review_status'] );
		$this->assert_no_translation_content( $approved[1] );
	}

	public function test_duplicate_approve_does_not_emit_a_second_approved_event(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Idem', 'Samma' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post_id, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		rest_do_request( $this->approve_review_request( $post_id, 'post_title' ) );

		$events = $this->capture_audit(
			function () use ( $post_id ): void {
				rest_do_request( $this->approve_review_request( $post_id, 'post_title' ) );
			}
		);

		$this->assertNull( $this->find_event( $events, ReviewAuditEvents::APPROVED ) );
	}

	public function test_reject_emits_review_rejected_with_reason_length_only(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Reject', 'Avvisa' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post_id, 'post_title' ) );

		$reason = 'Needs glossary terms';
		$events = $this->capture_audit(
			function () use ( $post_id, $reason ): void {
				wp_set_current_user( $this->create_reviewer() );
				$response = rest_do_request(
					$this->reject_review_request( $post_id, 'post_title', array( 'reason' => $reason ) )
				);
				$this->assertSame( 200, $response->get_status() );
			}
		);

		$rejected = $this->find_event( $events, ReviewAuditEvents::REJECTED );
		$this->assertNotNull( $rejected );
		$this->assertSame( Store::REVIEW_REJECTED, $rejected[1]['new_review_status'] );
		$this->assertTrue( $rejected[1]['reason_present'] );
		$this->assertSame( strlen( $reason ), $rejected[1]['reason_length'] );
		$this->assertArrayNotHasKey( 'rejection_reason', $rejected[1] );
		$this->assert_no_translation_content( $rejected[1] );
	}

	public function test_edit_after_submit_emits_review_invalidated_by_edit_via_bridge(): void {
		$language = $this->add_language();
		$post_id  = $this->create_translated_segment( $language, 'Edited', 'Redigerad' );

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( $post_id, 'post_title' ) );

		$events = $this->capture_audit(
			function () use ( $post_id, $language ): void {
				$this->store->save_translation(
					array(
						'source_id'       => $post_id,
						'language_id'     => (int) $language->language_id,
						'field_key'       => 'post_title',
						'source_text'     => 'Edited',
						'translated_text' => 'Ny text',
					)
				);
			}
		);

		$invalidated = $this->find_event( $events, ReviewAuditEvents::INVALIDATED_BY_EDIT );
		$this->assertNotNull( $invalidated );
		$this->assertSame( Store::REVIEW_PENDING, $invalidated[1]['old_review_status'] );
		$this->assertSame( Store::REVIEW_NOT_SUBMITTED, $invalidated[1]['new_review_status'] );
		$this->assertSame( $post_id, $invalidated[1]['post_id'] );
	}

	public function test_batch_review_emits_one_review_batch_completed_summary(): void {
		$this->add_language();
		$post = $this->create_two_block_page();
		$keys = $this->block_segment_keys_for_audit();

		wp_set_current_user( $this->create_translator() );
		$this->translate_block_segments_for_audit( $post, $keys );

		$events = $this->capture_audit(
			function () use ( $post, $keys ): void {
				$response = rest_do_request(
					$this->batch_review_request(
						(int) $post->ID,
						ReviewBatchCoordinator::ACTION_SUBMIT,
						array_map(
							static fn( string $key ): array => array( 'segment_key' => $key ),
							$keys
						)
					)
				);
				$this->assertSame( 200, $response->get_status() );
			}
		);

		$batch_events = array_values(
			array_filter(
				$events,
				static fn( array $entry ): bool => ReviewAuditEvents::BATCH_COMPLETED === $entry[0]
			)
		);

		$this->assertCount( 1, $batch_events );
		$payload = $batch_events[0][1];
		$this->assertSame( (int) $post->ID, $payload['post_id'] );
		$this->assertSame( ReviewBatchCoordinator::ACTION_SUBMIT, $payload['action'] );
		$this->assertSame( 2, $payload['total_count'] );
		$this->assertSame( 2, $payload['succeeded_count'] );
		$this->assertSame( 0, $payload['failed_count'] );
		$this->assertSame( 'completed', $payload['batch_status'] );
	}

	/**
	 * Finds the first captured event matching a stable event name.
	 *
	 * @param array<int, array{0: string, 1: array<string, mixed>}> $events Captured events.
	 * @param string                                                $event  Event name to find.
	 * @return array{0: string, 1: array<string, mixed>}|null
	 */
	private function find_event( array $events, string $event ): ?array {
		foreach ( $events as $entry ) {
			if ( $event === $entry[0] ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Asserts an audit payload carries no translation/source content.
	 *
	 * @param array<string, mixed> $payload Audit payload.
	 */
	private function assert_no_translation_content( array $payload ): void {
		foreach ( array( 'translated_text', 'source_text', 'rejection_reason' ) as $forbidden ) {
			$this->assertArrayNotHasKey( $forbidden, $payload );
		}
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

	/**
	 * @return array{0: string, 1: string}
	 */
	private function block_segment_keys_for_audit(): array {
		return array(
			SegmentKey::build( '550e8400-e29b-41d4-a716-446655440000', Contract::FIELD_CONTENT ),
			SegmentKey::build( '660e8400-e29b-41d4-a716-446655440001', Contract::FIELD_CONTENT ),
		);
	}

	/**
	 * @param \WP_Post           $post Canonical post.
	 * @param array<int, string> $keys Segment keys to translate.
	 */
	private function translate_block_segments_for_audit( \WP_Post $post, array $keys ): void {
		$load = new \WP_REST_Request( 'GET', '/aiml/v1/workspace/' . (int) $post->ID . '/segments' );
		$load->set_param( 'language', 'sv' );
		$segments = rest_do_request( $load )->get_data()['segments'];

		$by_key = array();
		foreach ( $segments as $row ) {
			$by_key[ (string) ( $row['segment_key'] ?? '' ) ] = $row;
		}

		foreach ( $keys as $key ) {
			$segment  = $by_key[ $key ];
			$save     = $this->workspace_save_request(
				(int) $post->ID,
				$segment,
				array(
					'translated_text' => 'Translated ' . $key,
					'source_hash'     => $segment['source_hash'],
				)
			);
			$response = rest_do_request( $save );
			$this->assertSame( 200, $response->get_status() );
		}
	}
}
