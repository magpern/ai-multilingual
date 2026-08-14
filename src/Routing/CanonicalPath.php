<?php
/**
 * Normalized URL path identity.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

/**
 * Immutable canonical path string produced by PathCanonicalizer.
 */
final class CanonicalPath {

	/**
	 * Normalized path with exactly one leading slash.
	 */
	private string $path;

	/**
	 * @param string $path Canonical path value.
	 */
	public function __construct( string $path ) {
		$this->path = $path;
	}

	/**
	 * Canonical path string.
	 */
	public function to_string(): string {
		return $this->path;
	}

	/**
	 * @param CanonicalPath $other Other path.
	 */
	public function equals( CanonicalPath $other ): bool {
		return $this->path === $other->to_string();
	}
}
