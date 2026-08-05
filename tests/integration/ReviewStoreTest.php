<?php
/**
 * Store-owned review state tests (R2).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Store;

/**
 * Review axis persistence and invalidate-on-edit behaviour.
 */
final class ReviewStoreTest extends AimlTestCase {

	public function test_new_translation_defaults_to_not_submitted(): void {
		$language = $this->add_language();
		$post_id  = $this->factory()->post->create(
			array(
				'post_title'   => 'Review defaults',
				'post_content' => 'Body',
			)
		);

		$result = $this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Review defaults',
				'translated_text' => 'Granska standarder',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$this->assertTrue( $result );

		$row = $this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );
		$this->assertSame( Store::REVIEW_NOT_SUBMITTED, $row->review_status );
		$this->assertSame( '', $row->submitted_translation_hash );
		$this->assertSame( '', $row->rejection_reason );
		$this->assertNull( $row->reviewed_by );
		$this->assertNull( $row->rejected_by );
		$this->assertSame( Store::translation_hash( 'Granska standarder' ), $row->translation_hash );
	}

	public function test_submit_metadata_via_helper_preserves_translation_content(): void {
		$language = $this->add_language();
		$post_id  = $this->factory()->post->create( array( 'post_title' => 'Submit meta' ) );

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Submit meta',
				'translated_text' => 'Skicka meta',
			)
		);

		$row  = $this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title' );
		$hash = (string) $row->translation_hash;
		$now  = current_time( 'mysql', true );

		$result = $this->store->update_review_metadata(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			array(
				'review_status'              => Store::REVIEW_PENDING,
				'review_submitted_by'        => 42,
				'review_submitted_at'        => $now,
				'submitted_translation_hash' => $hash,
			)
		);

		$this->assertTrue( $result );

		$after = $this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title' );
		$this->assertSame( Store::REVIEW_PENDING, $after->review_status );
		$this->assertSame( $hash, $after->submitted_translation_hash );
		$this->assertSame( 42, $after->review_submitted_by );
		$this->assertSame( 'Skicka meta', $after->translated_text );
		$this->assertSame( Store::STATUS_MANUALLY_EDITED, $after->status );
		$this->assertSame( $hash, $after->translation_hash );
	}

	public function test_edit_invalidates_review_state(): void {
		$language = $this->add_language();
		$post_id  = $this->factory()->post->create( array( 'post_title' => 'Invalidate' ) );

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Invalidate',
				'translated_text' => 'Original',
			)
		);

		$hash = Store::translation_hash( 'Original' );
		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			array(
				'review_status'              => Store::REVIEW_APPROVED,
				'review_submitted_by'        => 7,
				'review_submitted_at'        => current_time( 'mysql', true ),
				'submitted_translation_hash' => $hash,
				'reviewed_by'                => 9,
				'reviewed_at'                => current_time( 'mysql', true ),
			)
		);

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Invalidate',
				'translated_text' => 'Corrected',
			)
		);

		$after = $this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title' );
		$this->assertSame( 'Corrected', $after->translated_text );
		$this->assertSame( Store::REVIEW_NOT_SUBMITTED, $after->review_status );
		$this->assertSame( '', $after->submitted_translation_hash );
		$this->assertNull( $after->reviewed_by );
		$this->assertNull( $after->review_submitted_by );
		$this->assertSame( '', $after->rejection_reason );
	}

	public function test_noop_save_preserves_review_state(): void {
		$language = $this->add_language();
		$post_id  = $this->factory()->post->create( array( 'post_title' => 'Noop' ) );

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Noop',
				'translated_text' => 'Stabil',
			)
		);

		$hash = Store::translation_hash( 'Stabil' );
		$now  = current_time( 'mysql', true );
		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			array(
				'review_status'              => Store::REVIEW_PENDING,
				'review_submitted_by'        => 3,
				'review_submitted_at'        => $now,
				'submitted_translation_hash' => $hash,
			)
		);

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Noop',
				'translated_text' => 'Stabil',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$after = $this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title' );
		$this->assertSame( Store::REVIEW_PENDING, $after->review_status );
		$this->assertSame( $hash, $after->submitted_translation_hash );
		$this->assertSame( 3, $after->review_submitted_by );
		$this->assertSame( 'Stabil', $after->translated_text );
	}

	public function test_rejected_and_approved_content_preserved_by_metadata_update(): void {
		$language = $this->add_language();
		$post_id  = $this->factory()->post->create( array( 'post_title' => 'Preserve' ) );

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Preserve',
				'translated_text' => 'Bevara text',
			)
		);

		$hash = Store::translation_hash( 'Bevara text' );

		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			array(
				'review_status'              => Store::REVIEW_REJECTED,
				'submitted_translation_hash' => $hash,
				'rejection_reason'           => 'Needs glossary terms',
				'rejected_by'                => 11,
				'rejected_at'                => current_time( 'mysql', true ),
			)
		);

		$rejected = $this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title' );
		$this->assertSame( 'Bevara text', $rejected->translated_text );
		$this->assertSame( Store::REVIEW_REJECTED, $rejected->review_status );
		$this->assertSame( 'Needs glossary terms', $rejected->rejection_reason );

		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			array(
				'review_status'              => Store::REVIEW_APPROVED,
				'submitted_translation_hash' => $hash,
				'rejection_reason'           => '',
				'rejected_by'                => null,
				'rejected_at'                => null,
				'reviewed_by'                => 12,
				'reviewed_at'                => current_time( 'mysql', true ),
			)
		);

		$approved = $this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title' );
		$this->assertSame( 'Bevara text', $approved->translated_text );
		$this->assertSame( $hash, $approved->translation_hash );
		$this->assertSame( Store::REVIEW_APPROVED, $approved->review_status );
		$this->assertSame( 12, $approved->reviewed_by );
		$this->assertSame( '', $approved->rejection_reason );
	}

	public function test_concurrent_store_updates_keep_axes_independent(): void {
		$language = $this->add_language();
		$post_id  = $this->factory()->post->create( array( 'post_title' => 'Axes' ) );

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Axes',
				'translated_text' => 'Axlar',
				'status'          => Store::STATUS_MACHINE_TRANSLATED,
			)
		);

		$this->store->update_review_metadata(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			array(
				'review_status'              => Store::REVIEW_PENDING,
				'submitted_translation_hash' => Store::translation_hash( 'Axlar' ),
			)
		);

		// Content save with identical text must not clear pending review.
		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Axes',
				'translated_text' => 'Axlar',
				'status'          => Store::STATUS_MANUALLY_EDITED,
			)
		);

		$row = $this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title' );
		$this->assertSame( Store::STATUS_MANUALLY_EDITED, $row->status );
		$this->assertSame( Store::REVIEW_PENDING, $row->review_status );
	}

	public function test_update_review_metadata_rejects_unknown_columns(): void {
		$language = $this->add_language();
		$post_id  = $this->factory()->post->create( array( 'post_title' => 'Guard' ) );

		$this->store->save_translation(
			array(
				'source_id'       => $post_id,
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'source_text'     => 'Guard',
				'translated_text' => 'Vakta',
			)
		);

		$result = $this->store->update_review_metadata(
			Store::SOURCE_POST,
			$post_id,
			(int) $language->language_id,
			'post_title',
			array(
				'translated_text' => 'Hacked',
				'status'          => Store::STATUS_REVIEWED,
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );

		$row = $this->store->get( Store::SOURCE_POST, $post_id, (int) $language->language_id, 'post_title' );
		$this->assertSame( 'Vakta', $row->translated_text );
		$this->assertSame( Store::STATUS_MANUALLY_EDITED, $row->status );
	}
}
