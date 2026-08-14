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
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * Constructs a canonical path value object.
	 *
	 * @param string $path Canonical path value.
	 */
	public function __construct( string $path ) {
		$this->path = $path;
	}

	/**
	 * Returns the canonical path string.
	 */
	public function to_string(): string {
		return $this->path;
	}

	/**
	 * Compares two canonical paths for equality.
	 *
	 * @param CanonicalPath $other Other path.
	 */
	public function equals( CanonicalPath $other ): bool {
		return $this->path === $other->to_string();
	}
}
