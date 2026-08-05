<?php
/**
 * Segment store.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Database\Schema;
use WP_Error;

/**
 * Reads and writes translation segments.
 *
 * A segment is the unit of translation: one addressable piece of one field of
 * one object in one language. Identity is
 * (source_type, source_id, segment_hash, language_id), which makes every write
 * an upsert and therefore safe to replay.
 *
 * The normalization and hashing helpers are static and pure so the rules can be
 * unit-tested without a WordPress bootstrap. Normalization is dispatched on the
 * segment's text format because a single rule would be wrong: collapsing runs
 * of whitespace is harmless in a title and destructive inside a code block or a
 * JSON string. The algorithm is versioned (`norm_version`) so a future change
 * to these rules cannot silently mark an entire translated site stale
 * (ADR-0006).
 */
final class Store {

	/**
	 * Version of the normalization algorithm implemented here.
	 *
	 * Bump this when the rules change, keep the old branch reachable, and
	 * re-hash rows lazily on their next write.
	 */
	public const NORM_VERSION = 1;

	/**
	 * Text formats.
	 */
	public const FORMAT_PLAIN = 'plain';
	public const FORMAT_HTML  = 'html';
	public const FORMAT_JSON  = 'json';
	public const FORMAT_CODE  = 'code';
	public const FORMAT_SLUG  = 'slug';

	/**
	 * Segment provenance states. Freshness is a separate axis (`is_stale`).
	 */
	public const STATUS_MISSING            = 'missing';
	public const STATUS_MACHINE_TRANSLATED = 'machine_translated';
	public const STATUS_MANUALLY_EDITED    = 'manually_edited';
	public const STATUS_REVIEWED           = 'reviewed';
	public const STATUS_FAILED             = 'failed';
	public const STATUS_IGNORED            = 'ignored';

	/**
	 * Segment kinds.
	 */
	public const KIND_FIELD = 'field';

	/**
	 * Block-level segment kind (Strategy F).
	 */
	public const KIND_BLOCK = 'block';

	/**
	 * Source types.
	 */
	public const SOURCE_POST = 'post';

	/**
	 * Object cache.
	 *
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Builds the segment store.
	 *
	 * @param Cache $cache Object cache wrapper.
	 */
	public function __construct( Cache $cache ) {
		$this->cache = $cache;
	}

	// -- Pure helpers (safe to call without WordPress loaded) --

	/**
	 * Every known text format.
	 *
	 * @return string[]
	 */
	public static function formats(): array {
		return array( self::FORMAT_PLAIN, self::FORMAT_HTML, self::FORMAT_JSON, self::FORMAT_CODE, self::FORMAT_SLUG );
	}

	/**
	 * Every known provenance status.
	 *
	 * @return string[]
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_MISSING,
			self::STATUS_MACHINE_TRANSLATED,
			self::STATUS_MANUALLY_EDITED,
			self::STATUS_REVIEWED,
			self::STATUS_FAILED,
			self::STATUS_IGNORED,
		);
	}

	/**
	 * Normalizes source text for hashing, according to its format.
	 *
	 * The goal is to ignore differences that cannot change meaning, and only
	 * those. What counts as meaningless differs per format, which is why this
	 * dispatches rather than applying one rule:
	 *
	 * - plain: line endings, non-breaking spaces and whitespace runs are all
	 *   cosmetic in a title or label, so they collapse to a single space.
	 * - html: line endings and the three ways of writing a non-breaking space
	 *   are normalized, but whitespace is never collapsed — it is significant
	 *   between inline elements and inside `<pre>`. Non-breaking spaces are
	 *   canonicalized to the codepoint rather than converted to ordinary
	 *   spaces, because the non-breaking property is itself meaningful.
	 * - json: reordering keys does not change the document, so it is parsed and
	 *   re-encoded canonically. Invalid JSON is hashed byte-for-byte rather
	 *   than "repaired".
	 * - code: only line endings are normalized; indentation is meaning.
	 * - slug: trimmed and lowercased. Slug values are written already
	 *   canonicalized through sanitize_title(), so no further transformation is
	 *   needed here and this stays free of WordPress.
	 *
	 * @param string $text   Source text.
	 * @param string $format One of the FORMAT_* constants.
	 */
	public static function normalize( string $text, string $format = self::FORMAT_PLAIN ): string {
		switch ( $format ) {
			case self::FORMAT_HTML:
				$text = self::normalize_line_endings( $text );
				$text = self::canonicalize_nbsp( $text );

				return trim( $text );

			case self::FORMAT_JSON:
				return self::canonicalize_json( $text );

			case self::FORMAT_CODE:
				return self::normalize_line_endings( $text );

			case self::FORMAT_SLUG:
				return strtolower( trim( $text ) );

			case self::FORMAT_PLAIN:
			default:
				$text = self::normalize_line_endings( $text );
				$text = self::nbsp_to_space( $text );
				$text = (string) preg_replace( '/\s+/u', ' ', $text );

				return trim( $text );
		}
	}

