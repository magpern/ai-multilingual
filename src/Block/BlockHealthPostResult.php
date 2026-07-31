<?php
/**
 * Strategy F per-post health scan result.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Read-only health outcome for one canonical post.
 */
final class BlockHealthPostResult {

	/**
	 * Builds one post result.
	 *
	 * @param int                          $post_id     Post id.
	 * @param string                       $post_type   Post type.
	 * @param string|null                  $skip_reason Eligibility skip reason, if any.
	 * @param BlockIdentityCompliance|null $compliance UUID compliance when scanned.
	 * @param string|null                  $error_code  Scan error code, if any.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly string $post_type,
		public readonly ?string $skip_reason = null,
		public readonly ?BlockIdentityCompliance $compliance = null,
		public readonly ?string $error_code = null,
	) {
	}

	/**
	 * Whether the post was eligible and UUID-compliant.
	 */
	public function is_compliant(): bool {
		return null === $this->skip_reason
			&& null === $this->error_code
			&& $this->compliance instanceof BlockIdentityCompliance
			&& $this->compliance->is_compliant();
	}
}
