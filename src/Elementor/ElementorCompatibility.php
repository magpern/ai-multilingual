<?php
/**
 * Elementor version / availability boundary.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * Centralizes Elementor availability and version policy for A.2.
 */
final class ElementorCompatibility {

	public const STATUS_AVAILABLE   = 'available';
	public const STATUS_UNAVAILABLE = 'unavailable';
	public const STATUS_UNSUPPORTED = 'unsupported';

	/**
	 * Whether Elementor plugin is loaded.
	 */
	public function is_elementor_available(): bool {
		return class_exists( '\Elementor\Plugin' ) || defined( 'ELEMENTOR_VERSION' );
	}

	/**
	 * Installed Elementor version string or empty.
	 */
	public function elementor_version(): string {
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			return (string) ELEMENTOR_VERSION;
		}

		return '';
	}

	/**
	 * Installed Elementor Pro version or empty.
	 */
	public function elementor_pro_version(): string {
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return (string) ELEMENTOR_PRO_VERSION;
		}

		return '';
	}

	/**
	 * Whether the installed free Elementor version is in the A.2 supported family.
	 */
	public function is_version_supported(): bool {
		return $this->is_version_string_supported( $this->elementor_version() );
	}

	/**
	 * Whether a version string is in the A.2 supported family.
	 *
	 * @param string $version Version string.
	 */
	public function is_version_string_supported( string $version ): bool {
		if ( '' === $version ) {
			return false;
		}

		return 0 === strpos( $version, Contract::SUPPORTED_MAJOR_MINOR . '.' )
			|| Contract::SUPPORTED_MAJOR_MINOR === $version;
	}

	/**
	 * Compatibility status for diagnostics.
	 *
	 * @return self::STATUS_*
	 */
	public function status(): string {
		if ( ! $this->is_elementor_available() ) {
			return self::STATUS_UNAVAILABLE;
		}

		if ( ! $this->is_version_supported() ) {
			return self::STATUS_UNSUPPORTED;
		}

		return self::STATUS_AVAILABLE;
	}

	/**
	 * Whether Elementor overlays may run (available + supported).
	 */
	public function overlays_allowed(): bool {
		if ( array_key_exists( 'aiml_test_elementor_overlays_allowed', $GLOBALS ) ) {
			return (bool) $GLOBALS['aiml_test_elementor_overlays_allowed'];
		}

		return self::STATUS_AVAILABLE === $this->status();
	}

	/**
	 * Snapshot for diagnostics (no secrets).
	 *
	 * @return array<string, mixed>
	 */
	public function snapshot(): array {
		return array(
			'status'            => $this->status(),
			'elementor_version' => $this->elementor_version(),
			'elementor_pro'     => $this->elementor_pro_version(),
			'supported_family'  => Contract::SUPPORTED_MAJOR_MINOR . '.x',
			'overlays_allowed'  => $this->overlays_allowed(),
		);
	}
}
