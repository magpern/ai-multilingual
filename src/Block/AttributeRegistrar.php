<?php
/**
 * Strategy F block attribute registration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

use AIMultilingual\Settings;

/**
 * Registers `aimlBlockId` on supported blocks in PHP and the block editor.
 *
 * Registration is gated by {@see Settings::block_attr_registration_enabled()} for
 * pre-rollout environments. After production UUID rollout begins, registration
 * becomes a compatibility requirement and must not be treated as a normal
 * post-rollout kill switch (Strategy F plan §2.2).
 */
final class AttributeRegistrar {

	public const SCRIPT_HANDLE = 'aiml-block-editor';

	/**
	 * Builds the registrar.
	 *
	 * @param Settings      $settings Plugin settings.
	 * @param BlockRegistry $registry Supported block allowlist.
	 */
	public function __construct(
		private Settings $settings,
		private BlockRegistry $registry,
	) {
	}

	/**
	 * Registers WordPress hooks for Strategy F attribute registration.
	 */
	public function register(): void {
		add_filter( 'block_type_metadata', array( $this, 'filter_block_metadata' ), 10, 1 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Adds the Strategy F attribute to supported block metadata.
	 *
	 * @param array<string, mixed> $metadata Block type metadata.
	 * @return array<string, mixed>
	 */
	public function filter_block_metadata( array $metadata ): array {
		if ( ! $this->settings->block_attr_registration_enabled() ) {
			return $metadata;
		}

		$name = isset( $metadata['name'] ) ? (string) $metadata['name'] : '';

		if ( ! $this->registry->is_supported( $name ) ) {
			return $metadata;
		}

		if ( ! isset( $metadata['attributes'] ) || ! is_array( $metadata['attributes'] ) ) {
			$metadata['attributes'] = array();
		}

		$metadata['attributes'][ Contract::ATTR_NAME ] = Contract::attribute_definition();

		return $metadata;
	}

	/**
	 * Enqueues the editor mirror script when registration is enabled.
	 */
	public function enqueue_editor_assets(): void {
		if ( ! $this->settings->block_attr_registration_enabled() ) {
			return;
		}

		if ( ! defined( 'AIML_PLUGIN_FILE' ) ) {
			return;
		}

		$script_path = plugin_dir_path( AIML_PLUGIN_FILE ) . 'assets/block-editor.js';

		if ( ! is_readable( $script_path ) ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/block-editor.js', AIML_PLUGIN_FILE ),
			array( 'wp-blocks', 'wp-hooks', 'wp-data', 'wp-block-editor' ),
			(string) filemtime( $script_path ),
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'aimlBlockEditor',
			array(
				'attrName'         => Contract::ATTR_NAME,
				'attrDefinition'   => Contract::attribute_definition(),
				'supportedBlocks'  => BlockRegistry::SUPPORTED_BLOCKS,
				'registrationNote' => 'After production UUID rollout, attribute registration is a compatibility requirement and must remain enabled.',
			)
		);
	}
}
