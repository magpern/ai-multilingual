<?php
/**
 * Narrow SurfaceCapability support tokens (facts only).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface;

/**
 * Capability declaration names for {@see SurfaceCapability::supports()}.
 */
final class SurfaceCapabilityNames {

	public const INSPECT        = 'inspect';
	public const TRANSLATE      = 'translate';
	public const MUTATE         = 'mutate';
	public const JOBS           = 'jobs';
	public const PUBLISH_INPUTS = 'publish_inputs';
	public const OVERLAY        = 'overlay';
	public const STALE_OBSERVE  = 'stale_observe';

	/**
	 * All capability name tokens.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::INSPECT,
			self::TRANSLATE,
			self::MUTATE,
			self::JOBS,
			self::PUBLISH_INPUTS,
			self::OVERLAY,
			self::STALE_OBSERVE,
		);
	}
}
