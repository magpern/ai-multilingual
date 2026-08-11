<?php
/**
 * Assembles computed OTL operator translation read models.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\Operator;

use AIMultilingual\Language\Languages;
use AIMultilingual\Translation\AI\FieldSemanticMapper;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Publication\PublicationDecision;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\PreviewService;
use AIMultilingual\Workspace\QA\QAEngine;
use WP_Error;
use WP_Post;

/**
 * Computed, non-persisted operator translation read model (OTL.0).
 *
 * Composes Store / TI.4 / TI.5 / TI.7 — does not own policy.
 */
final class OperatorTranslationAssembler {

	public const PREVIEW_MAX_CHARS = 200;

	/**
	 * Invocation counters for list/detail performance assertions.
	 *
	 * @var array{assessment: int, publication_explain: int, qa: int}
	 */
	private array $invocation_counts = array(
		'assessment'          => 0,
		'publication_explain' => 0,
		'qa'                  => 0,
	);

	/**
	 * Builds the operator translation assembler.
	 *
	 * @param Store                   $store              Translation store.
	 * @param Languages               $languages          Language catalog.
	 * @param AllowedActionsResolver  $actions            UI admission resolver.
	 * @param PreviewService          $preview            Preview URLs.
	 * @param AssessmentAssembler     $assessment         TI.5 assessment.
	 * @param QAEngine                $qa                 Workspace QA engine.
	 * @param FieldSemanticMapper     $field_semantic     Field semantic mapper.
	 * @param PublicationService|null $publication        TI.7 publication service.
	 */
	public function __construct(
		private Store $store,
		private Languages $languages,
		private AllowedActionsResolver $actions,
		private PreviewService $preview,
		private AssessmentAssembler $assessment,
		private QAEngine $qa,
		private FieldSemanticMapper $field_semantic,
		private ?PublicationService $publication = null,
	) {}

	/**
	 * Resets invocation counters (tests / list assertions).
	 */
	public function reset_invocation_counts(): void {
		$this->invocation_counts = array(
			'assessment'          => 0,
			'publication_explain' => 0,
			'qa'                  => 0,
		);
	}

	/**
	 * Returns current TI.4/TI.5/TI.7 invocation counters.
	 *
	 * @return array{assessment: int, publication_explain: int, qa: int}
	 */
	public function invocation_counts(): array {
		return $this->invocation_counts;
	}

	/**
	 * Assembles a cheap list item from a hydrated Store row.
	 *
	 * Must not call AssessmentAssembler, PublicationService::explain, or QAEngine.
	 *
	 * @param object $row Hydrated Store row.
	 * @return array<string, mixed>
	 */
	public function assemble_list_item( object $row ): array {
		$post  = $this->post_for_row( $row );
		$caps  = AllowedActionsResolver::capability_flags( null, $post );
		$links = $this->links_for_row( $row, $post, false );
		$lang  = $this->languages->find( (int) $row->language_id );

		return array(
			'translation_id'  => (int) $row->translation_id,
			'source_type'     => (string) $row->source_type,
			'source_id'       => (int) $row->source_id,
			'source_subtype'  => (string) ( $row->source_subtype ?? '' ),
			'language_id'     => (int) $row->language_id,
			'language_code'   => $lang ? (string) $lang->code : '',
			'segment_key'     => (string) $row->segment_key,
			'field_key'       => (string) ( $row->field_key ?? '' ),
			'status'          => (string) ( $row->status ?? '' ),
			'review_status'   => (string) ( $row->review_status ?? Store::REVIEW_NOT_SUBMITTED ),
			'publish_status'  => (string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED ),
			'is_stale'          => (bool) ( $row->is_stale ?? false ),
			'attention_reasons' => OperationalAttention::reasons_for_row( $row ),
			'source_preview'    => self::preview_text( (string) ( $row->source_text ?? '' ) ),
			'target_preview'    => self::preview_text( (string) ( $row->translated_text ?? '' ) ),
			'updated_at'        => (string) ( $row->updated_at ?? '' ),
			'created_at'        => (string) ( $row->created_at ?? '' ),
			'provider'          => (string) ( $row->provider ?? '' ),
			'model'             => (string) ( $row->model ?? '' ),
			'error_code'        => (string) ( $row->error_code ?? '' ),
			'error_message'     => (string) ( $row->error_message ?? '' ),
			'links'             => $links,
			'allowed_actions'   => $this->actions->resolve_for_list( $row, $caps, $links ),
			'jobs'              => null,
		);
	}