	/**
	 * Hash of the normalized source text.
	 *
	 * Answers exactly one question: has the meaning of the source changed?
	 *
	 * @param string $text   Source text.
	 * @param string $format One of the FORMAT_* constants.
	 */
	public static function source_hash( string $text, string $format = self::FORMAT_PLAIN ): string {
		return sha1( self::normalize( $text, $format ) );
	}

	/**
	 * Hash of the stored translation, exactly as stored.
	 *
	 * This is an integrity marker, not an edit detector. The plugin owns the
	 * write path, so an editor saving through the UI updates the text and this
	 * hash together and they always agree afterwards. What it does catch is
	 * modification by something other than the plugin — direct SQL, a partial
	 * restore, replication damage. Comparison against remembered historical
	 * states (last machine output, last reviewed value) arrives with the
	 * revision-history migration in Milestone 3 (ADR-0007).
	 *
	 * @param string $text Translated text.
	 */
	public static function translation_hash( string $text ): string {
		return sha1( $text );
	}

	/**
	 * Stable identity of a segment within its object and language.
	 *
	 * @param string $field_key   Logical field.
	 * @param string $segment_key Segment identity within the field.
	 */
	public static function segment_hash( string $field_key, string $segment_key ): string {
		return sha1( $field_key . "\x1f" . $segment_key );
	}

	// -- Reads --

