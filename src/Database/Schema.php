<?php
/**
 * Table names and DDL.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Database;

/**
 * Single source of truth for this plugin's table names and their DDL.
 *
 * Every name is built from `$wpdb->prefix`; no table name is ever hardcoded
 * with a `wp_` prefix (invariant I9). The DDL is written out explicitly rather
 * than handed to `dbDelta()`: dbDelta's parser silently drops composite
 * prefix indexes and misparses several forms this schema depends on
 * (ADR-0003).
 */
final class Schema {

	/**
	 * Unprefixed table names.
	 */
	public const LANGUAGES    = 'aiml_languages';
	public const TRANSLATIONS = 'aiml_translations';
	public const TM           = 'aiml_tm';

	/**
	 * Prefixes a table name with the current site's table prefix.
	 *
	 * @param string $name Unprefixed table name.
	 */
	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . $name;
	}

	/**
	 * Fully qualified `aiml_languages` table name.
	 */
	public static function languages(): string {
		return self::table( self::LANGUAGES );
	}

	/**
	 * Fully qualified `aiml_translations` table name.
	 */
	public static function translations(): string {
		return self::table( self::TRANSLATIONS );
	}

	/**
	 * Fully qualified `aiml_tm` table name.
	 */
	public static function tm(): string {
		return self::table( self::TM );
	}

	/**
	 * Every table this plugin owns, in drop-safe order.
	 *
	 * @return string[]
	 */
	public static function all_tables(): array {
		return array(
			self::translations(),
			self::tm(),
			self::languages(),
		);
	}

	/**
	 * Charset and collation clause matching the host WordPress install.
	 */
	private static function charset_collate(): string {
		global $wpdb;

		return $wpdb->get_charset_collate();
	}

	/**
	 * DDL for the language configuration table.
	 *
	 * A single `status` column carries the three-state model
	 * (disabled / preview / published); it is VARCHAR rather than ENUM so a new
	 * state never requires an ALTER (ADR-0008). There is deliberately no
	 * `fallback_id`: the default language is the implicit fallback, and
	 * arbitrary language chains need a complete SEO policy first.
	 */
	public static function create_languages(): string {
		return 'CREATE TABLE IF NOT EXISTS ' . self::languages() . " (
			language_id  SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code         VARCHAR(12)       NOT NULL,
			locale       VARCHAR(20)       NOT NULL,
			name         VARCHAR(100)      NOT NULL,
			native_name  VARCHAR(100)      NOT NULL DEFAULT '',
			direction    VARCHAR(3)        NOT NULL DEFAULT 'ltr',
			is_default   TINYINT(1)        NOT NULL DEFAULT 0,
			status       VARCHAR(16)       NOT NULL DEFAULT 'preview',
			sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			created_at   DATETIME          NOT NULL,
			updated_at   DATETIME          NOT NULL,
			PRIMARY KEY (language_id),
			UNIQUE KEY code (code),
			KEY status_sort (status, sort_order)
		) ENGINE=InnoDB ROW_FORMAT=DYNAMIC " . self::charset_collate();
	}

	/**
	 * DDL for the segment store.
	 *
	 * `segment_identity` is the upsert key and keeps writes idempotent. It uses
	 * `segment_hash` rather than the raw (field_key, segment_key) pair because
	 * indexing those columns directly would cost roughly 1 KB per entry
	 * (ADR-0005). `object_lang` is the hot read path: one indexed query returns
	 * every segment for an object in a language, already in document order.
	 */
	public static function create_translations(): string {
		return 'CREATE TABLE IF NOT EXISTS ' . self::translations() . " (
			translation_id   BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
			source_type      VARCHAR(20)       NOT NULL,
			source_id        BIGINT UNSIGNED   NOT NULL,
			source_subtype   VARCHAR(32)       NOT NULL DEFAULT '',
			language_id      SMALLINT UNSIGNED NOT NULL,
			field_key        VARCHAR(64)       NOT NULL,
			segment_key      VARCHAR(191)      NOT NULL,
			segment_hash     CHAR(40)          NOT NULL,
			segment_kind     VARCHAR(16)       NOT NULL DEFAULT 'field',
			segment_order    INT UNSIGNED      NOT NULL DEFAULT 0,
			text_format      VARCHAR(16)       NOT NULL DEFAULT 'plain',
			source_text      LONGTEXT          NULL,
			source_hash      CHAR(40)          NOT NULL DEFAULT '',
			norm_version     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			translated_text  LONGTEXT          NULL,
			translation_hash CHAR(40)          NOT NULL DEFAULT '',
			status           VARCHAR(24)       NOT NULL DEFAULT 'missing',
			is_stale         TINYINT(1)        NOT NULL DEFAULT 0,
			provider         VARCHAR(32)       NOT NULL DEFAULT '',
			model            VARCHAR(64)       NOT NULL DEFAULT '',
			prompt_profile   VARCHAR(32)       NOT NULL DEFAULT '',
			prompt_version   VARCHAR(16)       NOT NULL DEFAULT '',
			glossary_version INT UNSIGNED      NOT NULL DEFAULT 0,
			tm_id            BIGINT UNSIGNED   NULL,
			translated_by    BIGINT UNSIGNED   NULL,
			reviewed_by      BIGINT UNSIGNED   NULL,
			reviewed_at      DATETIME          NULL,
			error_code       VARCHAR(32)       NOT NULL DEFAULT '',
			error_message    VARCHAR(500)      NOT NULL DEFAULT '',
			created_at       DATETIME          NOT NULL,
			updated_at       DATETIME          NOT NULL,
			PRIMARY KEY (translation_id),
			UNIQUE KEY segment_identity (source_type, source_id, segment_hash, language_id),
			KEY object_lang (source_type, source_id, language_id, segment_order),
			KEY lang_status (language_id, status, is_stale),
			KEY lang_subtype (language_id, source_type, source_subtype, status),
			KEY stale_sweep (language_id, is_stale, updated_at)
		) ENGINE=InnoDB ROW_FORMAT=DYNAMIC " . self::charset_collate();
	}

	/**
	 * DDL for the translation memory catalogue (ADR-0009 / F11).
	 *
	 * Identity is (source_hash, source_lang_id, target_lang_id, context) so
	 * write-back is an upsert. The `origin` column records provenance
	 * (human / ai / import / legacy) without conflating it with `quality`.
	 */
	public static function create_tm(): string {
		return 'CREATE TABLE IF NOT EXISTS ' . self::tm() . " (
			tm_id            BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
			source_lang_id   SMALLINT UNSIGNED NOT NULL,
			target_lang_id   SMALLINT UNSIGNED NOT NULL,
			source_hash      CHAR(40)          NOT NULL,
			source_text      LONGTEXT          NOT NULL,
			target_text      LONGTEXT          NOT NULL,
			text_format      VARCHAR(16)       NOT NULL DEFAULT 'plain',
			context          VARCHAR(64)       NOT NULL DEFAULT '',
			norm_version     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			origin           VARCHAR(16)       NOT NULL DEFAULT 'human',
			quality          VARCHAR(24)       NOT NULL DEFAULT 'human_approved',
			use_count        INT UNSIGNED      NOT NULL DEFAULT 0,
			glossary_version INT UNSIGNED      NOT NULL DEFAULT 0,
			created_at       DATETIME          NOT NULL,
			updated_at       DATETIME          NOT NULL,
			last_used_at     DATETIME          NULL,
			PRIMARY KEY (tm_id),
			UNIQUE KEY tm_identity (source_hash, source_lang_id, target_lang_id, context),
			KEY fuzzy_lookup (source_lang_id, target_lang_id, text_format),
			KEY origin_filter (origin, source_lang_id, target_lang_id)
		) ENGINE=InnoDB ROW_FORMAT=DYNAMIC " . self::charset_collate();
	}
}
