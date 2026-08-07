<?php
/**
 * Detect Fluent Forms embeds on a WordPress post.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration\FluentForms;

use AIMultilingual\Elementor\ElementorDocumentDetector;
use WP_Post;

/**
 * Bounded Form #5 embed detection (Elementor widget + shortcode).
 */
final class FluentFormsEmbedDetector {

	public const ELEMENTOR_WIDGET = 'fluent-form-widget';

	/**
	 * Builds the embed detector.
	 *
	 * @param ElementorDocumentDetector $detector Elementor meta reader.
	 */
	public function __construct(
		private ElementorDocumentDetector $detector
	) {
	}

	/**
	 * Whether the post embeds the given Fluent Forms form ID.
	 *
	 * @param WP_Post $post    Post.
	 * @param int     $form_id Form ID.
	 */
	public function embeds_form( WP_Post $post, int $form_id ): bool {
		if ( $form_id <= 0 ) {
			return false;
		}

		$elements = $this->detector->decode_elements( (int) $post->ID );
		if ( is_array( $elements ) && $this->walk_elements( $elements, $form_id ) ) {
			return true;
		}

		$content = (string) ( $post->post_content ?? '' );
		if ( '' === $content ) {
			return false;
		}

		$pattern = '/\[fluentform[^\]]*?\bid\s*=\s*[\'"]?' . preg_quote( (string) $form_id, '/' ) . '[\'"]?/i';
		return 1 === preg_match( $pattern, $content );
	}

	/**
	 * Recursively search Elementor nodes for a fluent-form-widget embed.
	 *
	 * @param array<int, mixed> $nodes   Elementor nodes.
	 * @param int               $form_id Form ID.
	 */
	private function walk_elements( array $nodes, int $form_id ): bool {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$widget = isset( $node['widgetType'] ) && is_string( $node['widgetType'] ) ? $node['widgetType'] : '';
			if ( self::ELEMENTOR_WIDGET === $widget ) {
				$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
				$list     = isset( $settings['form_list'] ) ? (string) $settings['form_list'] : '';
				if ( (string) $form_id === $list ) {
					return true;
				}
			}
			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) && $this->walk_elements( $node['elements'], $form_id ) ) {
				return true;
			}
		}
		return false;
	}
}
