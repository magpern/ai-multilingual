<?php
/**
 * A.2/A.3 Elementor control registry (allowlist).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

use AIMultilingual\Elementor\Strategy\ElementorStrategyFactory;

/**
 * Registry-driven allowlist — sole production admission surface.
 */
final class ElementorControlRegistry {

	public const SUPPORT_DIRECT = 'directly_supported';

	public const SUPPORT_ADAPTER = 'adapter';

	public const SANITIZE_PLAIN = 'plain';

	public const SANITIZE_HTML = 'html';

	public const NESTING_NONE = 'none';

	public const NESTING_REPEATER = 'repeater';

	public const IDENTITY_DOCUMENT_CONTROL = 'document_control';

	public const IDENTITY_REPEATER_ID = 'repeater_id';

	public const OWNERSHIP_DOCUMENT = 'document';

	/**
	 * Registry entries keyed by widget then control.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private array $entries;

	/**
	 * Seeds the admitted allowlist.
	 */
	public function __construct() {
		$this->entries = array(
			'heading'     => array(
				'title' => $this->flat_entry( 'heading', 'title', ElementorStrategyFactory::EXTRACTOR_SETTINGS_STRING, self::SANITIZE_PLAIN, 'plain' ),
			),
			'text-editor' => array(
				'editor' => $this->flat_entry( 'text-editor', 'editor', ElementorStrategyFactory::EXTRACTOR_SETTINGS_STRING, self::SANITIZE_HTML, 'html' ),
			),
			'button'      => array(
				'text' => $this->flat_entry( 'button', 'text', ElementorStrategyFactory::EXTRACTOR_SETTINGS_STRING, self::SANITIZE_PLAIN, 'plain' ),
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

	/**
	 * Admit a control entry (used by admission WPs / tests).
	 *
	 * @param string               $widget_type Widget type.
	 * @param string               $control_key Control key.
	 * @param array<string, mixed> $entry       Full entry.
	 */
	public function admit( string $widget_type, string $control_key, array $entry ): void {
		$this->entries[ $widget_type ][ $control_key ] = $entry;
	}

	/**
	 * Flat document-control entry helper.
	 *
	 * @param string $widget_type Widget.
	 * @param string $control_key Control.
	 * @param string $extractor   Strategy name.
	 * @param string $sanitize    Sanitization.
	 * @param string $format      Text format.
	 * @return array<string, mixed>
	 */
	public function flat_entry( string $widget_type, string $control_key, string $extractor, string $sanitize, string $format ): array {
		return array(
			'widget_type'   => $widget_type,
			'control_key'   => $control_key,
			'nesting'       => self::NESTING_NONE,
			'identity'      => self::IDENTITY_DOCUMENT_CONTROL,
			'extractor'     => $extractor,
			'renderer'      => $extractor,
			'sanitization'  => $sanitize,
			'ownership'     => self::OWNERSHIP_DOCUMENT,
			'support_state' => self::SUPPORT_DIRECT,
			'text_format'   => $format,
			'compatibility' => array( 'elementor' => '4.2' ),
		);
	}

	/**
	 * Nested repeater-field entry helper.
	 *
	 * @param string $widget_type  Widget.
	 * @param string $repeater_key Repeater settings key.
	 * @param string $control_key  Nested field key.
	 * @param string $sanitize     Sanitization.
	 * @param string $format       Text format.
	 * @return array<string, mixed>
	 */
	public function repeater_entry( string $widget_type, string $repeater_key, string $control_key, string $sanitize, string $format ): array {
		return array(
			'widget_type'   => $widget_type,
			'control_key'   => $control_key,
			'repeater_key'  => $repeater_key,
			'nesting'       => self::NESTING_REPEATER,
			'identity'      => self::IDENTITY_REPEATER_ID,
			'extractor'     => ElementorStrategyFactory::EXTRACTOR_REPEATER_FIELD,
			'renderer'      => ElementorStrategyFactory::EXTRACTOR_REPEATER_FIELD,
			'sanitization'  => $sanitize,
			'ownership'     => self::OWNERSHIP_DOCUMENT,
			'support_state' => self::SUPPORT_ADAPTER,
			'text_format'   => $format,
			'compatibility' => array( 'elementor' => '4.2' ),
		);
	}
}
