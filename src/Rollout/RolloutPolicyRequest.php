<?php
/**
 * Rollout policy evaluation request facts.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Rollout;

/**
 * Immutable input to {@see RolloutPolicyService}.
 */
final class RolloutPolicyRequest {

	/**
	 * Builds a frontend policy request.
	 *
	 * @param int    $post_id       Source post ID.
	 * @param string $post_type     Post type slug.
	 * @param string $language_code Target language code.
	 * @param bool   $is_frontend   Whether this is a frontend render request.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly string $post_type,
		public readonly string $language_code,
		public readonly bool $is_frontend = true,
	) {
	}
}