	/**
	 * Loads every segment for one object in one language.
	 *
	 * This is the hot path: one indexed query, one cache entry, results keyed
	 * by segment key so the renderer can look up fields without further
	 * queries.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Language id.
	 * @return array<string, object> Segment rows keyed by segment key.
	 */
	public function load_object( string $source_type, int $source_id, int $language_id ): array {
		if ( $language_id <= 0 ) {
			return array();
		}

		$key    = sprintf( 'seg:%s:%d', $source_type, $source_id );
		$cached = $this->cache->get( $key, $language_id );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::translations() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' WHERE source_type = %s AND source_id = %d AND language_id = %d'
				. ' ORDER BY segment_order ASC, translation_id ASC',
				$source_type,
				$source_id,
				$language_id
			)
		);

		$map = array();
		foreach ( (array) $rows as $row ) {
			$map[ (string) $row->segment_key ] = $this->hydrate( $row );
		}

		$this->cache->set( $key, $language_id, $map );

		return $map;
	}

	/**
	 * Returns one segment, or null.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Segment key.
	 */
	public function get( string $source_type, int $source_id, int $language_id, string $segment_key ): ?object {
		$segments = $this->load_object( $source_type, $source_id, $language_id );

		return $segments[ $segment_key ] ?? null;
	}

	/**
	 * Returns the renderable translation for a segment, or null.
	 *
	 * A stale translation is still returned. Dropping back to the source the
	 * moment an editor touches the English copy would splice languages together
	 * mid-page, which reads worse than slightly outdated but coherent text
	 * (invariant I7). Staleness is surfaced in the admin instead.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Language id.
	 * @param string $segment_key Segment key.
	 */
	public function translated_value( string $source_type, int $source_id, int $language_id, string $segment_key ): ?string {
		$segment = $this->get( $source_type, $source_id, $language_id, $segment_key );

		if ( null === $segment ) {
			return null;
		}

		if ( self::STATUS_IGNORED === $segment->status || self::STATUS_MISSING === $segment->status ) {
			return null;
		}

		$value = (string) ( $segment->translated_text ?? '' );

		return '' === $value ? null : $value;
	}

	/**
	 * Counts segments per status for an object across all languages.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @return array<int, array{total: int, stale: int}> Keyed by language id.
	 */
	public function summary_for_object( string $source_type, int $source_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT language_id, COUNT(*) AS total, SUM(is_stale) AS stale FROM ' . Schema::translations() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' WHERE source_type = %s AND source_id = %d AND status <> %s'
				. ' GROUP BY language_id',
				$source_type,
				$source_id,
				self::STATUS_MISSING
			)
		);

		$summary = array();
		foreach ( (array) $rows as $row ) {
			$summary[ (int) $row->language_id ] = array(
				'total' => (int) $row->total,
				'stale' => (int) $row->stale,
			);
		}

		return $summary;
	}

	/**
	 * Provenance states eligible for frontend block rendering.
	 *
	 * Keep aligned with {@see BlockTranslationLookup}.
	 *
	 * @var list<string>
	 */
	public const RENDERABLE_STATUSES = array(
		self::STATUS_MACHINE_TRANSLATED,
		self::STATUS_MANUALLY_EDITED,
		self::STATUS_REVIEWED,
	);

	/**
	 * Whether the translations table exists.
	 */
	public function translations_table_exists(): bool {
		global $wpdb;

		$table = Schema::translations();

		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Whether duplicate segment identity rows can be detected reliably.
	 *
	 * The schema enforces UNIQUE segment_identity, so duplicate rows should not
	 * exist without bypassing the store write path.
	 */
	public function duplicate_segment_rows_detectable(): bool {
		return false;
	}

	/**
	 * Counts block-kind segments scoped by source type and optional ids.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Optional source object id.
	 * @param int    $language_id Optional language id.
	 */
	public function count_block_segments( string $source_type, int $source_id = 0, int $language_id = 0 ): int {
		return $this->health_count_block_segments(
			$source_type,
			$source_id,
			$language_id,
			''
		);
	}

	/**
	 * Counts translated block segments with non-empty text.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Optional source object id.
	 * @param int    $language_id Optional language id.
	 */
	public function count_translated_block_segments( string $source_type, int $source_id = 0, int $language_id = 0 ): int {
		return $this->health_count_block_segments(
			$source_type,
			$source_id,
			$language_id,
			' AND status NOT IN (%s, %s) AND TRIM(translated_text) <> %s',
			array(
				self::STATUS_MISSING,
				self::STATUS_IGNORED,
				'',
			)
		);
	}

	/**
	 * Counts renderable block segments aligned with frontend lookup rules.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Optional source object id.
	 * @param int    $language_id Optional language id.
	 */
	public function count_renderable_block_segments( string $source_type, int $source_id = 0, int $language_id = 0 ): int {
		$status_placeholders = implode( ', ', array_fill( 0, count( self::RENDERABLE_STATUSES ), '%s' ) );

		return $this->health_count_block_segments(
			$source_type,
			$source_id,
			$language_id,
			' AND is_stale = 0 AND status IN (' . $status_placeholders . ') AND TRIM(translated_text) <> %s',
			array_merge( self::RENDERABLE_STATUSES, array( '' ) )
		);
	}

	/**
	 * Counts stale block-kind segments.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Optional source object id.
	 * @param int    $language_id Optional language id.
	 */
	public function count_stale_block_segments( string $source_type, int $source_id = 0, int $language_id = 0 ): int {
		return $this->health_count_block_segments(
			$source_type,
			$source_id,
			$language_id,
			' AND is_stale = 1'
		);
	}

	/**
	 * Counts orphaned block segments reconciled by sync_source.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Optional source object id.
	 * @param int    $language_id Optional language id.
	 */
	public function count_orphaned_block_segments( string $source_type, int $source_id = 0, int $language_id = 0 ): int {
		return $this->health_count_block_segments(
			$source_type,
			$source_id,
			$language_id,
			' AND status = %s AND error_code = %s',
			array(
				self::STATUS_IGNORED,
				'orphaned',
			)
		);
	}

	/**
	 * Counts duplicate segment identity rows when detectable.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Optional source object id.
	 */
	public function count_duplicate_segment_rows( string $source_type, int $source_id = 0 ): int {
		if ( ! $this->duplicate_segment_rows_detectable() ) {
			return 0;
		}

		global $wpdb;

		if ( ! $this->translations_table_exists() ) {
			return 0;
		}

		$scope = $this->health_scope_sql( $source_type, $source_id, 0 );
		$sql   = 'SELECT COALESCE(SUM(dup_count - 1), 0) FROM ('
			. 'SELECT COUNT(*) AS dup_count FROM ' . Schema::translations() // phpcs:ignore WordPress.DB.PreparedSQL
			. ' WHERE segment_kind = %s AND segment_key LIKE %s' . $scope['sql']
			. ' GROUP BY source_type, source_id, segment_hash, language_id HAVING COUNT(*) > 1'
			. ') AS duplicates';

		$count = $wpdb->get_var( $wpdb->prepare( $sql, ...array_merge( array( self::KIND_BLOCK, 'b:%' ), $scope['args'] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		return $this->normalize_health_count( $count );
	}

	/**
	 * Executes a scoped block-segment count query.
	 *
	 * @param string           $source_type Source type.
	 * @param int              $source_id   Optional source object id.
	 * @param int              $language_id Optional language id.
	 * @param string           $extra_where Additional WHERE clause with placeholders.
	 * @param list<string|int> $extra_args  Placeholder values for the extra clause.
	 */
	private function health_count_block_segments(
		string $source_type,
		int $source_id,
		int $language_id,
		string $extra_where,
		array $extra_args = array()
	): int {
		global $wpdb;

		if ( ! $this->translations_table_exists() ) {
			return 0;
		}

		$scope = $this->health_scope_sql( $source_type, $source_id, $language_id );
		$sql   = 'SELECT COUNT(*) FROM ' . Schema::translations() // phpcs:ignore WordPress.DB.PreparedSQL
			. ' WHERE segment_kind = %s AND segment_key LIKE %s' . $scope['sql'] . $extra_where;

		$args  = array_merge( array( self::KIND_BLOCK, 'b:%' ), $scope['args'], $extra_args );
		$count = $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		return $this->normalize_health_count( $count );
	}

	/**
	 * Builds optional source and language scope SQL fragments.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Optional source object id.
	 * @param int    $language_id Optional language id.
	 * @return array{sql: string, args: list<string|int>}
	 */
	private function health_scope_sql( string $source_type, int $source_id, int $language_id ): array {
		$sql  = ' AND source_type = %s';
		$args = array( $source_type );

		if ( $source_id > 0 ) {
			$sql   .= ' AND source_id = %d';
			$args[] = $source_id;
		}

		if ( $language_id > 0 ) {
			$sql   .= ' AND language_id = %d';
			$args[] = $language_id;
		}

		return array(
			'sql'  => $sql,
			'args' => $args,
		);
	}

	/**
	 * Normalizes count query results to non-negative integers.
	 *
	 * @param mixed $count Raw query result.
	 */
	private function normalize_health_count( $count ): int {
		if ( null === $count || false === $count ) {
			return 0;
		}

		return max( 0, (int) $count );
	}

	// -- Writes --

	/**
	 * Saves a translated segment.
	 *
	 * @param array<string, mixed> $args Segment fields. Required: source_type,
	 *                                   source_id, language_id, field_key,
	 *                                   source_text, translated_text.
	 * @return true|WP_Error
	 */
	public function save_translation( array $args ) {
		$source_type = (string) ( $args['source_type'] ?? self::SOURCE_POST );
		$source_id   = (int) ( $args['source_id'] ?? 0 );
		$language_id = (int) ( $args['language_id'] ?? 0 );
		$field_key   = (string) ( $args['field_key'] ?? '' );
		$segment_key = (string) ( $args['segment_key'] ?? $field_key );

		if ( $source_id <= 0 || $language_id <= 0 || '' === $field_key ) {
			return new WP_Error( 'aiml_invalid_segment', __( 'Incomplete segment reference.', 'ai-multilingual' ) );
		}

		$format = (string) ( $args['text_format'] ?? self::FORMAT_PLAIN );
		if ( ! in_array( $format, self::formats(), true ) ) {
			$format = self::FORMAT_PLAIN;
		}

		$status = (string) ( $args['status'] ?? self::STATUS_MANUALLY_EDITED );
		if ( ! in_array( $status, self::statuses(), true ) ) {
			$status = self::STATUS_MANUALLY_EDITED;
		}

		$source_text     = (string) ( $args['source_text'] ?? '' );
		$translated_text = (string) ( $args['translated_text'] ?? '' );

		// An emptied translation reverts the segment to untranslated rather than
		// storing a blank string that would render as an empty title.
		if ( '' === trim( $translated_text ) ) {
			$status          = self::STATUS_MISSING;
			$translated_text = '';
		}

		$now = current_time( 'mysql', true );

		$data = array(
			'source_type'      => $source_type,
			'source_id'        => $source_id,
			'source_subtype'   => (string) ( $args['source_subtype'] ?? '' ),
			'language_id'      => $language_id,
			'field_key'        => $field_key,
			'segment_key'      => $segment_key,
			'segment_hash'     => self::segment_hash( $field_key, $segment_key ),
			'segment_kind'     => (string) ( $args['segment_kind'] ?? self::KIND_FIELD ),
			'segment_order'    => (int) ( $args['segment_order'] ?? 0 ),
			'text_format'      => $format,
			'source_text'      => $source_text,
			'source_hash'      => self::source_hash( $source_text, $format ),
			'norm_version'     => self::NORM_VERSION,
			'translated_text'  => $translated_text,
			'translation_hash' => self::translation_hash( $translated_text ),
			'status'           => $status,
			'is_stale'         => 0,
			'translated_by'    => (int) ( $args['translated_by'] ?? get_current_user_id() ),
			'updated_at'       => $now,
		);

		$this->upsert( $data, $now );
		$this->invalidate( $source_type, $source_id, $language_id );

		if ( function_exists( 'do_action' ) ) {
			/**
			 * Fires after a translation segment is saved.
			 *
			 * @since 0.1.0
			 *
			 * @param string $source_type Source type.
			 * @param int    $source_id   Source object ID.
			 * @param int    $language_id Language ID.
			 */
			\do_action( 'aiml_translation_saved', $source_type, $source_id, $language_id );
		}

		return true;
	}

	/**
	 * Reconciles stored segments against the current source content.
	 *
	 * Runs for every language when the canonical object changes. Translated
	 * text and workflow status are never touched here (invariant I6): a source
	 * edit flags work for review, it does not discard it.
	 *
	 * @param string                      $source_type    Source type.
	 * @param int                         $source_id      Source object id.
	 * @param string                      $source_subtype Post type or taxonomy.
	 * @param array<string, array<mixed>> $segments      Extracted source segments keyed by segment key.
	 */
	public function sync_source( string $source_type, int $source_id, string $source_subtype, array $segments ): void {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT translation_id, language_id, segment_key, source_hash, text_format, status FROM ' . Schema::translations() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' WHERE source_type = %s AND source_id = %d',
				$source_type,
				$source_id
			)
		);

		if ( array() === (array) $rows ) {
			return;
		}

		$now     = current_time( 'mysql', true );
		$touched = array();

		foreach ( (array) $rows as $row ) {
			$key = (string) $row->segment_key;

			if ( ! isset( $segments[ $key ] ) ) {
				// The segment no longer exists in the source. Mark it rather
				// than delete it, so reverting the source restores the work.
				if ( self::STATUS_IGNORED !== $row->status ) {
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						Schema::translations(),
						array(
							'status'     => self::STATUS_IGNORED,
							'error_code' => 'orphaned',
							'updated_at' => $now,
						),
						array( 'translation_id' => (int) $row->translation_id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);

					$touched[ (int) $row->language_id ] = true;
				}

				continue;
			}

			$segment = $segments[ $key ];
			$format  = (string) ( $segment['text_format'] ?? self::FORMAT_PLAIN );
			$text    = (string) ( $segment['source_text'] ?? '' );
			$hash    = self::source_hash( $text, $format );

			if ( $hash === (string) $row->source_hash ) {
				continue;
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				Schema::translations(),
				array(
					'source_text'    => $text,
					'source_hash'    => $hash,
					'norm_version'   => self::NORM_VERSION,
					'source_subtype' => $source_subtype,
					'is_stale'       => 1,
					'updated_at'     => $now,
				),
				array( 'translation_id' => (int) $row->translation_id ),
				array( '%s', '%s', '%d', '%s', '%d', '%s' ),
				array( '%d' )
			);

			$touched[ (int) $row->language_id ] = true;
		}

		foreach ( array_keys( $touched ) as $language_id ) {
			$this->invalidate( $source_type, $source_id, (int) $language_id );
		}
	}

	/**
	 * Deletes every segment of an object in one language.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Language id.
	 */
	public function delete_object( string $source_type, int $source_id, int $language_id ): void {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::translations(),
			array(
				'source_type' => $source_type,
				'source_id'   => $source_id,
				'language_id' => $language_id,
			),
			array( '%s', '%d', '%d' )
		);

		$this->invalidate( $source_type, $source_id, $language_id );
	}

	// -- Internals --

	/**
	 * Inserts or updates a row by segment identity.
	 *
	 * Uses ON DUPLICATE KEY UPDATE against `segment_identity` so a replayed
	 * write is a no-op rather than a duplicate row.
	 *
	 * @param array<string, mixed> $data Column values.
	 * @param string               $now  Timestamp for created_at on insert.
	 */
	private function upsert( array $data, string $now ): void {
		global $wpdb;

		$data['created_at'] = $now;

		$columns      = array_keys( $data );
		$placeholders = array();
		$values       = array();

		foreach ( $columns as $column ) {
			$value = $data[ $column ];

			if ( is_int( $value ) ) {
				$placeholders[] = '%d';
			} else {
				$placeholders[] = '%s';
			}

			$values[] = $value;
		}

		$updatable = array_diff( $columns, array( 'created_at', 'source_type', 'source_id', 'language_id', 'segment_hash' ) );

		$assignments = array();
		foreach ( $updatable as $column ) {
			$assignments[] = sprintf( '%1$s = VALUES(%1$s)', $column );
		}

		$sql = 'INSERT INTO ' . Schema::translations()
			. ' (' . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $placeholders ) . ')'
			. ' ON DUPLICATE KEY UPDATE ' . implode( ', ', $assignments );

		$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	}

	/**
	 * Drops the cached segment map for an object and language.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source object id.
	 * @param int    $language_id Language id.
	 */
	private function invalidate( string $source_type, int $source_id, int $language_id ): void {
		$this->cache->delete( sprintf( 'seg:%s:%d', $source_type, $source_id ), $language_id );
	}

	/**
	 * Casts a raw row's numeric columns.
	 *
	 * @param object $row Raw row from $wpdb.
	 */
	private function hydrate( object $row ): object {
		$row->translation_id = (int) $row->translation_id;
		$row->source_id      = (int) $row->source_id;
		$row->language_id    = (int) $row->language_id;
		$row->segment_order  = (int) $row->segment_order;
		$row->norm_version   = (int) $row->norm_version;
		$row->is_stale       = (bool) $row->is_stale;
		$row->status         = (string) $row->status;
		$row->segment_key    = (string) $row->segment_key;
		$row->field_key      = (string) $row->field_key;
		$row->text_format    = (string) $row->text_format;

		return $row;
	}

	// -- Normalization primitives --

	/**
	 * Converts CRLF and lone CR to LF.
	 *
	 * @param string $text Input.
	 */
	private static function normalize_line_endings( string $text ): string {
		return (string) preg_replace( '/\r\n?/', "\n", $text );
	}

	/**
	 * Rewrites every representation of a non-breaking space to the codepoint.
	 *
	 * Keeps the non-breaking property intact, which matters in HTML where the
	 * difference is visible.
	 *
	 * @param string $text Input.
	 */
	private static function canonicalize_nbsp( string $text ): string {
		return (string) str_replace( array( '&nbsp;', '&#160;', '&#xA0;', '&#xa0;' ), "\xC2\xA0", $text );
	}

	/**
	 * Reduces every representation of a non-breaking space to a plain space.
	 *
	 * @param string $text Input.
	 */
	private static function nbsp_to_space( string $text ): string {
		return (string) str_replace(
			array( '&nbsp;', '&#160;', '&#xA0;', '&#xa0;', "\xC2\xA0" ),
			' ',
			$text
		);
	}

	/**
	 * Re-encodes JSON canonically, or returns the raw bytes when invalid.
	 *
	 * Invalid JSON is never "repaired": doing so would let two genuinely
	 * different broken documents hash the same.
	 *
	 * @param string $text Input.
	 */
	private static function canonicalize_json( string $text ): string {
		$decoded = json_decode( $text, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return $text;
		}

		$sorted = self::sort_recursive( $decoded );

		// Deliberately json_encode() and not wp_json_encode(): this helper has
		// to run in the unit suite, which loads no WordPress.
		$encoded = json_encode( $sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode

		return false === $encoded ? $text : $encoded;
	}

	/**
	 * Sorts associative array keys recursively, leaving lists in order.
	 *
	 * @param mixed $value Decoded JSON value.
	 * @return mixed
	 */
	private static function sort_recursive( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );

		$value = array_map( array( self::class, 'sort_recursive' ), $value );

		if ( ! $is_list ) {
			ksort( $value );
		}

		return $value;
	}
}
