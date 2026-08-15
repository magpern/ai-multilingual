<?php
/**
 * P0 Settings Localized URLs honesty (admission + frontier copy).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Admin\SettingsPage;
use AIMultilingual\Routing\LocalizedUrlsActivationService;
use AIMultilingual\Routing\ReindexFrontierRepository;
use AIMultilingual\Routing\RoutingCapabilityAdmission;
use AIMultilingual\Routing\RoutingCapabilityRegistry;
use AIMultilingual\Settings;

/**
 * OC5–OC8 Settings honesty smoke.
 */
final class P0LocalizedUrlsSettingsHonestyTest extends AimlTestCase {

	public function test_settings_honesty_renders_admission_and_frontier_copy(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'settings_page_' . SettingsPage::SETTINGS_SLUG );

		$settings = new Settings(
			array(
				'localized_urls_state'                     => LocalizedUrlsActivationService::STATE_ON,
				'localized_urls_verified_capability_epoch' => 0,
				'localized_urls_admitted_capabilities'     => array(),
			)
		);
		update_option( Settings::OPTION, $settings->get() );

		$activation = new LocalizedUrlsActivationService( $settings );
		$admission  = new RoutingCapabilityAdmission( $settings, new RoutingCapabilityRegistry() );
		$frontier   = new ReindexFrontierRepository();
		$page       = new SettingsPage(
			$settings,
			$this->languages,
			null,
			$activation,
			$admission,
			$frontier
		);

		ob_start();
		$page->render_settings();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Localized URLs', $html );
		$this->assertStringContainsString( 'Capability admission', $html );
		$this->assertStringContainsString( 'Term archives', $html );
		$this->assertStringContainsString( 'Not admitted yet', $html );
		$this->assertStringContainsString( 'Hierarchy reindex / frontier', $html );
		$this->assertStringContainsString( 'not yet processed', $html );
		$this->assertStringNotContainsString( 'AIML_INTERNAL_DIAG', $html );
	}
}
