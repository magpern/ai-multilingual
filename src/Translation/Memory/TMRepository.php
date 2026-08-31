<?php
/**
 * Translation memory table access (ADR-0009 / F11).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Memory;

use AIMultilingual\Database\Schema;
use AIMultilingual\Translation\Store;
use WP_Error;

/**
 * Persistence boundary for `aiml_tm`.
 *
 * Does not implement lookup policy, write-back eligibility, or ranking —
 * those belong to {@see TranslationMemoryService} (WP2).
 */
final class TMRepository {

	/**
	 * Provenance values recorded in the `origin` column.
	 */
	public const ORIGIN_HUMAN  = 'human';
	public const ORIGIN_AI     = 'ai';
	public const ORIGIN_IMPORT = 'import';
	public const ORIGIN_LEGACY = 'legacy';

	/**
	 * Quality tier recorded for ranking (ADR-0009).
	 */
	public const QUALITY_HUMAN_APPROVED = 'human_approved';

	/**
	 * Allowed origin values.
	 *
	 * @return list<string>
	 */
	public static function origins(): array {
		return array(
			self::ORIGIN_HUMAN,
			self::ORIGIN_AI,
			self::ORIGIN_IMPORT,
			self::ORIGIN_LEGACY,
		);
	}

	/**
	 * Loads one TM row by primary key.
	 *
	 * @param int $tm_id Memory entry id.
	 * @return object|null
	 */
	public function find( int $tm_id ): ?object {
		global $wpdb;

		if ( $tm_id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::tm() . ' WHERE tm_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL
				$tm_id
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * Loads one TM row by unique identity.
	 *
	 * @param string $source_hash    Normalized source hash.
	 * @param int    $source_lang_id Source language id.
	 * @param int    $target_lang_id Target language id.
	 * @param string $context        Context key (empty string allowed).
	 * @return object|null
	 */
	public function find_by_identity(
		string $source_hash,
		int $source_lang_id,
		int $target_lang_id,
		string $context = ''
	): ?object {
		global $wpdb;

		if ( '' === $source_hash || $source_lang_id <= 0 || $target_lang_id <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::tm() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' WHERE source_hash = %s AND source_lang_id = %d AND target_lang_id = %d AND context = %s',
				$source_hash,
				$source_lang_id,
				$target_lang_id,
				$context
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}

	/**
	 * Returns candidate rows for fuzzy scoring within a language pair.
	 *
	 * Cap is applied in SQL so large corpora do not flood PHP.
	 *
	 * @param int         $source_lang_id Source language id.
	 * @param int         $target_lang_id Target language id.
	 * @param string|null $text_format    Optional format filter.
	 * @param int         $limit          Max candidates.
	 * @return list<object>
	 */
	public function find_fuzzy_candidates(
		int $source_lang_id,
		int $target_lang_id,
		?string $text_format = null,
		int $limit = 200
	): array {
		global $wpdb;

		if ( $source_lang_id <= 0 || $target_lang_id <= 0 ) {
			return array();
		}

		$limit = max( 1, min( 500, $limit ) );

		if ( null !== $text_format && '' !== $text_format ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . Schema::tm() // phpcs:ignore WordPress.DB.PreparedSQL
					. ' WHERE source_lang_id = %d AND target_lang_id = %d AND text_format = %s'
					. ' ORDER BY use_count DESC, updated_at DESC LIMIT %d',
					$source_lang_id,
					$target_lang_id,
					$text_format,
					$limit
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . Schema::tm() // phpcs:ignore WordPress.DB.PreparedSQL
					. ' WHERE source_lang_id = %d AND target_lang_id = %d'
					. ' ORDER BY use_count DESC, updated_at DESC LIMIT %d',
					$source_lang_id,
					$target_lang_id,
					$limit
				)
			);
		}

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Upserts a TM entry against `tm_identity`.
	 *
	 * Human/import provenance replaces ai-origin rows for the same identity
	 * (ADR-F11-004 update rules). Callers supply eligibility-checked payloads.
	 *
	 * @param array<string, mixed> $entry Entry fields.
	 * @return object|WP_Error Persisted row or error.
	 */
	public function upsert( array $entry ) {
		global $wpdb;

		$source_lang_id = (int) ( $entry['source_lang_id'] ?? 0 );
		$target_lang_id = (int) ( $entry['target_lang_id'] ?? 0 );
		$source_hash    = (string) ( $entry['source_hash'] ?? '' );
		$source_text    = (string) ( $entry['source_text'] ?? '' );
		$target_text    = (string) ( $entry['target_text'] ?? '' );
		$text_format    = (string) ( $entry['text_format'] ?? Store::FORMAT_PLAIN );
		$context        = (string) ( $entry['context'] ?? '' );
		$norm_version   = (int) ( $entry['norm_version'] ?? Store::NORM_VERSION );
		$origin         = (string) ( $entry['origin'] ?? self::ORIGIN_HUMAN );
		$quality        = (string) ( $entry['quality'] ?? self::QUALITY_HUMAN_APPROVED );
		$glossary       = (int) ( $entry['glossary_version'] ?? 0 );

		if ( $source_lang_id <= 0 || $target_lang_id <= 0 || '' === $source_hash ) {
			return new WP_Error(
				'aiml_tm_invalid_entry',
				__( 'Translation memory entry is incomplete.', 'universal-multilingual' ),
				array( 'status' => 422 )
			);
		}

		if ( ! in_array( $origin, self::origins(), true ) ) {
			return new WP_Error(
				'aiml_tm_invalid_origin',
				__( 'Unknown translation memory origin.', 'universal-multilingual' ),
				array( 'status' => 422 )
			);
		}

		$now = current_time( 'mysql', true );

		$sql = 'INSERT INTO ' . Schema::tm()
			. ' (source_lang_id, target_lang_id, source_hash, source_text, target_text, text_format,'
			. ' context, norm_version, origin, quality, use_count, glossary_version, created_at, updated_at, last_used_at)'
			. ' VALUES (%d, %d, %s, %s, %s, %s, %s, %d, %s, %s, %d, %d, %s, %s, NULL)'
			. ' ON DUPLICATE KEY UPDATE'
			. ' source_text = VALUES(source_text),'
			. ' target_text = VALUES(target_text),'
			. ' text_format = VALUES(text_format),'
			. ' norm_version = VALUES(norm_version),'
			. ' origin = VALUES(origin),'
			. ' quality = VALUES(quality),'
			. ' glossary_version = VALUES(glossary_version),'
			. ' updated_at = VALUES(updated_at)';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Schema::tm() is trusted; values are prepared.
		$result = $wpdb->query(
			$wpdb->prepare(
				$sql,
				$source_lang_id,
				$target_lang_id,
				$source_hash,
				$source_text,
				$target_text,
				$text_format,
				$context,
				$norm_version,
				$origin,
				$quality,
				0,
				$glossary,
				$now,
				$now
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- intentional TM write path.

		if ( false === $result ) {
			return new WP_Error(
				'aiml_tm_write_failed',
				__( 'Could not write translation memory entry.', 'universal-multilingual' ),
				array( 'status' => 500 )
			);
		}

		$row = $this->find_by_identity( $source_hash, $source_lang_id, $target_lang_id, $context );
		if ( null === $row ) {
			return new WP_Error(
				'aiml_tm_write_failed',
				__( 'Could not reload translation memory entry after write.', 'universal-multilingual' ),
				array( 'status' => 500 )
			);
		}

		return $row;
	}

	/**
	 * Increments usage counters for an existing entry.
	 *
	 * @param int $tm_id Memory entry id.
	 * @return object|WP_Error Updated row or error.
	 */
	public function record_usage( int $tm_id ) {
		global $wpdb;

		$row = $this->find( $tm_id );
		if ( null === $row ) {
			return new WP_Error(
				'aiml_tm_not_found',
				__( 'Translation memory entry was not found.', 'universal-multilingual' ),
				array( 'status' => 404 )
			);
		}

		$now = current_time( 'mysql', true );

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::tm() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' SET use_count = use_count + 1, last_used_at = %s, updated_at = %s WHERE tm_id = %d',
				$now,
				$now,
				$tm_id
			)
		);

		if ( false === $updated ) {
			return new WP_Error(
				'aiml_tm_write_failed',
				__( 'Could not update translation memory usage.', 'universal-multilingual' ),
				array( 'status' => 500 )
			);
		}

		$refreshed = $this->find( $tm_id );
		if ( null === $refreshed ) {
			return new WP_Error(
				'aiml_tm_write_failed',
				__( 'Could not reload translation memory entry after usage update.', 'universal-multilingual' ),
				array( 'status' => 500 )
			);
		}

		return $refreshed;
	}
}
