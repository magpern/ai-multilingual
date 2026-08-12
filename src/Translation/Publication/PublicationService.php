<?php
/**
 * Canonical TI.7 PublicationService (ADR-0020).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Publication;

use AIMultilingual\Settings;
use AIMultilingual\Surface\SurfaceCapabilityNames;
use AIMultilingual\Surface\SurfaceRegistry;
use AIMultilingual\Translation\AI\FieldSemantic;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermCompatRef;
use AIMultilingual\Translation\TermTranslationResolver;
use WP_Error;
use WP_Post;

/**
 * Applies publication transitions after re-checking current eligibility.
 */
final class PublicationService {

	/**
	 * Builds the publication service.
	 *
	 * @param Store                        $store      Translation store.
	 * @param AssessmentAssembler          $assembler  TI.5 assessment assembler.
	 * @param PublicationPolicy            $policy     Pure eligibility policy.
	 * @param PublicationAuditLogger       $audit      Bounded audit logger.
	 * @param Settings|null                $settings   Plugin settings.
	 * @param SurfaceRegistry|null         $surfaces   Optional surface registry for visibility facts.
	 * @param TermTranslationResolver|null $terms Optional term address resolver (TSC.1).
	 */
	public function __construct(
		private Store $store,
		private AssessmentAssembler $assembler,
		private PublicationPolicy $policy,
		private PublicationAuditLogger $audit,
		private ?Settings $settings = null,
		private ?SurfaceRegistry $surfaces = null,
		private ?TermTranslationResolver $terms = null,
	) {
		$this->settings = $settings ?? new Settings();
	}

	/**
	 * Explains eligibility without mutation.
	 *
	 * @param string             $source_type         Source type.
	 * @param int                $source_id           Source id.
	 * @param int                $language_id         Language id.
	 * @param string             $segment_key         Segment key.
	 * @param bool               $for_automatic       Treat as automatic path.
	 * @param array<int, string> $scaffolding_markers Optional request markers for TI.5.
	 * @param bool|null          $markers_applicable  Whether leakage evidence applies.
	 * @return PublicationDecision|WP_Error
	 */
	public function explain(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		bool $for_automatic = false,
		array $scaffolding_markers = array(),
		?bool $markers_applicable = null
	) {
		$term_ref = $this->term_ref( $source_type, $source_id, $language_id, $segment_key );
		if ( null !== $term_ref ) {
			[ $source_type, $source_id, $segment_key ] = $this->authoritative_address( $term_ref, $source_type, $source_id, $segment_key );
		}

		$row = $this->store->get( $source_type, $source_id, $language_id, $segment_key );
		if ( null === $row ) {
			return new WP_Error( 'aiml_segment_missing', __( 'Translation segment not found.', 'ai-multilingual' ) );
		}

		return $this->evaluate_row( $row, $for_automatic, $scaffolding_markers, $markers_applicable );
	}

	/**
	 * Attempts automatic publication after successful translation persistence.
	 *
	 * @param string             $source_type         Source type.
	 * @param int                $source_id           Source id.
	 * @param int                $language_id         Language id.
	 * @param string             $segment_key         Segment key.
	 * @param array<int, string> $scaffolding_markers Optional generation-path markers.
	 * @param bool|null          $markers_applicable  Whether leakage evidence applies.
	 * @return array<string, mixed> Bounded publication result.
	 */
	public function maybe_auto_publish(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		array $scaffolding_markers = array(),
		?bool $markers_applicable = null
	): array {
		$mode = $this->current_mode();
		if ( PublicationMode::MANUAL === $mode ) {
			$this->audit->log(
				PublicationAuditEvents::SKIPPED,
				array(
					'source_type'    => $source_type,
					'source_id'      => $source_id,
					'segment_key'    => $segment_key,
					'language_id'    => $language_id,
					'mode'           => $mode,
					'reason_codes'   => array( PublicationReasonCodes::AUTOMATION_DISABLED ),
					'actor_kind'     => 'system',
					'source_surface' => 'auto',
				)
			);

			return array(
				'status'       => 'skipped',
				'reason_codes' => array( PublicationReasonCodes::AUTOMATION_DISABLED ),
				'mode'         => $mode,
			);
		}

		$result = $this->publish(
			$source_type,
			$source_id,
			$language_id,
			$segment_key,
			true,
			0,
			'auto',
			null,
			$scaffolding_markers,
			$markers_applicable
		);

		if ( $result instanceof WP_Error ) {
			$this->audit->log(
				PublicationAuditEvents::FAILED,
				array(
					'source_type'    => $source_type,
					'source_id'      => $source_id,
					'segment_key'    => $segment_key,
					'language_id'    => $language_id,
					'mode'           => $mode,
					'reason_codes'   => array( (string) $result->get_error_code() ),
					'actor_kind'     => 'system',
					'source_surface' => 'auto',
				)
			);

			return array(
				'status'        => 'failed',
				'error_code'    => $result->get_error_code(),
				'error_message' => $result->get_error_message(),
				'mode'          => $mode,
			);
		}

		return $result;
	}