	/**
	 * Assembles a rich detail model (authoritative TI.4 / TI.5 / TI.7).
	 *
	 * QA reuse: path B — call AssessmentAssembler, QAEngine, and PublicationService::explain
	 * independently without redesigning TI.4/TI.5 (shared-detect deferred as debt).
	 *
	 * @param object $row Hydrated Store row.
	 * @return array<string, mixed>|WP_Error
	 */
	public function assemble_detail( object $row ) {
		$post = $this->post_for_row( $row );
		if ( Store::SOURCE_POST === (string) $row->source_type && ! ( $post instanceof WP_Post ) ) {
			return new WP_Error( 'aiml_source_missing', __( 'Source object not found.', 'ai-multilingual' ), array( 'status' => 404 ) );
		}

		$caps = AllowedActionsResolver::capability_flags( null, $post );
		if ( $post instanceof WP_Post && empty( $caps['can_edit_source'] ) ) {
			return new WP_Error( 'aiml_forbidden', __( 'You cannot access this translation.', 'ai-multilingual' ), array( 'status' => 403 ) );
		}

		$links   = $this->links_for_row( $row, $post, true );
		$lang    = $this->languages->find( (int) $row->language_id );
		$default = $this->languages->default();

		$segment = array(
			'segment_key'     => (string) $row->segment_key,
			'field_key'       => (string) ( $row->field_key ?? '' ),
			'source_text'     => (string) ( $row->source_text ?? '' ),
			'translated_text' => (string) ( $row->translated_text ?? '' ),
			'text_format'     => (string) ( $row->text_format ?? Store::FORMAT_PLAIN ),
			'status'          => (string) ( $row->status ?? '' ),
			'review_status'   => (string) ( $row->review_status ?? Store::REVIEW_NOT_SUBMITTED ),
			'publish_status'  => (string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED ),
			'is_stale'        => (bool) ( $row->is_stale ?? false ),
			'source_subtype'  => (string) ( $row->source_subtype ?? '' ),
			'provider'        => (string) ( $row->provider ?? '' ),
			'model'           => (string) ( $row->model ?? '' ),
		);

		$assessment = $this->assessment->assess_segment(
			$segment,
			$this->field_semantic->map( $segment, (string) ( $segment['source_subtype'] ?? '' ) ),
			array(),
			false
		)->to_array();
		++$this->invocation_counts['assessment'];

		$qa = $this->qa->evaluate(
			(string) $segment['source_text'],
			(string) $segment['translated_text'],
			(string) $segment['text_format'],
			array(
				'source_language_id' => $default ? (int) $default->language_id : 0,
				'target_language_id' => (int) $row->language_id,
			)
		)->to_array();
		++$this->invocation_counts['qa'];

		$publication       = null;
		$publication_array = null;
		if ( $this->publication instanceof PublicationService ) {
			$decision = $this->publication->explain(
				(string) $row->source_type,
				(int) $row->source_id,
				(int) $row->language_id,
				(string) $row->segment_key
			);
			++$this->invocation_counts['publication_explain'];
			if ( $decision instanceof PublicationDecision ) {
				$publication       = $decision;
				$publication_array = $decision->to_array();
			} elseif ( is_wp_error( $decision ) ) {
				$publication_array = array(
					'error_code'    => $decision->get_error_code(),
					'error_message' => $decision->get_error_message(),
				);
			}
		}

		return array(
			'translation_id'      => (int) $row->translation_id,
			'source_type'         => (string) $row->source_type,
			'source_id'           => (int) $row->source_id,
			'source_subtype'      => (string) ( $row->source_subtype ?? '' ),
			'language_id'         => (int) $row->language_id,
			'language_code'       => $lang ? (string) $lang->code : '',
			'segment_key'         => (string) $row->segment_key,
			'field_key'           => (string) ( $row->field_key ?? '' ),
			'status'              => (string) ( $row->status ?? '' ),
			'review_status'       => (string) ( $row->review_status ?? Store::REVIEW_NOT_SUBMITTED ),
			'publish_status'      => (string) ( $row->publish_status ?? Store::PUBLISH_UNPUBLISHED ),
			'is_stale'            => (bool) ( $row->is_stale ?? false ),
			'source_text'         => (string) ( $row->source_text ?? '' ),
			'translated_text'     => (string) ( $row->translated_text ?? '' ),
			'text_format'         => (string) ( $row->text_format ?? Store::FORMAT_PLAIN ),
			'source_hash'         => (string) ( $row->source_hash ?? '' ),
			'updated_at'          => (string) ( $row->updated_at ?? '' ),
			'created_at'          => (string) ( $row->created_at ?? '' ),
			'review_submitted_at' => (string) ( $row->review_submitted_at ?? '' ),
			'reviewed_at'         => (string) ( $row->reviewed_at ?? '' ),
			'published_at'        => (string) ( $row->published_at ?? '' ),
			'published_by'        => isset( $row->published_by ) ? (int) $row->published_by : null,
			'provider'            => (string) ( $row->provider ?? '' ),
			'model'               => (string) ( $row->model ?? '' ),
			'prompt_profile'      => (string) ( $row->prompt_profile ?? '' ),
			'tm_id'               => isset( $row->tm_id ) ? (int) $row->tm_id : null,
			'error_code'          => (string) ( $row->error_code ?? '' ),
			'error_message'       => (string) ( $row->error_message ?? '' ),
			'qa'                  => $qa,
			'assessment'          => $assessment,
			'publication'         => $publication_array,
			'links'               => $links,
			'allowed_actions'     => $this->actions->resolve_for_detail( $row, $caps, $publication, $links ),
			'jobs'                => null,
		);
	}

