<?php
/**
 * Glossary application service (ADR-0014).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Glossary;

use AIMultilingual\Database\Schema;
use WP_Error;

/**
 * Owns CRUD orchestration, versioning, matching, and fragment building.
 */
final class GlossaryService {

	public const FRAGMENT_MAX_TERMS         = 40;
	public const FRAGMENT_MAX_CHARS         = 4000;
	public const FRAGMENT_TRUNCATION_MARKER = '# glossary_truncated';

	/**
	 * Fragment truncation counter for diagnostics.
	 *
	 * @var int
	 */
	private int $fragment_truncations = 0;

	/**
	 * Rejected write counter for diagnostics.
	 *
	 * @var int
	 */
	private int $rejected_writes = 0;

	/**
	 * Exact-segment suggestion hit counter for diagnostics.
	 *
	 * @var int
	 */
	private int $exact_suggestion_hits = 0;

	/**
	 * Wire repository, normalizer, and matcher.
	 *
	 * @param GlossaryRepository $repository Persistence.
	 * @param GlossaryNormalizer $normalizer Canonical normalizer.
	 * @param GlossaryMatcher    $matcher    Whole-word matcher.
	 */
	public function __construct(
		private readonly GlossaryRepository $repository,
		private readonly GlossaryNormalizer $normalizer,
		private readonly GlossaryMatcher $matcher
	) {
	}

	/**
	 * Current glossary lexicon version.
	 */
	public function current_version(): int {
		return (int) get_option( Schema::GLOSSARY_VERSION_OPTION, 0 );
	}

	/**
	 * Create a glossary term and bump version on success.
	 *
	 * @param array<string, mixed> $input Create payload.
	 * @return object|WP_Error
	 */
	public function create( array $input ) {
		try {
			$payload = $this->normalize_write_payload( $input );
		} catch ( \InvalidArgumentException $e ) {
			++$this->rejected_writes;

			return new WP_Error( $e->getMessage(), 'Invalid glossary term.' );
		}

		$row = $this->repository->insert( $payload );
		if ( $row instanceof WP_Error ) {
			++$this->rejected_writes;

			return $row;
		}

		$this->bump_version();

		return $row;
	}

	/**
	 * Update a glossary term and bump version on material change.
	 *
	 * @param int                  $glossary_id Term id.
	 * @param array<string, mixed> $input       Update payload.
	 * @return object|WP_Error|true True when no-op.
	 */
	public function update( int $glossary_id, array $input ) {
		$existing = $this->repository->find( $glossary_id );
		if ( null === $existing ) {
			return new WP_Error( 'glossary_not_found', 'Glossary term not found.' );
		}

		try {
			$merged  = array(
				'source_lang_id' => (int) ( $input['source_lang_id'] ?? $existing->source_lang_id ),
				'target_lang_id' => (int) ( $input['target_lang_id'] ?? $existing->target_lang_id ),
				'source_term'    => (string) ( $input['source_term'] ?? $existing->source_term ),
				'target_term'    => (string) ( $input['target_term'] ?? $existing->target_term ),
				'context'        => (string) ( $input['context'] ?? $existing->context ),
				'description'    => (string) ( $input['description'] ?? $existing->description ),
				'is_active'      => array_key_exists( 'is_active', $input ) ? (bool) $input['is_active'] : (bool) $existing->is_active,
			);
			$payload = $this->normalize_write_payload( $merged );
		} catch ( \InvalidArgumentException $e ) {
			++$this->rejected_writes;

			return new WP_Error( $e->getMessage(), 'Invalid glossary term.' );
		}

		$row = $this->repository->update( $glossary_id, $payload );
		if ( $row instanceof WP_Error ) {
			++$this->rejected_writes;

			return $row;
		}
		if ( null === $row ) {
			return true;
		}

		$this->bump_version();

		return $row;
	}

	/**
	 * Activate or deactivate a term.
	 *
	 * @param int  $glossary_id Term id.
	 * @param bool $active      Desired active state.
	 * @return object|WP_Error|true
	 */
	public function set_active( int $glossary_id, bool $active ) {
		$row = $this->repository->update(
			$glossary_id,
			array( 'is_active' => $active )
		);
		if ( $row instanceof WP_Error ) {
			return $row;
		}
		if ( null === $row ) {
			return true;
		}
		$this->bump_version();

		return $row;
	}

	/**
	 * Delete a term and bump version.
	 *
	 * @param int $glossary_id Term id.
	 * @return true|WP_Error
	 */
	public function delete( int $glossary_id ) {
		$existing = $this->repository->find( $glossary_id );
		if ( null === $existing ) {
			return new WP_Error( 'glossary_not_found', 'Glossary term not found.' );
		}
		if ( ! $this->repository->delete( $glossary_id ) ) {
			return new WP_Error( 'glossary_delete_failed', 'Failed to delete glossary term.' );
		}
		$this->bump_version();

		return true;
	}

	/**
	 * Find a term by id.
	 *
	 * @param int $glossary_id Term id.
	 */
	public function find( int $glossary_id ): ?object {
		return $this->repository->find( $glossary_id );
	}

	/**
	 * Admin listing query.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{items:list<object>,total:int}
	 */
	public function query( array $args ): array {
		return $this->repository->query( $args );
	}

