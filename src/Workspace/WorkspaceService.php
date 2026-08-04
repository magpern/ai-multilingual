<?php
/**
 * Translator workspace application facade.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use AIMultilingual\Language\Languages;
use AIMultilingual\Plugin;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Memory\TranslationMemoryService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\QA\QAEngine;
use AIMultilingual\Workspace\QA\QAIssue;
use AIMultilingual\Workspace\QA\QAResult;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Orchestration entry point for workspace queries and commands.
 */
final class WorkspaceService {

	public const SUPPORTED_POST_TYPES = array( 'post', 'page' );

	/**
	 * Injected dependency.
	 *
	 * @var SegmentAssembler
	 */
	private SegmentAssembler $assembler;

	/**
	 * Injected dependency.
	 *
	 * @var TranslationStatusCalculator
	 */
	private TranslationStatusCalculator $status_calculator;

	/**
	 * Injected dependency.
	 *
	 * @var BatchOperationCoordinator
	 */
	private BatchOperationCoordinator $batch;

	/**
	 * Injected dependency.
	 *
	 * @var TranslationService
	 */
	private TranslationService $translation;

	/**
	 * Injected dependency.
	 *
	 * @var PreviewService
	 */
	private PreviewService $preview;

	/**
	 * Injected dependency.
	 *
	 * @var Languages
	 */
	private Languages $languages;

	/**
	 * Injected dependency.
	 *
	 * @var Store
	 */
	private Store $store;

	/**
	 * Injected dependency.
	 *
	 * @var Extractor
	 */
	private Extractor $extractor;

	/**
	 * Injected dependency.
	 *
	 * @var TranslationSuggestionService
	 */
	private TranslationSuggestionService $suggestions;

	/**
	 * Injected dependency.
	 *
	 * @var QAEngine
	 */
	private QAEngine $qa;

	/**
	 * Injected dependency.
	 *
	 * @var TranslationMemoryService
	 */
	private TranslationMemoryService $tm;

	/**
	 * Builds the collaborator.
	 *
	 * @param SegmentAssembler             $assembler           Segment assembly.
	 * @param TranslationStatusCalculator  $status_calculator   Status aggregation.
	 * @param TranslationService           $translation         Auto-translate boundary.
	 * @param PreviewService               $preview             Preview URLs.
	 * @param Languages                    $languages           Language registry.
	 * @param Store                        $store               Segment store.
	 * @param Extractor                    $extractor           Source extractor.
	 * @param TranslationSuggestionService $suggestions         Suggestion orchestration.
	 * @param QAEngine                     $qa                  Quality assurance engine.
	 * @param TranslationMemoryService     $tm                  Translation memory write-back.
	 */
	public function __construct(
		SegmentAssembler $assembler,
		TranslationStatusCalculator $status_calculator,
		TranslationService $translation,
		PreviewService $preview,
		Languages $languages,
		Store $store,
		Extractor $extractor,
		TranslationSuggestionService $suggestions,
		QAEngine $qa,
		TranslationMemoryService $tm
	) {
		$this->assembler         = $assembler;
		$this->status_calculator = $status_calculator;
		$this->translation       = $translation;
		$this->preview           = $preview;
		$this->languages         = $languages;
		$this->store             = $store;
		$this->extractor         = $extractor;
		$this->suggestions       = $suggestions;
		$this->qa                = $qa;
		$this->tm                = $tm;
		$this->batch             = new BatchOperationCoordinator( $this, $translation );
	}

	/**
	 * Returns the bulk operation coordinator.
	 *
	 * @return BatchOperationCoordinator
	 */
	public function batch_coordinator(): BatchOperationCoordinator {
		return $this->batch;
	}