	/**
	 * Unicode-safe preview truncation.
	 *
	 * @param string $text Source text.
	 */
	public static function preview_text( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		$text = trim( $text );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $text ) <= self::PREVIEW_MAX_CHARS ) {
				return $text;
			}

			return rtrim( mb_substr( $text, 0, self::PREVIEW_MAX_CHARS ) ) . '…';
		}
		if ( strlen( $text ) <= self::PREVIEW_MAX_CHARS ) {
			return $text;
		}

		return rtrim( substr( $text, 0, self::PREVIEW_MAX_CHARS ) ) . '…';
	}

	/**
	 * Resolves the post for a post-backed Store row.
	 *
	 * @param object $row Store row.
	 */
	private function post_for_row( object $row ): ?WP_Post {
		if ( Store::SOURCE_POST !== (string) ( $row->source_type ?? '' ) ) {
			return null;
		}
		$post = get_post( (int) $row->source_id );

		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Builds navigation links for a translation row.
	 *
	 * @param object       $row          Row.
	 * @param WP_Post|null $post         Post.
	 * @param bool         $with_preview Whether to generate frontend URLs.
	 * @return array<string, mixed>
	 */
	private function links_for_row( object $row, ?WP_Post $post, bool $with_preview ): array {
		$links = array(
			'edit_link'           => null,
			'source_frontend_url' => null,
			'frontend_url'        => null,
		);

		if ( ! ( $post instanceof WP_Post ) ) {
			return $links;
		}

		$edit               = get_edit_post_link( (int) $post->ID, 'raw' );
		$links['edit_link'] = is_string( $edit ) && '' !== $edit ? $edit : null;

		if ( ! $with_preview ) {
			// Cheap list: permalink without language routing is still useful as source frontend.
			$permalink                    = get_permalink( $post );
			$links['source_frontend_url'] = is_string( $permalink ) && '' !== $permalink ? $permalink : null;

			return $links;
		}

		$default = $this->languages->default();
		if ( $default ) {
			$source_url                   = $this->preview->preview_url( $post, (string) $default->code );
			$links['source_frontend_url'] = is_string( $source_url ) ? $source_url : null;
		}

		$lang = $this->languages->find( (int) $row->language_id );
		if ( $lang ) {
			$target_url            = $this->preview->preview_url( $post, (string) $lang->code );
			$links['frontend_url'] = is_string( $target_url ) ? $target_url : null;
		}

		return $links;
	}
}
