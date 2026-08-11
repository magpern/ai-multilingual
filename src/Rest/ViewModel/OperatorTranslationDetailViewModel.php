<?php
/**
 * OTL operations detail ViewModel.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rest\ViewModel;

/**
 * Rich detail representation with authoritative TI.4 / TI.5 / TI.7 payloads.
 */
final class OperatorTranslationDetailViewModel {

	/**
	 * Constructs a detail ViewModel.
	 *
	 * @param array<string, mixed> $data Assembled detail.
	 */
	public function __construct(
		private array $data
	) {}

	/**
	 * Serializes the detail model.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'translation_id'      => (int) ( $this->data['translation_id'] ?? 0 ),
			'source_type'         => (string) ( $this->data['source_type'] ?? '' ),
			'source_id'           => (int) ( $this->data['source_id'] ?? 0 ),
			'source_subtype'      => (string) ( $this->data['source_subtype'] ?? '' ),
			'language_id'         => (int) ( $this->data['language_id'] ?? 0 ),
			'language_code'       => (string) ( $this->data['language_code'] ?? '' ),
			'segment_key'         => (string) ( $this->data['segment_key'] ?? '' ),
			'field_key'           => (string) ( $this->data['field_key'] ?? '' ),
			'status'              => (string) ( $this->data['status'] ?? '' ),
			'review_status'       => (string) ( $this->data['review_status'] ?? '' ),
			'publish_status'      => (string) ( $this->data['publish_status'] ?? '' ),
			'is_stale'            => (bool) ( $this->data['is_stale'] ?? false ),
			'attention_reasons'   => $this->attention_reasons(),
			'source_text'         => (string) ( $this->data['source_text'] ?? '' ),
			'translated_text'     => (string) ( $this->data['translated_text'] ?? '' ),
			'text_format'         => (string) ( $this->data['text_format'] ?? '' ),
			'source_hash'         => (string) ( $this->data['source_hash'] ?? '' ),
			'updated_at'          => (string) ( $this->data['updated_at'] ?? '' ),
			'created_at'          => (string) ( $this->data['created_at'] ?? '' ),
			'review_submitted_at' => (string) ( $this->data['review_submitted_at'] ?? '' ),
			'reviewed_at'         => (string) ( $this->data['reviewed_at'] ?? '' ),
			'published_at'        => (string) ( $this->data['published_at'] ?? '' ),
			'published_by'        => $this->data['published_by'] ?? null,
			'provider'            => (string) ( $this->data['provider'] ?? '' ),
			'model'               => (string) ( $this->data['model'] ?? '' ),
			'prompt_profile'      => (string) ( $this->data['prompt_profile'] ?? '' ),
			'tm_id'               => $this->data['tm_id'] ?? null,
			'error_code'          => (string) ( $this->data['error_code'] ?? '' ),
			'error_message'       => (string) ( $this->data['error_message'] ?? '' ),
			'qa'                  => is_array( $this->data['qa'] ?? null ) ? $this->data['qa'] : null,
			'assessment'          => is_array( $this->data['assessment'] ?? null ) ? $this->data['assessment'] : null,
			'publication'         => is_array( $this->data['publication'] ?? null ) ? $this->data['publication'] : null,
			'links'               => is_array( $this->data['links'] ?? null ) ? $this->data['links'] : array(),
			'allowed_actions'     => is_array( $this->data['allowed_actions'] ?? null ) ? $this->data['allowed_actions'] : array(),
			'jobs'                => $this->data['jobs'] ?? null,
		);
	}

	/**
	 * Sanitizes attention_reasons to the frozen OTL.1 vocabulary.
	 *
	 * @return list<string>
	 */
	private function attention_reasons(): array {
		$raw = $this->data['attention_reasons'] ?? array();
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$allowed = array(
			'stale'              => true,
			'review_pending'     => true,
			'review_rejected'    => true,
			'unpublished'        => true,
			'translation_failed' => true,
		);
		$out     = array();
		foreach ( $raw as $reason ) {
			$id = strtolower( preg_replace( '/[^a-z0-9_]/', '', (string) $reason ) ?? '' );
			if ( isset( $allowed[ $id ] ) && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}

		return $out;
	}
}