	/**
	 * Lists posts/pages for the workspace picker.
	 *
	 * @param array<string, mixed> $args Query args: search, page, per_page, language.
	 * @return array<string, mixed>
	 */
	public function list_pages( array $args ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$search   = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		$language = $this->resolve_language( (string) ( $args['language'] ?? '' ) );

		$query = new WP_Query(
			array(
				'post_type'              => self::SUPPORTED_POST_TYPES,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				's'                      => $search,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$summaries = $this->status_calculator->summaries_for_post( (int) $post->ID );
			$lang_id   = null !== $language ? (int) $language->language_id : 0;
			$summary   = $lang_id > 0 ? ( $summaries[ $lang_id ] ?? array(
				'total' => 0,
				'stale' => 0,
			) ) : array(
				'total' => 0,
				'stale' => 0,
			);

			$items[] = array(
				'post_id'        => (int) $post->ID,
				'post_title'     => (string) $post->post_title,
				'post_type'      => (string) $post->post_type,
				'post_status'    => (string) $post->post_status,
				'modified_gmt'   => (string) $post->post_modified_gmt,
				'language_id'    => $lang_id,
				'total_segments' => (int) ( $summary['total'] ?? 0 ),
				'stale_count'    => (int) ( $summary['stale'] ?? 0 ),
			);
		}

		return array(
			'items'       => $items,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Loads segment DTOs for one post.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @return array<int, array<string, mixed>>
	 */
	public function load_segments( WP_Post $post, int $language_id ): array {
		$this->assert_supported_post( $post );

		$segments = $this->assembler->assemble_for_post( $post, $language_id );

		return $this->attach_meta( $segments, $language_id );
	}

	/**
	 * Returns page-level status DTO.
	 *
	 * @param WP_Post $post        Canonical post.
	 * @param int     $language_id Target language id.
	 * @return array<string, mixed>
	 */
	public function page_status( WP_Post $post, int $language_id ): array {
		$this->assert_supported_post( $post );

		return $this->page_status_for_segments(
			$post,
			$language_id,
			$this->assembler->assemble_for_post( $post, $language_id )
		);
	}

	/**
	 * Page status derived from already-assembled segment DTOs.
	 *
	 * @param WP_Post                          $post        Canonical post.
	 * @param int                              $language_id Target language id.
	 * @param array<int, array<string, mixed>> $segments    Assembled segment DTOs.
	 * @return array<string, mixed>
	 */
	public function page_status_for_segments( WP_Post $post, int $language_id, array $segments ): array {
		$this->assert_supported_post( $post );

		return $this->status_calculator->for_segments( $post, $language_id, $segments );
	}

	/**
	 * Operation handler.
	 *
	 * @param WP_Post $post          Canonical post.
	 * @param string  $language_code Target language code.
	 * @return string|WP_Error
	 */
	public function preview_url( WP_Post $post, string $language_code ) {
		$this->assert_supported_post( $post );

		return $this->preview->preview_url( $post, $language_code );
	}

	/**
	 * Saves one segment with optimistic locking.
	 *
	 * @param WP_Post $post            Canonical post.
	 * @param int     $language_id     Target language id.
	 * @param string  $segment_key     Segment key.
	 * @param string  $translated_text Target text.
	 * @param string  $source_hash     Client source hash.
	 * @param string  $status          Optional workflow status.
	 * @param string  $save_origin     Optional TM save origin (human|ai_accepted|tm_accepted|machine|import).
	 * @param int     $tm_id           Optional TM id when accepting an existing memory hit.
	 * @return array<string, mixed>
	 * @throws WorkspaceConflictException When source_hash mismatches.
	 * @throws WorkspaceQAException When QA errors block the save.
	 * @throws \InvalidArgumentException When the segment cannot be saved.
	 * @throws \RuntimeException When the saved segment cannot be reloaded.
	 */
	public function save_segment(
		WP_Post $post,
		int $language_id,
		string $segment_key,
		string $translated_text,
		string $source_hash,
		string $status = '',
		string $save_origin = '',
		int $tm_id = 0
	): array {
		$this->assert_supported_post( $post );

		$current = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $current ) {
			throw new \InvalidArgumentException( 'Unknown segment key for this post.' );
		}

		if ( ! (bool) ( $current['can_edit'] ?? false ) ) {
			throw new \InvalidArgumentException( 'This segment is not editable.' );
		}

		if ( '' !== $source_hash && (string) ( $current['source_hash'] ?? '' ) !== $source_hash ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- conflict payload is structured data, not exception text.
			throw new WorkspaceConflictException( array( $current ) );
		}

		$format = (string) ( $current['text_format'] ?? Store::FORMAT_PLAIN );
		$qa     = $this->qa->evaluate(
			(string) ( $current['source_text'] ?? '' ),
			$translated_text,
			$format
		);

		// Clearing a translation (blank target) is an allowed workspace action;
		// empty_translation must not block that path.
		if ( '' === trim( $translated_text ) ) {
			$qa = new \AIMultilingual\Workspace\QA\QAResult(
				array_values(
					array_filter(
						$qa->issues,
						static fn( \AIMultilingual\Workspace\QA\QAIssue $issue ): bool => 'empty_translation' !== $issue->code
					)
				)
			);
		}

		if ( $this->qa->should_block_save( $qa ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- QA payload is structured data, not exception text.
			throw new WorkspaceQAException( $qa );
		}

		$save_status = '' !== $status ? $status : Store::STATUS_MANUALLY_EDITED;
		if ( ! in_array( $save_status, Store::statuses(), true ) ) {
			$save_status = Store::STATUS_MANUALLY_EDITED;
		}

		$result = $this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => (string) $post->post_type,
				'language_id'     => $language_id,
				'field_key'       => (string) ( $current['field_key'] ?? '' ),
				'segment_key'     => $segment_key,
				'segment_kind'    => (string) ( $current['segment_kind'] ?? Store::KIND_BLOCK ),
				'segment_order'   => (int) ( $current['segment_order'] ?? 0 ),
				'text_format'     => (string) ( $current['text_format'] ?? Store::FORMAT_PLAIN ),
				'source_text'     => (string) ( $current['source_text'] ?? '' ),
				'translated_text' => $translated_text,
				'status'          => $save_status,
			)
		);

		if ( $result instanceof WP_Error ) {
			throw new \InvalidArgumentException( esc_html( $result->get_error_message() ) );
		}

		$this->sync_translation_memory_after_save(
			$language_id,
			$current,
			$translated_text,
			$save_origin,
			$tm_id
		);

		$refreshed = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $refreshed ) {
			throw new \RuntimeException( 'Saved segment could not be reloaded.' );
		}

		$with_meta = $this->attach_meta( array( $refreshed ), $language_id );

		return $with_meta[0];
	}