	/**
	 * Publishes a translation when eligible.
	 *
	 * @param string             $source_type         Source type.
	 * @param int                $source_id           Source id.
	 * @param int                $language_id         Language id.
	 * @param string             $segment_key         Segment key.
	 * @param bool               $for_automatic       Automatic path.
	 * @param int                $user_id             Acting user (0 = system).
	 * @param string             $surface             Audit surface.
	 * @param string|null        $expected_status     Optional optimistic publish_status.
	 * @param array<int, string> $scaffolding_markers Optional generation-path markers.
	 * @param bool|null          $markers_applicable  Whether leakage evidence applies.
	 * @return array<string, mixed>|WP_Error
	 */
	public function publish(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		bool $for_automatic = false,
		int $user_id = 0,
		string $surface = 'manual',
		?string $expected_status = null,
		array $scaffolding_markers = array(),
		?bool $markers_applicable = null
	) {
		$row = $this->store->get( $source_type, $source_id, $language_id, $segment_key );
		if ( null === $row ) {
			return new WP_Error( 'aiml_segment_missing', __( 'Translation segment not found.', 'ai-multilingual' ) );
		}

		$decision = $this->evaluate_publish_attempt(
			$row,
			$for_automatic,
			$expected_status,
			$scaffolding_markers,
			$markers_applicable,
			$user_id,
			$surface
		);
		if ( $decision instanceof WP_Error || is_array( $decision ) ) {
			return $decision;
		}

		// ADR-0020: re-read and re-evaluate immediately before mutation so a
		// concurrent edit / visibility / review change cannot apply a stale decision.
		$row = $this->store->get( $source_type, $source_id, $language_id, $segment_key );
		if ( null === $row ) {
			return new WP_Error( 'aiml_segment_missing', __( 'Translation segment not found.', 'ai-multilingual' ) );
		}

		$decision = $this->evaluate_publish_attempt(
			$row,
			$for_automatic,
			$expected_status,
			$scaffolding_markers,
			$markers_applicable,
			$user_id,
			$surface
		);
		if ( $decision instanceof WP_Error || is_array( $decision ) ) {
			return $decision;
		}

		$current = (string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED );
		$now     = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
		$by      = $for_automatic ? 0 : ( $user_id > 0 ? $user_id : (int) get_current_user_id() );

		$updated = $this->store->update_publish_metadata(
			$source_type,
			$source_id,
			$language_id,
			$segment_key,
			array(
				'publish_status' => Store::PUBLISH_PUBLISHED,
				'published_at'   => $now,
				'published_by'   => $by,
			)
		);

		if ( $updated instanceof WP_Error ) {
			return $updated;
		}

		$event = $for_automatic ? PublicationAuditEvents::AUTO : PublicationAuditEvents::MANUAL;
		$this->audit->log(
			$event,
			array(
				'source_type'        => $source_type,
				'source_id'          => $source_id,
				'segment_key'        => $segment_key,
				'language_id'        => $language_id,
				'old_publish_status' => $current,
				'new_publish_status' => Store::PUBLISH_PUBLISHED,
				'policy_version'     => $decision->policy_version,
				'assessment_version' => $decision->assessment_version,
				'overall_category'   => $decision->overall_category,
				'reason_codes'       => array( PublicationReasonCodes::PUBLISHED ),
				'mode'               => $decision->mode,
				'actor_kind'         => $for_automatic ? 'system' : 'user',
				'user_id'            => $by,
				'source_surface'     => $surface,
			)
		);

		return array(
			'status'         => 'published',
			'publish_status' => Store::PUBLISH_PUBLISHED,
			'decision'       => $decision->to_array(),
			'reason_codes'   => array( PublicationReasonCodes::PUBLISHED ),
		);
	}

