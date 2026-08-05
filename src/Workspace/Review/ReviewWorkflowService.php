<?php
/**
 * Review workflow transition policy.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Review;

use AIMultilingual\Translation\Store;
use WP_Error;

/**
 * Owns legal review-status transitions only (no REST, caps, TM, or QA).
 *
 * Also emits stable `aiml_review_audit` lifecycle events (ADR-0015 §12) for
 * every real transition it persists — this is a domain-event concern, not a
 * transport/policy one, so it stays alongside the transitions that produce
 * the old/new status pair the audit payload needs.
 */
final class ReviewWorkflowService {

	public const CODE_NOT_PENDING         = 'aiml_review_not_pending';
	public const CODE_ALREADY_PENDING     = 'aiml_review_already_pending';
	public const CODE_TRANSLATION_CHANGED = 'aiml_review_translation_changed';
	public const CODE_CONFLICT            = 'aiml_review_conflict';
	public const CODE_REASON_REQUIRED     = 'aiml_review_reason_required';
	public const CODE_INVALID_TRANSITION  = 'aiml_review_invalid_transition';
	public const CODE_NO_TRANSLATION      = 'aiml_review_no_translation';
	public const CODE_INVALID_TRANSLATION = 'aiml_review_invalid_translation';
	public const CODE_PERMISSION_DENIED   = 'aiml_review_permission_denied';
	public const CODE_QA_BLOCKED          = 'aiml_review_qa_blocked';
	public const CODE_SERVICE_ERROR       = 'aiml_review_service_error';

	public const REASON_MIN_LEN = 1;
	public const REASON_MAX_LEN = 512;

	/**
	 * Segment store.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Audit logger for stable review lifecycle events (ADR-0015 §12).
	 *
	 * @var ReviewAuditLogger
	 */
	private ReviewAuditLogger $audit;

	/**
	 * Builds the service.
	 *
	 * @param Store                  $store Segment store.
	 * @param ReviewAuditLogger|null $audit Audit logger.
	 */
	public function __construct( Store $store, ?ReviewAuditLogger $audit = null ) {
		$this->store = $store;
		$this->audit = $audit ?? new ReviewAuditLogger();
	}

	/**
	 * Submits or resubmits a translation for review.
	 *
	 * @param string      $source_type            Source type.
	 * @param int         $source_id              Source object id.
	 * @param int         $language_id            Language id.
	 * @param string      $segment_key            Segment key.
	 * @param int         $user_id                Submitting user id.
	 * @param string|null $expected_review_status Optional optimistic review_status.
	 * @return object Refreshed Store row.
	 *
	 * @throws ReviewWorkflowException When the transition is illegal or conflicts.
	 */
	public function submit(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		int $user_id,
		?string $expected_review_status = null
	): object {
		$row = $this->load_segment( $source_type, $source_id, $language_id, $segment_key );
		$this->assert_eligible_translation( $row );
		$this->assert_expected_review_status( $row, $expected_review_status );

		$review_status  = (string) $row->review_status;
		$current_hash   = (string) $row->translation_hash;
		$submitted_hash = (string) $row->submitted_translation_hash;

		if ( Store::REVIEW_PENDING === $review_status ) {
			if ( $current_hash === $submitted_hash ) {
				$this->raise(
					self::CODE_ALREADY_PENDING,
					'Translation is already pending review.',
					$this->segment_review_context( $row )
				);
			}
		} elseif ( Store::REVIEW_APPROVED === $review_status ) {
			$this->raise(
				self::CODE_INVALID_TRANSITION,
				'Approved translations must be edited before resubmitting for review.',
				$this->segment_review_context( $row )
			);
		} elseif ( ! in_array( $review_status, array( Store::REVIEW_NOT_SUBMITTED, Store::REVIEW_REJECTED, Store::REVIEW_PENDING ), true ) ) {
			$this->raise(
				self::CODE_INVALID_TRANSITION,
				'Illegal review transition for submit.',
				$this->segment_review_context( $row )
			);
		}

		$hash = Store::translation_hash( (string) $row->translated_text );
		$now  = current_time( 'mysql', true );

		$this->persist_review_metadata(
			$source_type,
			$source_id,
			$language_id,
			$segment_key,
			array(
				'review_status'              => Store::REVIEW_PENDING,
				'submitted_translation_hash' => $hash,
				'review_submitted_by'        => $user_id,
				'review_submitted_at'        => $now,
				'reviewed_by'                => null,
				'reviewed_at'                => null,
				'rejection_reason'           => '',
				'rejected_by'                => null,
				'rejected_at'                => null,
			)
		);

		$this->audit->log(
			Store::REVIEW_REJECTED === $review_status ? ReviewAuditEvents::RESUBMITTED : ReviewAuditEvents::SUBMITTED,
			$this->audit_payload(
				$source_type,
				$source_id,
				$language_id,
				$segment_key,
				$review_status,
				Store::REVIEW_PENDING,
				$user_id,
				$hash
			)
		);

		return $this->refresh_segment( $source_type, $source_id, $language_id, $segment_key );
	}

