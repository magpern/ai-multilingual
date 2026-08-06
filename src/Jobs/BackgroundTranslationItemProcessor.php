<?php
/**
 * Sole per-item domain boundary for background translation jobs.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Jobs;

use AIMultilingual\Glossary\GlossaryService;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\SegmentAssembler;
use AIMultilingual\Workspace\TranslationService;
use WP_Error;
use WP_Post;

/**
 * Delegates translation work exclusively through TranslationService (plan §19).
 */
final class BackgroundTranslationItemProcessor {

	/**
	 * Segment store for conflict reads.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Existing auto-translate boundary.
	 *
	 * @var TranslationService
	 */
	private TranslationService $translation;

	/**
	 * Glossary version source.
	 *
	 * @var GlossaryService
	 */
	private GlossaryService $glossary;

	/**
	 * Source hash assembly for pre-checks.
	 *
	 * @var SegmentAssembler
	 */
	private SegmentAssembler $assembler;

	/**
	 * Builds the processor.
	 *
	 * @param Store              $store       Segment store.
	 * @param TranslationService $translation Translation boundary.
	 * @param GlossaryService    $glossary    Glossary service.
	 * @param SegmentAssembler   $assembler   Segment assembler.
	 */
	public function __construct(
		Store $store,
		TranslationService $translation,
		GlossaryService $glossary,
		SegmentAssembler $assembler
	) {
		$this->store       = $store;
		$this->translation = $translation;
		$this->glossary    = $glossary;
		$this->assembler   = $assembler;
	}

	/**
	 * Process one job item through the existing translation pipeline.
	 *
	 * @param object  $job         Job row.
	 * @param object  $item        Item row.
	 * @param WP_Post $post        Canonical post.
	 * @return ItemResult
	 */
	public function process( object $job, object $item, WP_Post $post ): ItemResult {
		$language_id = (int) $job->language_id;
		$segment_key = (string) $item->segment_key;
		$job_type    = (string) $job->job_type;

		$assembled = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $assembled ) {
			return ItemResult::from_error(
				ItemStatuses::FAILED,
				'aiml_invalid_segment',
				ProviderResult::ERROR_PERMANENT,
				'Unknown segment key for this post.'
			);
		}

		$current_source_hash = (string) ( $assembled['source_hash'] ?? '' );
		$captured_source     = (string) ( $item->source_hash_captured ?? '' );

		if ( '' !== $captured_source && $current_source_hash !== $captured_source ) {
			return ItemResult::stale_source();
		}

		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, $language_id, $segment_key );

		$conflict = $this->evaluate_conflict(
			$row,
			$job_type,
			(string) ( $item->translation_hash_captured ?? '' )
		);
		if ( null !== $conflict ) {
			return $conflict;
		}

		$glossary_version = $this->glossary->current_version();
		$result           = $this->translation->translate_segment( $post, $language_id, $segment_key );

		if ( $result instanceof WP_Error ) {
			return $this->map_translate_error( $result );
		}

		return ItemResult::completed( $glossary_version );
	}

	/**
	 * Apply overwrite/conflict policy before calling TranslationService (plan §11).
	 *
	 * @param object|null $row                       Store row when present.
	 * @param string      $job_type                  Job type code.
	 * @param string      $translation_hash_captured Snapshot translation hash.
	 */
	private function evaluate_conflict( ?object $row, string $job_type, string $translation_hash_captured ): ?ItemResult {
		if ( null === $row ) {
			return null;
		}

		$review_status = (string) ( $row->review_status ?? Store::REVIEW_NOT_SUBMITTED );
		if ( in_array(
			$review_status,
			array(
				Store::REVIEW_PENDING,
				Store::REVIEW_APPROVED,
				Store::REVIEW_REJECTED,
			),
			true
		) ) {
			return ItemResult::skipped_conflict( 'Segment is in an active review state.' );
		}

		$status = (string) ( $row->status ?? Store::STATUS_MISSING );
		if ( in_array( $status, array( Store::STATUS_MANUALLY_EDITED, Store::STATUS_REVIEWED ), true ) ) {
			return ItemResult::skipped_conflict( 'Segment was manually edited or reviewed.' );
		}

		$translated = trim( (string) ( $row->translated_text ?? '' ) );
		if ( Store::STATUS_MISSING === $status || '' === $translated ) {
			return null;
		}

		if ( Store::STATUS_MACHINE_TRANSLATED !== $status ) {
			return ItemResult::skipped_conflict( 'Segment status is not eligible for background overwrite.' );
		}

		if ( ! JobTypes::allows_retranslate( $job_type ) ) {
			return ItemResult::skipped_conflict( 'Job type does not allow retranslation of machine output.' );
		}

		if ( '' !== $translation_hash_captured ) {
			$current_hash = Store::translation_hash( (string) ( $row->translated_text ?? '' ) );
			if ( $current_hash !== $translation_hash_captured ) {
				return ItemResult::skipped_conflict( 'Translation hash differs from the job snapshot.' );
			}
		}

		return null;
	}

	/**
	 * Map TranslationService WP_Error to a bounded ItemResult.
	 *
	 * @param WP_Error $error Provider or validation error.
	 */
	private function map_translate_error( WP_Error $error ): ItemResult {
		$code    = (string) $error->get_error_code();
		$message = (string) $error->get_error_message();
		$class   = $this->classify_error( $code, $error );

		$status = ProviderResult::ERROR_RETRYABLE === $class
			? ItemStatuses::RETRY_WAIT
			: ItemStatuses::FAILED;

		return ItemResult::from_error( $status, $code, $class, $message );
	}

	/**
	 * Minimal retry taxonomy stub (full RetryPolicy arrives in J4).
	 *
	 * @param string   $code  Error code.
	 * @param WP_Error $error Full error for data inspection.
	 */
	private function classify_error( string $code, WP_Error $error ): string {
		$data = $error->get_error_data();
		$http = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;

		if ( in_array( $code, array( 'aiml_rate_limited', 'http_request_failed' ), true ) ) {
			return ProviderResult::ERROR_RETRYABLE;
		}

		if ( $http >= 500 && $http < 600 ) {
			return ProviderResult::ERROR_RETRYABLE;
		}

		if ( in_array( $code, array( 'aiml_invalid_segment', 'aiml_invalid_language' ), true ) ) {
			return ProviderResult::ERROR_PERMANENT;
		}

		return ProviderResult::ERROR_PERMANENT;
	}
}
