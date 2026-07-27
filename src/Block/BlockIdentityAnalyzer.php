<?php
/**
 * Strategy F read-only block identity analyzer.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Inspects parsed block trees for UUID compliance without mutation.
 */
final class BlockIdentityAnalyzer {

	/**
	 * Builds the analyzer.
	 *
	 * @param BlockRegistry $registry Block eligibility policy.
	 */
	public function __construct(
		private BlockRegistry $registry,
	) {
	}

	/**
	 * Analyzes one canonical post body for UUID compliance.
	 *
	 * @param string $content Canonical post_content.
	 */
	public function analyze_content( string $content ): BlockIdentityCompliance {
		if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'has_blocks' ) || ! has_blocks( $content ) ) {
			return new BlockIdentityCompliance();
		}

		$blocks = parse_blocks( $content );
		if ( ! is_array( $blocks ) ) {
			return new BlockIdentityCompliance();
		}

		$eligible    = 0;
		$missing     = 0;
		$malformed   = 0;
		$uuid_counts = array();

		( new BlockTreeWalker() )->walk(
			$blocks,
			function ( array $block ) use ( &$eligible, &$missing, &$malformed, &$uuid_counts ): void {
				if ( ! $this->registry->is_eligible( $block ) ) {
					return;
				}

				++$eligible;

				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				$uuid  = isset( $attrs[ Contract::ATTR_NAME ] ) ? (string) $attrs[ Contract::ATTR_NAME ] : '';

				if ( '' === $uuid ) {
					++$missing;

					return;
				}

				if ( ! UuidValidator::is_valid_non_empty( $uuid ) ) {
					++$malformed;

					return;
				}

				$uuid_counts[ $uuid ] = ( $uuid_counts[ $uuid ] ?? 0 ) + 1;
			}
		);

		$duplicate = 0;

		( new BlockTreeWalker() )->walk(
			$blocks,
			function ( array $block ) use ( &$duplicate, $uuid_counts ): void {
				if ( ! $this->registry->is_eligible( $block ) ) {
					return;
				}

				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				$uuid  = isset( $attrs[ Contract::ATTR_NAME ] ) ? (string) $attrs[ Contract::ATTR_NAME ] : '';

				if ( ! UuidValidator::is_valid_non_empty( $uuid ) ) {
					return;
				}

				if ( ( $uuid_counts[ $uuid ] ?? 0 ) > 1 ) {
					++$duplicate;
				}
			}
		);

		return new BlockIdentityCompliance( $eligible, $missing, $malformed, $duplicate );
	}
}