	/**
	 * Approves a pending review.
	 *
	 * @param string      $source_type              Source type.
	 * @param int         $source_id                Source object id.
	 * @param int         $language_id              Language id.
	 * @param string      $segment_key              Segment key.
	 * @param int         $user_id                  Reviewer user id.
	 * @param string|null $expected_review_status   Optional optimistic review_status.
	 * @param string|null $client_submitted_hash    Optional client submitted hash.
	 * @return object Refreshed Store row.
	 *
	 * @throws ReviewWorkflowException When the transition is illegal or conflicts.
	 */
	public function approve(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		int $user_id,
		?string $expected_review_status = null,
		?string $client_submitted_hash = null
	): object {
		$row = $this->load_segment( $source_type, $source_id, $language_id, $segment_key );

		if ( $this->is_idempotent_approved( $row, $client_submitted_hash ) ) {
			return $row;
		}

		if ( Store::REVIEW_PENDING !== (string) $row->review_status ) {
			$this->raise(
				self::CODE_NOT_PENDING,
				'Translation is not pending review.',
				$this->segment_review_context( $row )
			);
		}

		$this->assert_expected_review_status( $row, $expected_review_status );
		$this->assert_submitted_hash_match( $row, $client_submitted_hash );

		$now = current_time( 'mysql', true );

		$this->persist_review_metadata(
			$source_type,
			$source_id,
			$language_id,
			$segment_key,
			array(
				'review_status'    => Store::REVIEW_APPROVED,
				'reviewed_by'      => $user_id,
				'reviewed_at'      => $now,
				'rejection_reason' => '',
				'rejected_by'      => null,
				'rejected_at'      => null,
			)
		);

		$this->audit->log(
			ReviewAuditEvents::APPROVED,
			$this->audit_payload(
				$source_type,
				$source_id,
				$language_id,
				$segment_key,
				Store::REVIEW_PENDING,
				Store::REVIEW_APPROVED,
				$user_id,
				(string) $row->submitted_translation_hash
			)
		);

		return $this->refresh_segment( $source_type, $source_id, $language_id, $segment_key );
	}