	/**
	 * Requests ranked suggestions for one segment (TM + optional AI profile).
	 *
	 * @param WP_Post $post           Canonical post.
	 * @param int     $language_id    Target language id.
	 * @param string  $segment_key    Segment key.
	 * @param string  $prompt_profile Prompt profile id.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException When the segment is unknown.
	 */
	public function request_suggestions(
		WP_Post $post,
		int $language_id,
		string $segment_key,
		string $prompt_profile
	): array {
		$this->assert_supported_post( $post );

		$segment = $this->assembler->assemble_one( $post, $language_id, $segment_key );
		if ( null === $segment ) {
			throw new \InvalidArgumentException( 'Unknown segment key for this post.' );
		}

		$default = $this->languages->default();
		$context = array(
			'source_language_id' => $default ? (int) $default->language_id : 0,
			'target_language_id' => $language_id,
			'prompt_profile'     => $prompt_profile,
			'post'               => $post,
		);

		$suggestions         = $this->suggestions->request_suggestions( $segment, $context );
		$meta                = is_array( $segment['meta'] ?? null ) ? $segment['meta'] : array();
		$meta['suggestions'] = $suggestions;
		$meta['qa']          = $this->qa->evaluate(
			(string) ( $segment['source_text'] ?? '' ),
			(string) ( $segment['translated_text'] ?? '' ),
			(string) ( $segment['text_format'] ?? Store::FORMAT_PLAIN )
		)->to_array();
		$segment['meta']     = $meta;

		return $segment;
	}