	/**
	 * Evaluates one publish attempt; returns Decision when mutation may proceed,
	 * or a bounded result/WP_Error when it must not.
	 *
	 * @param object             $row                  Store row.
	 * @param bool               $for_automatic        Automatic path.
	 * @param string|null        $expected_status      Optional optimistic publish_status.
	 * @param array<int, string> $scaffolding_markers  Optional markers.
	 * @param bool|null          $markers_applicable   Marker applicability.
	 * @param int                $user_id              Acting user.
	 * @param string             $surface              Audit surface.
	 * @return PublicationDecision|array<string, mixed>|WP_Error
	 */
	private function evaluate_publish_attempt(
		object $row,
		bool $for_automatic,
		?string $expected_status,
		array $scaffolding_markers,
		?bool $markers_applicable,
		int $user_id = 0,
		string $surface = 'manual'
	) {
		$current = (string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED );
		if ( null !== $expected_status && $expected_status !== $current ) {
			return new WP_Error(
				'aiml_publish_conflict',
				__( 'Publish status changed; refresh and retry.', 'ai-multilingual' ),
				array( 'reason_codes' => array( PublicationReasonCodes::EXPECTED_STATUS_MISMATCH ) )
			);
		}

		$decision = $this->evaluate_row( $row, $for_automatic, $scaffolding_markers, $markers_applicable );
		if ( ! $decision->eligible ) {
			$this->audit->log(
				PublicationAuditEvents::SKIPPED,
				array(
					'source_type'        => (string) ( $row->source_type ?? Store::SOURCE_POST ),
					'source_id'          => (int) ( $row->source_id ?? 0 ),
					'segment_key'        => (string) ( $row->segment_key ?? '' ),
					'language_id'        => (int) ( $row->language_id ?? 0 ),
					'old_publish_status' => $current,
					'new_publish_status' => $current,
					'policy_version'     => $decision->policy_version,
					'assessment_version' => $decision->assessment_version,
					'overall_category'   => $decision->overall_category,
					'reason_codes'       => $decision->reason_codes,
					'mode'               => $decision->mode,
					'actor_kind'         => $for_automatic ? 'system' : 'user',
					'user_id'            => $user_id,
					'source_surface'     => $surface,
				)
			);

			return array(
				'status'       => 'skipped',
				'decision'     => $decision->to_array(),
				'reason_codes' => $decision->reason_codes,
			);
		}

		if ( Store::PUBLISH_PUBLISHED === $current ) {
			return array(
				'status'       => 'noop',
				'decision'     => $decision->to_array(),
				'reason_codes' => array( PublicationReasonCodes::PUBLICATION_ALREADY_ACTIVE ),
			);
		}

		return $decision;
	}

	/**
	 * Manually unpublishes a translation.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Segment key.
	 * @param int    $user_id     Acting user.
	 * @return array<string, mixed>|WP_Error
	 */
	public function unpublish(
		string $source_type,
		int $source_id,
		int $language_id,
		string $segment_key,
		int $user_id = 0
	) {
		$row = $this->store->get( $source_type, $source_id, $language_id, $segment_key );
		if ( null === $row ) {
			return new WP_Error( 'aiml_segment_missing', __( 'Translation segment not found.', 'ai-multilingual' ) );
		}

		$current = (string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED );
		if ( Store::PUBLISH_UNPUBLISHED === $current ) {
			return array(
				'status'         => 'noop',
				'publish_status' => Store::PUBLISH_UNPUBLISHED,
				'reason_codes'   => array( PublicationReasonCodes::UNPUBLISHED ),
			);
		}

		$updated = $this->store->update_publish_metadata(
			$source_type,
			$source_id,
			$language_id,
			$segment_key,
			Store::publish_clear_fields()
		);

		if ( $updated instanceof WP_Error ) {
			return $updated;
		}

		$by = $user_id > 0 ? $user_id : (int) get_current_user_id();
		$this->audit->log(
			PublicationAuditEvents::UNPUBLISH_MANUAL,
			array(
				'source_type'        => $source_type,
				'source_id'          => $source_id,
				'segment_key'        => $segment_key,
				'language_id'        => $language_id,
				'old_publish_status' => $current,
				'new_publish_status' => Store::PUBLISH_UNPUBLISHED,
				'reason_codes'       => array( PublicationReasonCodes::UNPUBLISHED ),
				'actor_kind'         => 'user',
				'user_id'            => $by,
				'source_surface'     => 'manual',
			)
		);

		return array(
			'status'         => 'unpublished',
			'publish_status' => Store::PUBLISH_UNPUBLISHED,
			'reason_codes'   => array( PublicationReasonCodes::UNPUBLISHED ),
		);
	}

