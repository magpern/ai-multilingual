<?php
/**
 * Invalid URL path for canonicalization.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use RuntimeException;

/**
 * Raised when a path cannot be normalized for route identity.
 */
final class InvalidPathException extends RuntimeException {
}