	/**
	 * Operation handler.
	 *
	 * @param WP_Post                          $post        Canonical post.
	 * @param int                              $language_id Target language id.
	 * @param array<int, array<string, mixed>> $items       Batch items.
	 * @return array<string, mixed>|WP_Error
	 */
	public function save_batch( WP_Post $post, int $language_id, array $items ) {
		$this->assert_supported_post( $post );

		return $this->batch->save_batch( $post, $language_id, $items );
	}

	/**
	 * Operation handler.
	 *
	 * @param WP_Post            $post         Canonical post.
	 * @param int                $language_id  Target language id.
	 * @param array<int, string> $segment_keys Segment keys.
	 * @param string             $mode         sync|async placeholder.
	 * @return array<string, mixed>|WP_Error
	 */
	public function translate( WP_Post $post, int $language_id, array $segment_keys, string $mode = 'sync' ) {
		$this->assert_supported_post( $post );
		unset( $mode );

		return $this->batch->translate_batch( $post, $language_id, $segment_keys );
	}

	/**
	 * Operation handler.
	 *
	 * @param string $language_code Language code from the request.
	 * @return object|null
	 */
	public function resolve_language( string $language_code ): ?object {
		if ( '' === $language_code ) {
			return null;
		}

		return $this->languages->find_by_code( $language_code );
	}