	/**
	 * Rejects a pending review with a required reason.
	 *
	 * @param string      $source_type              Source type.
	 * @param int         $source_id                Source object id.
	 * @param int         $language_id              Language id.
	 * @param string      $segment_key              Segment key.
	 * @param int         $user_id                  Reviewer user id.
	 * @param string      $reason                   Rejection reason.
	 * @param string|null $expected_review_status   Optional optimistic review_status.
	 * @param string|null $client_submitted_hash    Optional client submitted hash.
	 * @return object Refreshed Store row.
	 *
	 * @throws ReviewWorkflowException When the transition is illegal or conflicts.
	 */
	public function reject(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		int $user_id,
		string $reason,
		?string $expected_review_status = null,
		?string $client_submitted_hash = null
	): object {
		$normalized_reason = self::normalize_rejection_reason( $reason );

		$row = $this->load_segment( $source_type, $source_id, $language_id, $segment_key );

		if ( $this->is_idempotent_rejected( $row, $normalized_reason, $client_submitted_hash ) ) {
			return $row;
		}

		if ( Store::REVIEW_PENDING !== (string) $row->review_status ) {
			$this->raise(
				self::CODE_NOT_PENDING,
				'Translation is not pending review.',
				$this->segment_review_context( $row )
			);
		}

		$this->assert_expected_review_status( $row, $expected_review_status );
		$this->assert_submitted_hash_match( $row, $client_submitted_hash );

		$now = current_time( 'mysql', true );

		$this->persist_review_metadata(
			$source_type,
			$source_id,
			$language_id,
			$segment_key,
			array(
				'review_status'    => Store::REVIEW_REJECTED,
				'rejection_reason' => $normalized_reason,
				'rejected_by'      => $user_id,
				'rejected_at'      => $now,
				'reviewed_by'      => null,
				'reviewed_at'      => null,
			)
		);

		$this->audit->log(
			ReviewAuditEvents::REJECTED,
			array_merge(
				$this->audit_payload(
					$source_type,
					$source_id,
					$language_id,
					$segment_key,
					Store::REVIEW_PENDING,
					Store::REVIEW_REJECTED,
					$user_id,
					(string) $row->submitted_translation_hash
				),
				array(
					'reason_present' => '' !== $normalized_reason,
					'reason_length'  => strlen( $normalized_reason ),
				)
			)
		);

		return $this->refresh_segment( $source_type, $source_id, $language_id, $segment_key );
	}

