<?php
/**
 * Strategy F attribute registration integration.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\AttributeRegistrar;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Settings;

/**
 * Strategy F attribute registration integration.
 */
final class BlockAttributeRegistrationTest extends AimlTestCase {

	public function test_registration_is_disabled_by_default(): void {
		$settings = new Settings();
		$this->assertFalse( $settings->block_attr_registration_enabled() );

		$registrar = new AttributeRegistrar( $settings, new BlockRegistry() );
		$registrar->register();

		$metadata = apply_filters(
			'block_type_metadata',
			array(
				'name'       => 'core/paragraph',
				'attributes' => array(),
			)
		);

		$this->assertArrayNotHasKey( Contract::ATTR_NAME, $metadata['attributes'] );
	}

	public function test_supported_blocks_receive_attribute_when_enabled(): void {
		$settings = new Settings(
			array(
				'block_attr_registration_enabled' => true,
			)
		);

		$registrar = new AttributeRegistrar( $settings, new BlockRegistry() );
		$registrar->register();

		foreach ( BlockRegistry::SUPPORTED_BLOCKS as $block_name ) {
			$metadata = apply_filters(
				'block_type_metadata',
				array(
					'name'       => $block_name,
					'attributes' => array(),
				)
			);

			$this->assertSame(
				Contract::attribute_definition(),
				$metadata['attributes'][ Contract::ATTR_NAME ],
				$block_name
			);
		}
	}

	public function test_unsupported_blocks_do_not_receive_attribute(): void {
		$settings = new Settings(
			array(
				'block_attr_registration_enabled' => true,
			)
		);

		$registrar = new AttributeRegistrar( $settings, new BlockRegistry() );
		$registrar->register();

		$metadata = apply_filters(
			'block_type_metadata',
			array(
				'name'       => 'core/group',
				'attributes' => array(),
			)
		);

		$this->assertArrayNotHasKey( Contract::ATTR_NAME, $metadata['attributes'] );
	}

	public function test_editor_script_is_registered_when_enabled(): void {
		wp_deregister_script( AttributeRegistrar::SCRIPT_HANDLE );

		$settings = new Settings(
			array(
				'block_attr_registration_enabled' => true,
			)
		);

		$registrar = new AttributeRegistrar( $settings, new BlockRegistry() );
		$registrar->enqueue_editor_assets();

		$this->assertTrue( wp_script_is( AttributeRegistrar::SCRIPT_HANDLE, 'registered' ) );
	}

	public function test_editor_script_is_not_registered_when_disabled(): void {
		wp_deregister_script( AttributeRegistrar::SCRIPT_HANDLE );

		$settings = new Settings(
			array(
				'block_attr_registration_enabled' => false,
			)
		);

		$registrar = new AttributeRegistrar( $settings, new BlockRegistry() );
		$registrar->enqueue_editor_assets();

		$this->assertFalse( wp_script_is( AttributeRegistrar::SCRIPT_HANDLE, 'registered' ) );
	}
}
