<?php
/**
 * Optional companion interface for integration-owned chrome CPT surfaces.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

/**
 * Additive Integration API companion for private-CPT site-wide chrome.
 *
 * Existing {@see PluginIntegrationInterface} implementors remain compatible
 * without implementing this interface.
 */
interface DeclaresChromeOwnedSurfaces {

	/**
	 * Declares integration-owned chrome surfaces (private CPT + field allowlist).
	 *
	 * @return list<ChromeOwnedSurfaceDeclaration>
	 */
	public function get_chrome_owned_surfaces(): array;
}
