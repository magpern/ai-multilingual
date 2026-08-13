<?php
/**
 * Extension API v1 contract constants.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

/**
 * Frozen public Extension API v1 vocabulary.
 */
final class Contract {

	public const API_VERSION = 'v1';

	public const HOOK_REGISTER = 'aiml_register_extensions';

	public const EXTENSION_ID_PATTERN = '/^[a-z0-9_]+$/';

	public const MAX_EXTENSION_ID_LENGTH = 32;

	public const MAX_NAMESPACE_LENGTH = 32;

	public const NAMESPACE_PATTERN = '/^[a-z0-9_-]+$/';

	/**
	 * Code-owned namespaces extensions may not claim.
	 *
	 * @var list<string>
	 */
	public const RESERVED_NAMESPACES = array(
		'rankmath',
		'woocommerce',
		'aiml',
		'core',
	);

	/**
	 * Supported public segment key families for resolver lookups.
	 *
	 * @var list<string>
	 */
	public const RESOLVER_SEGMENT_PREFIXES = array(
		'm:',
		'p:',
		'b:',
	);
}