	/**
	 * Trims, sanitizes, and validates a rejection reason.
	 *
	 * @param string $reason Raw rejection reason.
	 * @return string Normalized reason.
	 *
	 * @throws ReviewWorkflowException When the reason is missing or too long.
	 */
	public static function normalize_rejection_reason( string $reason ): string {
		$trimmed = trim( $reason );

		if ( '' === $trimmed || strlen( $trimmed ) > self::REASON_MAX_LEN ) {
			self::raise_static(
				self::CODE_REASON_REQUIRED,
				'Rejection reason is required and must be between 1 and 512 characters.'
			);
		}

		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $trimmed );
		}

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return wp_strip_all_tags( $trimmed );
		}

		return strip_tags( $trimmed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- unit-test fallback without WP loaded.
	}

	/**
	 * Loads a segment or throws when missing.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Segment key.
	 * @return object Store row.
	 *
	 * @throws ReviewWorkflowException When the segment does not exist.
	 */
	private function load_segment(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key
	): object {
		$row = $this->store->get( $source_type, $source_id, $language_id, $segment_key );

		if ( null === $row ) {
			$this->raise( self::CODE_NO_TRANSLATION, 'Translation segment not found.' );
		}

		return $row;
	}

	/**
	 * Ensures the segment has submit-eligible translated content.
	 *
	 * @param object $row Store row.
	 *
	 * @throws ReviewWorkflowException When translation is missing or invalid.
	 */
	private function assert_eligible_translation( object $row ): void {
		$translated_text = (string) ( $row->translated_text ?? '' );
		$status          = (string) ( $row->status ?? '' );

		if ( '' === trim( $translated_text ) ) {
			$this->raise(
				self::CODE_INVALID_TRANSLATION,
				'Translation text is empty.',
				$this->segment_review_context( $row )
			);
		}

		if ( in_array( $status, array( Store::STATUS_MISSING, Store::STATUS_IGNORED, Store::STATUS_FAILED ), true ) ) {
			$this->raise(
				self::CODE_INVALID_TRANSLATION,
				'Translation status is not eligible for review.',
				$this->segment_review_context( $row )
			);
		}
	}

	/**
	 * Validates optimistic review_status when provided.
	 *
	 * @param object      $row                    Store row.
	 * @param string|null $expected_review_status Client-expected review_status.
	 *
	 * @throws ReviewWorkflowException On mismatch.
	 */
	private function assert_expected_review_status( object $row, ?string $expected_review_status ): void {
		if ( null === $expected_review_status || '' === $expected_review_status ) {
			return;
		}

		if ( (string) $row->review_status !== $expected_review_status ) {
			$this->raise(
				self::CODE_CONFLICT,
				'Review status conflict.',
				array_merge(
					$this->segment_review_context( $row ),
					array(
						'expected_review_status' => $expected_review_status,
					)
				),
				409
			);
		}
	}

	/**
	 * Validates submitted-hash concurrency for approve/reject.
	 *
	 * @param object      $row                   Store row.
	 * @param string|null $client_submitted_hash Optional client hash.
	 *
	 * @throws ReviewWorkflowException On hash drift.
	 */
	private function assert_submitted_hash_match( object $row, ?string $client_submitted_hash ): void {
		$current_hash   = (string) $row->translation_hash;
		$submitted_hash = (string) $row->submitted_translation_hash;

		$server_match = '' !== $submitted_hash && $current_hash === $submitted_hash;
		$client_match = null === $client_submitted_hash || '' === $client_submitted_hash
			|| ( $client_submitted_hash === $current_hash && $client_submitted_hash === $submitted_hash );

		if ( $server_match && $client_match ) {
			return;
		}

		$this->raise(
			self::CODE_CONFLICT,
			'Submitted translation hash mismatch.',
			array_merge(
				$this->segment_review_context( $row ),
				array(
					'reason' => self::CODE_TRANSLATION_CHANGED,
				)
			),
			409
		);
	}

	/**
	 * Returns true when approve is a no-op on an already-approved segment.
	 *
	 * @param object      $row                   Store row.
	 * @param string|null $client_submitted_hash Optional client hash.
	 */
	private function is_idempotent_approved( object $row, ?string $client_submitted_hash ): bool {
		if ( Store::REVIEW_APPROVED !== (string) $row->review_status ) {
			return false;
		}

		$current_hash   = (string) $row->translation_hash;
		$submitted_hash = (string) $row->submitted_translation_hash;

		if ( $current_hash !== $submitted_hash ) {
			return false;
		}

		if ( null !== $client_submitted_hash && '' !== $client_submitted_hash && $client_submitted_hash !== $submitted_hash ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns true when reject is a no-op on an already-rejected segment.
	 *
	 * @param object      $row                   Store row.
	 * @param string      $normalized_reason     Normalized rejection reason.
	 * @param string|null $client_submitted_hash Optional client hash.
	 */
	private function is_idempotent_rejected( object $row, string $normalized_reason, ?string $client_submitted_hash ): bool {
		if ( Store::REVIEW_REJECTED !== (string) $row->review_status ) {
			return false;
		}

		if ( (string) $row->rejection_reason !== $normalized_reason ) {
			return false;
		}

		$current_hash   = (string) $row->translation_hash;
		$submitted_hash = (string) $row->submitted_translation_hash;

		if ( $current_hash !== $submitted_hash ) {
			return false;
		}

		if ( null !== $client_submitted_hash && '' !== $client_submitted_hash && $client_submitted_hash !== $submitted_hash ) {
			return false;
		}

		return true;
	}

	/**
	 * Persists review metadata via Store and maps failures to domain errors.
	 *
	 * @param string               $source_type Source type.
	 * @param int                  $source_id   Source object id.
	 * @param int                  $language_id Language id.
	 * @param string               $segment_key Segment key.
	 * @param array<string, mixed> $fields      Review fields.
	 *
	 * @throws ReviewWorkflowException When persistence fails.
	 */
	private function persist_review_metadata(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		array $fields
	): void {
		$result = $this->store->update_review_metadata(
			$source_type,
			$source_id,
			$language_id,
			$segment_key,
			$fields
		);

		if ( $result instanceof WP_Error ) {
			$this->raise( self::CODE_SERVICE_ERROR, $result->get_error_message() );
		}
	}

	/**
	 * Reloads a segment after a write.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Segment key.
	 * @return object Store row.
	 *
	 * @throws ReviewWorkflowException When the segment disappears after update.
	 */
	private function refresh_segment(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key
	): object {
		$row = $this->store->get( $source_type, $source_id, $language_id, $segment_key );

		if ( null === $row ) {
			$this->raise( self::CODE_SERVICE_ERROR, 'Translation segment missing after review update.' );
		}

		return $row;
	}

	/**
	 * Raises a domain exception (instance helper).
	 *
	 * @param string               $error_code Stable error code.
	 * @param string               $message    Human-readable message.
	 * @param array<string, mixed> $context    Optional structured context.
	 * @param int                  $code       Suggested HTTP status (0 = unspecified).
	 *
	 * @throws ReviewWorkflowException Always.
	 */
	private function raise(
		string $error_code,
		string $message,
		array $context = array(),
		int $code = 0
	): never {
		self::raise_static( $error_code, $message, $context, $code );
	}

	/**
	 * Raises a domain exception (static helper for normalize_rejection_reason).
	 *
	 * @param string               $error_code Stable error code.
	 * @param string               $message    Human-readable message.
	 * @param array<string, mixed> $context    Optional structured context.
	 * @param int                  $code       Suggested HTTP status (0 = unspecified).
	 *
	 * @throws ReviewWorkflowException Always.
	 */
	private static function raise_static(
		string $error_code,
		string $message,
		array $context = array(),
		int $code = 0
	): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- domain codes and context are structured data, not HTML output.
		throw new ReviewWorkflowException( $error_code, $message, $context, $code );
	}

	/**
	 * Builds the safe audit payload shared by submit/approve/reject events.
	 *
	 * Safe fields only (ADR-0015 §12): source/post id, segment key,
	 * language id, old/new review status, user id, timestamp, source
	 * surface, and a non-reversible submitted-hash fingerprint — never
	 * translation text.
	 *
	 * @param string $source_type   Source type.
	 * @param int    $source_id     Source object id.
	 * @param int    $language_id   Language id.
	 * @param string $segment_key   Segment key.
	 * @param string $old_status    Previous review_status.
	 * @param string $new_status    New review_status.
	 * @param int    $user_id       Acting user id.
	 * @param string $submitted_hash Submitted translation hash for a safe fingerprint.
	 * @return array<string, mixed>
	 */
	private function audit_payload(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		string $old_status,
		string $new_status,
		int $user_id,
		string $submitted_hash
	): array {
		$payload = array(
			'source_type'                => $source_type,
			'source_id'                  => $source_id,
			'segment_key'                => $segment_key,
			'language_id'                => $language_id,
			'old_review_status'          => $old_status,
			'new_review_status'          => $new_status,
			'user_id'                    => $user_id,
			'source_surface'             => 'workspace',
			'submitted_hash_fingerprint' => ReviewAuditLogger::hash_fingerprint( $submitted_hash ),
		);

		if ( Store::SOURCE_POST === $source_type ) {
			$payload['post_id'] = $source_id;
		}

		return $payload;
	}

	/**
	 * Returns review-axis fields from a segment row for conflict responses.
	 *
	 * @param object $row Store row.
	 * @return array<string, mixed>
	 */
	private function segment_review_context( object $row ): array {
		return array(
			'review_status'              => (string) ( $row->review_status ?? Store::REVIEW_NOT_SUBMITTED ),
			'submitted_translation_hash' => (string) ( $row->submitted_translation_hash ?? '' ),
			'translation_hash'           => (string) ( $row->translation_hash ?? '' ),
			'review_submitted_by'        => $row->review_submitted_by ?? null,
			'review_submitted_at'        => $row->review_submitted_at ?? null,
			'reviewed_by'                => $row->reviewed_by ?? null,
			'reviewed_at'                => $row->reviewed_at ?? null,
			'rejection_reason'           => (string) ( $row->rejection_reason ?? '' ),
			'rejected_by'                => $row->rejected_by ?? null,
			'rejected_at'                => $row->rejected_at ?? null,
		);
	}
}
