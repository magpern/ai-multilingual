<?php
/**
 * A.2 Elementor control registry (allowlist).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * Registry-driven allowlist for A.2 first surface.
 */
final class ElementorControlRegistry {

	public const SUPPORT_DIRECT = 'directly_supported';

	public const SANITIZE_PLAIN = 'plain';

	public const SANITIZE_HTML = 'html';

	/**
	 * Registry entries keyed by widget then control.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private array $entries;

	/**
	 * Seeds the frozen A.2 allowlist.
	 */
	public function __construct() {
		$this->entries = array(
			'heading'     => array(
				'title' => array(
					'widget_type'   => 'heading',
					'control_key'   => 'title',
					'extractor'     => 'settings_string',
					'renderer'      => 'settings_string',
					'sanitization'  => self::SANITIZE_PLAIN,
					'support_state' => self::SUPPORT_DIRECT,
					'text_format'   => 'plain',
				),
			),
			'text-editor' => array(
				'editor' => array(
					'widget_type'   => 'text-editor',
					'control_key'   => 'editor',
					'extractor'     => 'settings_string',
					'renderer'      => 'settings_string',
					'sanitization'  => self::SANITIZE_HTML,
					'support_state' => self::SUPPORT_DIRECT,
					'text_format'   => 'html',
				),
			),
			'button'      => array(
				'text' => array(
					'widget_type'   => 'button',
					'control_key'   => 'text',
					'extractor'     => 'settings_string',
					'renderer'      => 'settings_string',
					'sanitization'  => self::SANITIZE_PLAIN,
					'support_state' => self::SUPPORT_DIRECT,
					'text_format'   => 'plain',
				),
			),
		);
	}

	/**
	 * Whether widget+control is supported.
	 *
	 * @param string $widget_type Widget type.
	 * @param string $control_key Control key.
	 */
	public function is_supported( string $widget_type, string $control_key ): bool {
		return null !== $this->get( $widget_type, $control_key );
	}

	/**
	 * Registry entry or null.
	 *
	 * @param string $widget_type Widget type.
	 * @param string $control_key Control key.
	 * @return array<string, mixed>|null
	 */
	public function get( string $widget_type, string $control_key ): ?array {
		if ( ! isset( $this->entries[ $widget_type ][ $control_key ] ) ) {
			return null;
		}

		$entry = $this->entries[ $widget_type ][ $control_key ];
		return is_array( $entry ) ? $entry : null;
	}

	/**
	 * All supported pairs.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function all(): array {
		$out = array();
		foreach ( $this->entries as $controls ) {
			foreach ( $controls as $entry ) {
				if ( is_array( $entry ) ) {
					$out[] = $entry;
				}
			}
		}
		return $out;
	}

	/**
	 * Whether widget type has any supported control.
	 *
	 * @param string $widget_type Widget type.
	 */
	public function is_supported_widget( string $widget_type ): bool {
		return isset( $this->entries[ $widget_type ] ) && array() !== $this->entries[ $widget_type ];
	}
}