	/**
	 * Evaluates publication eligibility for a Store row.
	 *
	 * @param object             $row                  Store row.
	 * @param bool               $for_automatic        Automatic path.
	 * @param array<int, string> $scaffolding_markers  Optional generation-path markers.
	 * @param bool|null          $markers_applicable   Whether leakage evidence applies.
	 */
	private function evaluate_row(
		object $row,
		bool $for_automatic,
		array $scaffolding_markers = array(),
		?bool $markers_applicable = null
	): PublicationDecision {
		$applicable = null !== $markers_applicable
			? $markers_applicable
			: ( array() !== $scaffolding_markers );

		$assessment = $this->assembler->assess_segment(
			array(
				'source_text'     => (string) ( $row->source_text ?? '' ),
				'translated_text' => (string) ( $row->translated_text ?? '' ),
				'text_format'     => (string) ( $row->text_format ?? Store::FORMAT_PLAIN ),
				'status'          => (string) ( $row->status ?? Store::STATUS_MISSING ),
				'review_status'   => (string) ( $row->review_status ?? Store::REVIEW_NOT_SUBMITTED ),
				'provider'        => (string) ( $row->provider ?? '' ),
				'model'           => (string) ( $row->model ?? '' ),
				'prompt_profile'  => (string) ( $row->prompt_profile ?? '' ),
				'prompt_version'  => (string) ( $row->prompt_version ?? '' ),
				'tm_id'           => isset( $row->tm_id ) ? (int) $row->tm_id : null,
			),
			FieldSemantic::GENERIC,
			array_values( array_map( 'strval', $scaffolding_markers ) ),
			$applicable
		);

		return $this->policy->evaluate(
			$assessment,
			$this->current_mode(),
			(string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED ),
			(string) ( $row->review_status ?? Store::REVIEW_NOT_SUBMITTED ),
			$this->is_source_public( (string) ( $row->source_type ?? Store::SOURCE_POST ), (int) ( $row->source_id ?? 0 ) ),
			! empty( $row->is_stale ),
			$for_automatic
		);
	}

	/**
	 * Current auto_publication_mode from settings.
	 */
	public function current_mode(): string {
		$all  = $this->settings->get();
		$mode = (string) ( $all['auto_publication_mode'] ?? PublicationMode::MANUAL );

		return PublicationMode::is_valid( $mode ) ? $mode : PublicationMode::MANUAL;
	}

	/**
	 * Whether the source object is publicly visible (visibility fact).
	 *
	 * Delegates to SurfaceCapability when registered. TI.7 publication policy
	 * remains owned by PublicationPolicy — this method never answers can_publish.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 */
	public function is_source_public( string $source_type, int $source_id ): bool {
		if ( $source_id <= 0 ) {
			return false;
		}

		if ( null !== $this->surfaces ) {
			$surface = $this->surfaces->for( $source_type );
			if ( null === $surface ) {
				return false;
			}
			if ( ! $surface->supports( SurfaceCapabilityNames::PUBLISH_INPUTS ) ) {
				return false;
			}
			return $surface->is_visitor_public( $source_id );
		}

		// Legacy fallback when registry is not wired (unit tests).
		if ( Store::SOURCE_POST !== $source_type || ! function_exists( 'get_post' ) ) {
			return false;
		}

		$post = get_post( $source_id );
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( 'trash' === $post->post_status || 'auto-draft' === $post->post_status ) {
			return false;
		}

		return 'publish' === $post->post_status;
	}
}
