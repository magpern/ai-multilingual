<?php
/**
 * Request-scoped language state.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Language;

/**
 * Holds the language the current request is being rendered in.
 *
 * Kept separate from LanguageResolver on purpose: resolution is a pure
 * function evaluated once, this is mutable state read by every overlay filter
 * for the rest of the request. Merging them to save a class would have put a
 * switch stack inside something that is supposed to have no state at all
 * (ADR-0008).
 *
 * The switch stack exists for the contexts that render outside the current
 * request's language — transactional emails in the order's language,
 * background translation jobs, admin previews. Those all run in code paths
 * where an exception must not leave the context pointing at the wrong
 * language, which is what `with()` guarantees.
 */
final class LanguageContext {

	/**
	 * Language the request is rendering in.
	 *
	 * @var object|null
	 */
	private ?object $current = null;

	/**
	 * The site's default (source) language.
	 *
	 * @var object|null
	 */
	private ?object $default = null;

	/**
	 * Saved languages for switch_to()/restore().
	 *
	 * @var array<int, object|null>
	 */
	private array $stack = array();

	/**
	 * Sets the language for this request.
	 *
	 * @param object|null $language Language row.
	 */
	public function set_current( ?object $language ): void {
		$this->current = $language;
	}

	/**
	 * Language the request is rendering in, or null before resolution.
	 */
	public function current(): ?object {
		return $this->current;
	}

	/**
	 * Sets the site's default language.
	 *
	 * @param object|null $language Language row.
	 */
	public function set_default( ?object $language ): void {
		$this->default = $language;
	}

	/**
	 * The site's default language, or null when none is configured.
	 */
	public function default(): ?object {
		return $this->default;
	}

	/**
	 * Current language id, or 0 when unresolved.
	 */
	public function current_id(): int {
		return null === $this->current ? 0 : (int) $this->current->language_id;
	}

	/**
	 * Whether the request is rendering in the default language.
	 *
	 * Unresolved counts as default: overlays must stay inert until a language
	 * has actually been established.
	 */
	public function is_default(): bool {
		if ( null === $this->current ) {
			return true;
		}

		return ! empty( $this->current->is_default );
	}

	/**
	 * Whether a translated rendering is expected for this request.
	 */
	public function is_translated(): bool {
		return null !== $this->current && ! $this->is_default();
	}

	/**
	 * Temporarily switches the current language.
	 *
	 * Prefer `with()`; use this pair only when the switch cannot be expressed
	 * as a single callable, and always restore in a finally block.
	 *
	 * @param object|null $language Language to switch to.
	 */
	public function switch_to( ?object $language ): void {
		$this->stack[] = $this->current;
		$this->current = $language;
	}

	/**
	 * Restores the language saved by the matching switch_to().
	 */
	public function restore(): void {
		if ( array() === $this->stack ) {
			return;
		}

		$this->current = array_pop( $this->stack );
	}

	/**
	 * Runs a callable with a different current language, always restoring it.
	 *
	 * @param object|null $language Language to render in.
	 * @param callable    $callback Work to run.
	 * @return mixed Whatever the callback returns.
	 */
	public function with( ?object $language, callable $callback ) {
		$this->switch_to( $language );

		try {
			return $callback();
		} finally {
			$this->restore();
		}
	}
}