	/**
	 * Operation handler.
	 *
	 * @param int $post_id Post id.
	 * @return WP_Post|WP_Error
	 */
	public function get_post( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'aiml_post_not_found',
				__( 'Post not found.', 'ai-multilingual' ),
				array( 'status' => 404 )
			);
		}

		if ( ! in_array( $post->post_type, self::SUPPORTED_POST_TYPES, true ) ) {
			return new WP_Error(
				'aiml_post_type_unsupported',
				__( 'This post type is not supported in the workspace.', 'ai-multilingual' ),
				array( 'status' => 422 )
			);
		}

		return $post;
	}

	/**
	 * Accepts exact TM suggestions for selected segments via save_batch.
	 *
	 * @param WP_Post            $post         Canonical post.
	 * @param int                $language_id  Target language id.
	 * @param array<int, string> $segment_keys Segment keys (empty = all with exact TM).
	 * @return array<string, mixed>|WP_Error
	 */
	public function accept_tm_exact_batch( WP_Post $post, int $language_id, array $segment_keys = array() ) {
		$this->assert_supported_post( $post );

		$segments = $this->load_segments( $post, $language_id );
		$wanted   = array();
		foreach ( $segment_keys as $key ) {
			$wanted[ (string) $key ] = true;
		}

		$items = array();
		foreach ( $segments as $segment ) {
			$key = (string) ( $segment['segment_key'] ?? '' );
			if ( array() !== $wanted && ! isset( $wanted[ $key ] ) ) {
				continue;
			}

			if ( ! (bool) ( $segment['can_edit'] ?? false ) ) {
				continue;
			}

			$exact = $this->first_exact_tm_suggestion( $segment );
			if ( null === $exact ) {
				continue;
			}

			$items[] = array(
				'segment_key'     => $key,
				'translated_text' => $exact['text'],
				'source_hash'     => (string) ( $segment['source_hash'] ?? '' ),
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'save_origin'     => 'tm_accepted',
				'tm_id'           => $exact['tm_id'],
			);
		}

		if ( array() === $items ) {
			return array(
				'status'   => 'completed',
				'segments' => array(),
				'errors'   => array(),
			);
		}

		return $this->batch->save_batch( $post, $language_id, $items );
	}

	/**
	 * Runs read-only QA for selected or all segments.
	 *
	 * @param WP_Post            $post         Canonical post.
	 * @param int                $language_id  Target language id.
	 * @param array<int, string> $segment_keys Segment keys (empty = all).
	 * @return array<string, mixed>
	 */
	public function qa_batch( WP_Post $post, int $language_id, array $segment_keys = array() ): array {
		$this->assert_supported_post( $post );

		$segments = $this->load_segments( $post, $language_id );
		$wanted   = array();
		foreach ( $segment_keys as $key ) {
			$wanted[ (string) $key ] = true;
		}

		$out      = array();
		$errors   = 0;
		$warnings = 0;
		$info     = 0;

		foreach ( $segments as $segment ) {
			$key = (string) ( $segment['segment_key'] ?? '' );
			if ( array() !== $wanted && ! isset( $wanted[ $key ] ) ) {
				continue;
			}

			$qa        = is_array( $segment['meta']['qa'] ?? null ) ? $segment['meta']['qa'] : array();
			$summary   = is_array( $qa['summary'] ?? null ) ? $qa['summary'] : array();
			$errors   += (int) ( $summary['errors'] ?? 0 );
			$warnings += (int) ( $summary['warnings'] ?? 0 );
			$info     += (int) ( $summary['info'] ?? 0 );
			$out[]     = $segment;
		}

		return array(
			'segments' => $out,
			'summary'  => array(
				'errors'   => $errors,
				'warnings' => $warnings,
				'info'     => $info,
			),
		);
	}

	/**
	 * Returns the top exact TM suggestion text and tm_id, if any.
	 *
	 * @param array<string, mixed> $segment Segment DTO with meta.suggestions.
	 * @return array{text: string, tm_id: int}|null
	 */
	private function first_exact_tm_suggestion( array $segment ): ?array {
		$suggestions = is_array( $segment['meta']['suggestions'] ?? null )
			? $segment['meta']['suggestions']
			: array();

		foreach ( $suggestions as $suggestion ) {
			if ( ! is_array( $suggestion ) ) {
				continue;
			}
			if ( 'tm' !== (string) ( $suggestion['provider_id'] ?? '' ) ) {
				continue;
			}
			$tier  = (int) ( $suggestion['rank_tier'] ?? 0 );
			$meta  = is_array( $suggestion['metadata'] ?? null ) ? $suggestion['metadata'] : array();
			$match = (string) ( $meta['match_type'] ?? '' );
			if ( 1 === $tier || 'exact' === $match ) {
				$text = (string) ( $suggestion['target_text'] ?? '' );
				if ( '' === $text ) {
					return null;
				}

				return array(
					'text'  => $text,
					'tm_id' => (int) ( $meta['tm_id'] ?? 0 ),
				);
			}
		}

		return null;
	}

	/**
	 * Applies ADR-F11-004 TM write-back / usage after a successful Store save.
	 *
	 * Machine persist never reaches this method (TranslationService writes Store
	 * directly). TM accepts record usage only; human / AI-accepted / import upsert.
	 *
	 * @param int                  $language_id     Target language id.
	 * @param array<string, mixed> $current         Pre-save assembled segment DTO.
	 * @param string               $translated_text Saved target text.
	 * @param string               $save_origin     Optional explicit origin token.
	 * @param int                  $tm_id           Optional TM id for tm_accepted.
	 */
	private function sync_translation_memory_after_save(
		int $language_id,
		array $current,
		string $translated_text,
		string $save_origin = '',
		int $tm_id = 0
	): void {
		if ( '' === trim( $translated_text ) ) {
			return;
		}

		if ( 'machine' === $save_origin ) {
			return;
		}

		$default = $this->languages->default();
		if ( null === $default ) {
			return;
		}

		$text_format = (string) ( $current['text_format'] ?? Store::FORMAT_PLAIN );
		$source_text = (string) ( $current['source_text'] ?? '' );
		$source_hash = (string) ( $current['source_hash'] ?? '' );
		$context     = TranslationMemoryService::derive_context(
			(string) ( $current['block_name'] ?? '' ),
			(string) ( $current['field_key'] ?? '' )
		);

		$resolved_origin = $save_origin;
		$resolved_tm_id  = $tm_id;

		if ( '' === $resolved_origin ) {
			$match = $this->matching_tm_suggestion( $current, $language_id, $translated_text );
			if ( null !== $match ) {
				$resolved_origin = 'tm_accepted';
				$resolved_tm_id  = $match;
			} else {
				$resolved_origin = 'human';
			}
		}

		if ( 'tm_accepted' === $resolved_origin ) {
			if ( $resolved_tm_id <= 0 ) {
				$resolved_tm_id = $this->matching_tm_suggestion( $current, $language_id, $translated_text ) ?? 0;
			}
			if ( $resolved_tm_id > 0 ) {
				$this->tm->record_usage( $resolved_tm_id );
			}
			return;
		}

		if ( ! in_array( $resolved_origin, array( 'human', 'ai_accepted', 'import' ), true ) ) {
			$resolved_origin = 'human';
		}

		$this->tm->write_back(
			array(
				'source_lang_id' => (int) $default->language_id,
				'target_lang_id' => $language_id,
				'source_text'    => $source_text,
				'source_hash'    => $source_hash,
				'target_text'    => $translated_text,
				'text_format'    => $text_format,
				'context'        => $context,
			),
			$resolved_origin
		);
	}

	/**
	 * Finds a TM suggestion id whose target text matches the saved translation.
	 *
	 * @param array<string, mixed> $current         Segment DTO (meta optional).
	 * @param int                  $language_id     Target language id.
	 * @param string               $translated_text Saved target text.
	 */
	private function matching_tm_suggestion( array $current, int $language_id, string $translated_text ): ?int {
		$suggestions = is_array( $current['meta']['suggestions'] ?? null )
			? $current['meta']['suggestions']
			: array();

		if ( array() === $suggestions ) {
			$with_meta   = $this->attach_meta( array( $current ), $language_id );
			$suggestions = is_array( $with_meta[0]['meta']['suggestions'] ?? null )
				? $with_meta[0]['meta']['suggestions']
				: array();
		}

		foreach ( $suggestions as $suggestion ) {
			if ( ! is_array( $suggestion ) ) {
				continue;
			}
			if ( 'tm' !== (string) ( $suggestion['provider_id'] ?? '' ) ) {
				continue;
			}
			if ( (string) ( $suggestion['target_text'] ?? '' ) !== $translated_text ) {
				continue;
			}
			$meta  = is_array( $suggestion['metadata'] ?? null ) ? $suggestion['metadata'] : array();
			$tm_id = (int) ( $meta['tm_id'] ?? 0 );
			if ( $tm_id > 0 ) {
				return $tm_id;
			}
		}

		return null;
	}

	/**
	 * Attaches ranked suggestions and QA via dedicated services.
	 *
	 * @param list<array<string, mixed>> $segments    Assembled segment DTOs.
	 * @param int                        $language_id Target language id.
	 * @return list<array<string, mixed>>
	 */
	private function attach_meta( array $segments, int $language_id ): array {
		$default = $this->languages->default();
		$context = array(
			'source_language_id' => $default ? (int) $default->language_id : 0,
			'target_language_id' => $language_id,
		);

		$by_key = $this->suggestions->suggestions_for_batch( $segments, $context );

		foreach ( $segments as $index => $segment ) {
			$key                        = (string) ( $segment['segment_key'] ?? '' );
			$meta                       = is_array( $segment['meta'] ?? null ) ? $segment['meta'] : array();
			$meta['suggestions']        = $by_key[ $key ] ?? array();
			$meta['qa']                 = $this->qa->evaluate(
				(string) ( $segment['source_text'] ?? '' ),
				(string) ( $segment['translated_text'] ?? '' ),
				(string) ( $segment['text_format'] ?? Store::FORMAT_PLAIN )
			)->to_array();
			$segments[ $index ]['meta'] = $meta;
		}

		return $segments;
	}

	/**
	 * Ensures the post type is supported by the workspace.
	 *
	 * @param WP_Post $post Canonical post.
	 * @throws \InvalidArgumentException When the post type is unsupported.
	 */
	private function assert_supported_post( WP_Post $post ): void {
		if ( ! in_array( $post->post_type, self::SUPPORTED_POST_TYPES, true ) ) {
			throw new \InvalidArgumentException( 'This post type is not supported in the workspace.' );
		}
	}

	/**
	 * Whether the current user may access workspace operations for a post.
	 *
	 * @param WP_Post $post Canonical post.
	 */
	public function current_user_can_access( WP_Post $post ): bool {
		return current_user_can( Plugin::CAPABILITY ) && current_user_can( 'edit_post', (int) $post->ID );
	}
}
