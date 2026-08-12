<?php
/**
 * Detect Fluent Forms embeds on a WordPress post.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Integration\FluentForms;

use AIMultilingual\Elementor\ElementorDocumentDetector;
use WP_Post;

/**
 * Bounded host-local Fluent Forms embed detection (Elementor widget + shortcode).
 *
 * No sitewide enumeration. Forward discovery only — no reverse form→host index.
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
		return in_array( $form_id, $this->discover_form_ids( $post ), true );
	}

	/**
	 * Discover Fluent Forms IDs embedded on this host post only.
	 *
	 * @param WP_Post $post Host post.
	 * @return list<int>
	 */
	public function discover_form_ids( WP_Post $post ): array {
		$found = array();

		$elements = $this->detector->decode_elements( (int) $post->ID );
		if ( is_array( $elements ) ) {
			$this->collect_elementor_form_ids( $elements, $found );
		}

		$content = (string) ( $post->post_content ?? '' );
		if ( '' !== $content && preg_match_all( '/\[fluentform[^\]]*?\bid\s*=\s*[\'"]?(\d+)[\'"]?/i', $content, $matches ) ) {
			foreach ( $matches[1] as $raw ) {
				$id = (int) $raw;
				if ( $id > 0 ) {
					$found[ $id ] = $id;
				}
			}
		}

		$form_ids = array_values( $found );
		sort( $form_ids, SORT_NUMERIC );
		return $form_ids;
	}

	/**
	 * Recursively collect Elementor fluent-form-widget form ids.
	 *
	 * @param array<int, mixed> $nodes Elementor nodes.
	 * @param array<int, int>   $found Accumulator keyed by form id.
	 */
	private function collect_elementor_form_ids( array $nodes, array &$found ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$widget = isset( $node['widgetType'] ) && is_string( $node['widgetType'] ) ? $node['widgetType'] : '';
			if ( self::ELEMENTOR_WIDGET === $widget ) {
				$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
				$list     = isset( $settings['form_list'] ) ? (string) $settings['form_list'] : '';
				$id       = (int) $list;
				if ( $id > 0 ) {
					$found[ $id ] = $id;
				}
			}
			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->collect_elementor_form_ids( $node['elements'], $found );
			}
		}
	}
}
