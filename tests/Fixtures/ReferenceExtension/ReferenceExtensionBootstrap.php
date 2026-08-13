<?php
/**
 * Black-box reference extension for Extension API v1 proof (tests only).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Fixtures\ReferenceExtension;

use AIMultilingual\Extension\Block\ExtensionBlockAdapter;
use AIMultilingual\Extension\ExtensionManifest;
use AIMultilingual\Extension\ExtensionMetaDefinition;
use AIMultilingual\Extension\ExtensionRegistrar;
use AIMultilingual\Extension\RegisteredExtension;
use AIMultilingual\Translation\Store;

/**
 * Registers one public m: meta field and one custom block adapter.
 */
final class ReferenceExtensionBootstrap {

	public const EXTENSION_ID = 'aiml_reference_ext';

	public const META_KEY = '_aiml_ref_ext_subtitle';

	public const BLOCK_NAME = 'aiml/reference-callout';

	public const FILTER_META = 'aiml_reference_ext_meta_overlay';

	/**
	 * Hooks reference extension into aiml_register_extensions.
	 */
	public static function register_hooks(): void {
		add_action(
			'aiml_register_extensions',
			array( self::class, 'register' ),
			10,
			1
		);
	}

	/**
	 * @param ExtensionRegistrar $registrar Public registrar.
	 */
	public static function register( ExtensionRegistrar $registrar ): RegisteredExtension {
		$handle = $registrar->register_extension(
			new ExtensionManifest(
				extension_id: self::EXTENSION_ID,
				version: '1.0.0',
				owned_namespaces: array( 'reference_ext' ),
			)
		);

		$handle->register_meta(
			new ExtensionMetaDefinition(
				namespace: 'reference_ext',
				source_type: Store::SOURCE_POST,
				meta_key: self::META_KEY,
				label: 'Reference extension subtitle',
				text_format: 'plain',
				admitted_subtypes: array( 'post', 'page' ),
			)
		);

		$handle->register_block_adapter( new ReferenceBlockAdapter() );

		return $handle;
	}
}