	/**
	 * Match active terms against source text.
	 *
	 * @param string $source_text     Source segment.
	 * @param int    $source_lang_id  Source language id.
	 * @param int    $target_lang_id  Target language id.
	 * @param string $text_format     plain|html.
	 * @return list<GlossaryTermMatch>
	 */
	public function match_terms( string $source_text, int $source_lang_id, int $target_lang_id, string $text_format = 'plain' ): array {
		$terms = $this->repository->list_active_for_pair( $source_lang_id, $target_lang_id );

		return $this->matcher->match( $source_text, $terms, $text_format );
	}

	/**
	 * Exact-segment glossary suggestion target, if any.
	 *
	 * @param string $source_text    Source segment.
	 * @param int    $source_lang_id Source language id.
	 * @param int    $target_lang_id Target language id.
	 */
	public function exact_segment_suggestion( string $source_text, int $source_lang_id, int $target_lang_id ): ?GlossaryTermMatch {
		foreach ( $this->match_terms( $source_text, $source_lang_id, $target_lang_id ) as $match ) {
			if ( $match->is_exact_segment() ) {
				++$this->exact_suggestion_hits;

				return $match;
			}
		}

		return null;
	}

	/**
	 * Deterministic bounded glossary fragment for AI batch.
	 *
	 * @param string $source_text    Source segment.
	 * @param int    $source_lang_id Source language id.
	 * @param int    $target_lang_id Target language id.
	 * @param string $text_format    plain|html.
	 */
	public function build_fragment( string $source_text, int $source_lang_id, int $target_lang_id, string $text_format = 'plain' ): string {
		$matches = $this->match_terms( $source_text, $source_lang_id, $target_lang_id, $text_format );
		if ( array() === $matches ) {
			return '';
		}

		usort(
			$matches,
			static function ( GlossaryTermMatch $a, GlossaryTermMatch $b ): int {
				if ( $a->length !== $b->length ) {
					return $b->length <=> $a->length;
				}
				if ( $a->char_offset !== $b->char_offset ) {
					return $a->char_offset <=> $b->char_offset;
				}
				$lex = $a->source_term_normalized <=> $b->source_term_normalized;
				if ( 0 !== $lex ) {
					return $lex;
				}

				return $a->glossary_id <=> $b->glossary_id;
			}
		);

		$lines      = array();
		$char_count = 0;
		$truncated  = false;

		foreach ( $matches as $match ) {
			if ( count( $lines ) >= self::FRAGMENT_MAX_TERMS ) {
				$truncated = true;
				break;
			}
			$source = str_replace( array( "\r", "\n" ), ' ', $match->source_term );
			$target = str_replace( array( "\r", "\n" ), ' ', $match->target_term );
			$line   = $source . ' => ' . $target;
			$next   = $char_count + strlen( $line ) + ( array() === $lines ? 0 : 1 );
			if ( $next > self::FRAGMENT_MAX_CHARS ) {
				$truncated = true;
				break;
			}
			$lines[]    = $line;
			$char_count = $next;
		}

		if ( $truncated ) {
			++$this->fragment_truncations;
			$marker_len = strlen( self::FRAGMENT_TRUNCATION_MARKER ) + 1;
			while ( array() !== $lines && $char_count + $marker_len > self::FRAGMENT_MAX_CHARS ) {
				$removed     = array_pop( $lines );
				$char_count -= strlen( (string) $removed ) + ( array() === $lines ? 0 : 1 );
			}
			$lines[] = self::FRAGMENT_TRUNCATION_MARKER;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Low-cardinality diagnostics snapshot.
	 *
	 * @return array<string, mixed>
	 */
	public function diagnostics(): array {
		return array(
			'version'               => $this->current_version(),
			'active_by_pair'        => $this->repository->count_active_by_pair(),
			'rejected_writes'       => $this->rejected_writes,
			'fragment_truncations'  => $this->fragment_truncations,
			'exact_suggestion_hits' => $this->exact_suggestion_hits,
		);
	}

	/**
	 * Normalize and validate a write payload.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException When validation fails.
	 */
	private function normalize_write_payload( array $input ): array {
		$source = trim( (string) ( $input['source_term'] ?? '' ) );
		if ( mb_strlen( $source, 'UTF-8' ) > 255 ) {
			throw new \InvalidArgumentException( 'glossary_term_too_long' );
		}

		return array(
			'source_lang_id'         => (int) $input['source_lang_id'],
			'target_lang_id'         => (int) $input['target_lang_id'],
			'source_term'            => $source,
			'source_term_normalized' => $this->normalizer->normalize_source( $source ),
			'target_term'            => $this->normalizer->prepare_target( (string) ( $input['target_term'] ?? '' ) ),
			'context'                => mb_substr( (string) ( $input['context'] ?? '' ), 0, 64, 'UTF-8' ),
			'description'            => mb_substr( (string) ( $input['description'] ?? '' ), 0, 512, 'UTF-8' ),
			'is_active'              => array_key_exists( 'is_active', $input ) ? (bool) $input['is_active'] : true,
		);
	}

	/**
	 * Increment the monotonic glossary version option.
	 */
	private function bump_version(): void {
		$current = $this->current_version();
		update_option( Schema::GLOSSARY_VERSION_OPTION, $current + 1, true );
	}
}
