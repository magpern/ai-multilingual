<?php
/**
 * Object-cache wrapper.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Cache;

/**
 * The only place this plugin talks to the object cache.
 *
 * Every key embeds the language plus two version counters — a global epoch and
 * a per-language counter — so invalidation is a single integer write rather
 * than key enumeration. That matters here: the target stack runs Redis through
 * Predis (a userland PHP client) where prefix deletion is not available cheaply
 * and per-key enumeration would cost more than the waste it avoids.
 *
 * The trade-off is deliberate and documented (ADR-0012): one translation write
 * invalidates every cached entry for that language, not just the affected
 * object. That is always correct, and cheap at the current content scale. A
 * per-object counter can be added to the same key shape later without changing
 * any caller.
 *
 * Nothing containing cart state, nonces, prices, stock or per-user data is ever
 * cached here — only pure functions of (source content, translations,
 * language).
 */
final class Cache {

	/**
	 * Object cache group.
	 */
	public const GROUP = 'aiml';

	/**
	 * Option holding the global cache epoch.
	 */
	public const VERSION_OPTION = 'aiml_cache_version';

	/**
	 * Option prefix for the per-language counters.
	 */
	private const LANG_VERSION_PREFIX = 'aiml_lang_version_';

	/**
	 * Per-process high-water mark for each version option.
	 *
	 * `version()` normally just reads the persisted option, which is correct
	 * in production where it only ever increases. Under `WP_UnitTestCase`,
	 * every test runs inside a transaction that rolls back, so a bump made in
	 * one test (e.g. via `add_language()`) is undone before the next test
	 * begins — and if two tests bump the same option by the same number of
	 * steps from that shared rollback baseline, they land on the identical
	 * persisted value. A `Languages`/`Store` instance built once at plugin
	 * boot (idempotent `Plugin::init()`) and reused for the rest of the
	 * process would then see `$memo_epoch === $epoch` succeed spuriously and
	 * serve another test's stale, since-rolled-back rows. Tracking the
	 * highest value this process has ever produced — memory the rollback
	 * cannot touch — keeps every bump strictly increasing for the process's
	 * lifetime without changing anything in production, where the persisted
	 * value is already the higher of the two.
	 *
	 * @var array<string, int>
	 */
	private static array $high_water_mark = array();

	/**
	 * Reads a value, or null when absent.
	 *
	 * @param string $key         Unversioned key.
	 * @param int    $language_id Language the value belongs to.
	 * @return mixed|null
	 */
	public function get( string $key, int $language_id ) {
		$found = false;
		$value = wp_cache_get( $this->key( $key, $language_id ), self::GROUP, false, $found );

		return $found ? $value : null;
	}

	/**
	 * Stores a value.
	 *
	 * @param string $key         Unversioned key.
	 * @param int    $language_id Language the value belongs to.
	 * @param mixed  $value       Value to store.
	 * @param int    $ttl         Lifetime in seconds; 0 means the backend default.
	 */
	public function set( string $key, int $language_id, $value, int $ttl = 0 ): void {
		wp_cache_set( $this->key( $key, $language_id ), $value, self::GROUP, $ttl );
	}

	/**
	 * Deletes a single entry.
	 *
	 * @param string $key         Unversioned key.
	 * @param int    $language_id Language the value belongs to.
	 */
	public function delete( string $key, int $language_id ): void {
		wp_cache_delete( $this->key( $key, $language_id ), self::GROUP );
	}

	/**
	 * Invalidates every cached entry for one language.
	 *
	 * @param int $language_id Language to invalidate.
	 */
	public function flush_language( int $language_id ): void {
		$this->bump( self::LANG_VERSION_PREFIX . $language_id );
	}

	/**
	 * Invalidates every cached entry for every language.
	 *
	 * Used when language configuration changes or the plugin is upgraded.
	 */
	public function flush_all(): void {
		$this->bump( self::VERSION_OPTION );
	}

	/**
	 * Advances a version counter, recording the new value in the per-process
	 * high-water mark alongside the persisted option. See
	 * {@see self::$high_water_mark} for why both are needed.
	 *
	 * @param string $option Option name holding the counter.
	 */
	private function bump( string $option ): void {
		$next                             = $this->version( $option ) + 1;
		self::$high_water_mark[ $option ] = $next;

		update_option( $option, $next, true );
	}

	/**
	 * Builds the fully versioned cache key.
	 *
	 * @param string $key         Unversioned key.
	 * @param int    $language_id Language the value belongs to.
	 */
	private function key( string $key, int $language_id ): string {
		$global = $this->version( self::VERSION_OPTION );
		$lang   = $this->version( self::LANG_VERSION_PREFIX . $language_id );

		return sprintf( '%d:%d:l%d:%s', $global, $lang, $language_id, $key );
	}

	/**
	 * Reads a version counter.
	 *
	 * Deliberately not memoized on the instance. Both counters are autoloaded
	 * options, so this is an array lookup in the already-loaded options cache,
	 * not a query — and reading the live value means every holder of a Cache
	 * agrees on the current key namespace. Memoizing per instance would let two
	 * service graphs in one process compute different keys, so one could
	 * invalidate an entry the other still reads.
	 *
	 * @param string $option Option name holding the counter.
	 */
	private function version( string $option ): int {
		$persisted = (int) get_option( $option, 0 );
		$mark      = self::$high_water_mark[ $option ] ?? 0;

		return max( $persisted, $mark );
	}
}
