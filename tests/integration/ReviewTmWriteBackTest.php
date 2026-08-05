<?php
/**
 * Approval-gated Translation Memory write-back tests (R5).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Memory\TMRepository;
use AIMultilingual\Translation\Memory\TranslationMemoryService;
use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * ADR-0015 §7 / F11 amendment: new-content TM write-back moves from
 * save-time to approval-time. Pending and rejected translations must never
 * write TM; approved eligible content writes back exactly once via the
 * existing {@see TranslationMemoryService} (no second TM writer); rejection
 * never deletes historical TM.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ReviewTmWriteBackTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	private TranslationMemoryService $tm;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
		$this->tm = new TranslationMemoryService( new TMRepository() );
	}

	public function test_pending_review_does_not_write_tm(): void {
		$post = $this->prepare_saved_segment( 'Pending TM', 'Väntande TM' );

		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		$this->assertNull( $this->lookup_tm_for_field( 'Pending TM' ) );
	}

	public function test_rejected_review_does_not_write_tm(): void {
		$post = $this->prepare_saved_segment( 'Rejected TM', 'Avvisad TM' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request(
			$this->reject_review_request(
				(int) $post->ID,
				'post_title',
				array( 'reason' => 'Needs another pass' )
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Store::REVIEW_REJECTED, $response->get_data()['review_status'] );

		$this->assertNull( $this->lookup_tm_for_field( 'Rejected TM' ) );
	}

	public function test_approve_writes_tm_entry_exactly_once_for_eligible_content(): void {
		$post = $this->prepare_saved_segment( 'Approved TM', 'Godkänd TM' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->approve_review_request( (int) $post->ID, 'post_title' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Store::REVIEW_APPROVED, $response->get_data()['review_status'] );

		$hit = $this->lookup_tm_for_field( 'Approved TM' );
		$this->assertNotNull( $hit );
		$this->assertSame( 'Godkänd TM', $hit['target_text'] );
		$this->assertSame( TMRepository::ORIGIN_HUMAN, $hit['origin'] );

		// Glossary version stamp is not wired for approval write-back (unchanged
		// pre-R5 behaviour); confirm the default is preserved, not regressed.
		$raw = $this->tm->repository()->find_by_identity(
			Store::source_hash( 'Approved TM' ),
			(int) $this->languages->default()->language_id,
			(int) $this->languages->find_by_code( 'sv' )->language_id,
			'field:post_title'
		);
		$this->assertNotNull( $raw );
		$this->assertSame( 0, (int) $raw->glossary_version );
	}

	public function test_approve_does_not_write_tm_for_machine_origin_content(): void {
		$this->add_language();
		$post = $this->create_page( 'Machine ineligible' );

		$this->store->save_translation(
			array(
				'source_id'       => (int) $post->ID,
				'language_id'     => (int) $this->languages->find_by_code( 'sv' )->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Machine ineligible',
				'translated_text' => 'Maskin ej godkänd',
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
			)
		);

		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request( $this->approve_review_request( (int) $post->ID, 'post_title' ) );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( Store::REVIEW_APPROVED, $response->get_data()['review_status'] );

		$this->assertNull( $this->lookup_tm_for_field( 'Machine ineligible' ) );
	}

	public function test_duplicate_approve_is_idempotent_and_does_not_inflate_usage(): void {
		$post = $this->prepare_saved_segment( 'Duplicate approve TM', 'Dubbel godkännande TM' );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$first = rest_do_request( $this->approve_review_request( (int) $post->ID, 'post_title' ) );
		$this->assertSame( 200, $first->get_status() );

		$hit_after_first = $this->lookup_tm_for_field( 'Duplicate approve TM' );
		$this->assertNotNull( $hit_after_first );
		$this->assertSame( 0, (int) $hit_after_first['use_count'] );

		$second = rest_do_request( $this->approve_review_request( (int) $post->ID, 'post_title' ) );
		$this->assertSame( 200, $second->get_status() );
		$this->assertSame( Store::REVIEW_APPROVED, $second->get_data()['review_status'] );

		$hit_after_second = $this->lookup_tm_for_field( 'Duplicate approve TM' );
		$this->assertNotNull( $hit_after_second );
		$this->assertSame( 'Dubbel godkännande TM', $hit_after_second['target_text'] );
		$this->assertSame( 0, (int) $hit_after_second['use_count'], 'Duplicate approve must not inflate TM usage.' );
	}

	public function test_rejection_after_historic_tm_does_not_delete_or_overwrite_it(): void {
		$this->add_language();
		$default = $this->languages->default();
		$this->assertNotNull( $default );

		$source_text = 'Historic memory phrase';
		$this->tm->repository()->upsert(
			array(
				'source_lang_id' => (int) $default->language_id,
				'target_lang_id' => (int) $this->languages->find_by_code( 'sv' )->language_id,
				'source_hash'    => Store::source_hash( $source_text ),
				'source_text'    => $source_text,
				'target_text'    => 'Historisk minnesfras',
				'text_format'    => Store::FORMAT_PLAIN,
				'context'        => 'field:post_title',
				'origin'         => TMRepository::ORIGIN_HUMAN,
			)
		);

		$post = $this->create_page( $source_text );
		$this->store->save_translation(
			array(
				'source_id'       => (int) $post->ID,
				'language_id'     => (int) $this->languages->find_by_code( 'sv' )->language_id,
				'field_key'       => 'post_title',
				'source_text'     => $source_text,
				'translated_text' => 'Ny föreslagen fras',
			)
		);
		wp_set_current_user( $this->create_translator() );
		rest_do_request( $this->submit_review_request( (int) $post->ID, 'post_title' ) );

		wp_set_current_user( $this->create_reviewer() );
		$response = rest_do_request(
			$this->reject_review_request(
				(int) $post->ID,
				'post_title',
				array( 'reason' => 'Does not match glossary' )
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Store::REVIEW_REJECTED, $response->get_data()['review_status'] );
		// Reject preserves the submitted text for correction.
		$this->assertSame( 'Ny föreslagen fras', $response->get_data()['translated_text'] );

		$hit = $this->lookup_tm_for_field( $source_text );
		$this->assertNotNull( $hit );
		$this->assertSame( 'Historisk minnesfras', $hit['target_text'], 'Reject must not delete or overwrite historical TM.' );
	}

	/**
	 * Creates a post with a saved title translation and returns it.
	 *
	 * @param string $source Source title.
	 * @param string $target Translated title.
	 */
	private function prepare_saved_segment( string $source, string $target ): \WP_Post {
		$this->add_language();
		$post = $this->create_page( $source );

		$this->store->save_translation(
			array(
				'source_id'       => (int) $post->ID,
				'language_id'     => (int) $this->languages->find_by_code( 'sv' )->language_id,
				'field_key'       => 'post_title',
				'source_text'     => $source,
				'translated_text' => $target,
			)
		);

		wp_set_current_user( $this->create_translator() );

		return $post;
	}

	/**
	 * Looks up a `field:post_title` TM entry for the default → sv pair.
	 *
	 * @param string $source_text Source title text.
	 * @return array<string, mixed>|null
	 */
	private function lookup_tm_for_field( string $source_text ): ?array {
		$default = $this->languages->default();
		$this->assertNotNull( $default );
		$language = $this->languages->find_by_code( 'sv' );
		$this->assertNotNull( $language );

		return $this->tm->lookup_exact(
			$source_text,
			(int) $default->language_id,
			(int) $language->language_id,
			'field:post_title'
		);
	}
}
