<?php
/**
 * Strategy F admin settings controls.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Admin\SettingsPage;
use AIMultilingual\Block\FeatureFlags;
use AIMultilingual\Settings;

/**
 * Settings UI, dependency normalization, and flag audit hooks.
 */
final class StrategyFSettingsTest extends AimlTestCase {

	private SettingsPage $page;

	protected function setUp(): void {
		parent::setUp();

		$this->page = new SettingsPage( new Settings(), $this->languages );
		$this->page->register();
	}

	protected function tearDown(): void {
		delete_option( Settings::OPTION );
		parent::tearDown();
	}

	public function test_settings_ui_exposes_production_flags_in_dependency_order(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'settings_page_' . SettingsPage::SETTINGS_SLUG );

		$html = $this->render_settings_html();

		$positions = array();
		foreach ( FeatureFlags::PRODUCTION_FLAGS as $flag ) {
			$pos = strpos( $html, 'name="aiml_settings[' . $flag . ']"' );
			$this->assertNotFalse( $pos, "Expected settings control for {$flag}." );
			$positions[ $flag ] = $pos;
		}

		$this->assertLessThan(
			$positions[ FeatureFlags::INJECTION ],
			$positions[ FeatureFlags::REGISTRATION ]
		);
		$this->assertLessThan(
			$positions[ FeatureFlags::EXTRACTION ],
			$positions[ FeatureFlags::INJECTION ]
		);
		$this->assertLessThan(
			$positions[ FeatureFlags::FRONTEND_RENDER ],
			$positions[ FeatureFlags::EXTRACTION ]
		);
	}

	public function test_reserved_flags_are_not_exposed_in_settings_ui(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'settings_page_' . SettingsPage::SETTINGS_SLUG );

		$html = $this->render_settings_html();

		$this->assertStringContainsString( 'Strategy F — Gutenberg block translation', $html );
		$this->assertStringNotContainsString( 'name="aiml_settings[' . FeatureFlags::MIGRATION . ']"', $html );
		$this->assertStringNotContainsString( 'name="aiml_settings[' . FeatureFlags::RENDER . ']"', $html );
	}

	public function test_diagnostics_show_saved_and_effective_state(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'settings_page_' . SettingsPage::SETTINGS_SLUG );

		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array(
					FeatureFlags::REGISTRATION => true,
					FeatureFlags::INJECTION    => true,
				)
			)
		);

		$html = $this->render_settings_html();

		$this->assertStringContainsString( 'aiml-strategy-f-diagnostics', $html );
		$this->assertStringContainsString( 'Combination valid:', $html );
		$this->assertStringContainsString( 'Frontend rendering active:', $html );
	}

	public function test_frontend_rendering_checkbox_requires_confirmation_script(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'settings_page_' . SettingsPage::SETTINGS_SLUG );

		$html = $this->render_settings_html();

		$this->assertStringContainsString( 'data-aiml-requires-confirm="1"', $html );
		$this->assertStringContainsString( 'window.confirm', $html );
		$this->assertStringContainsString( 'kill switch', $html );
	}

	public function test_valid_combination_persists_through_sanitize_callback(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$clean = $this->page->sanitize_settings(
			array(
				FeatureFlags::REGISTRATION => '1',
				FeatureFlags::INJECTION    => '1',
				FeatureFlags::EXTRACTION   => '1',
			)
		);

		$this->assertTrue( $clean[ FeatureFlags::REGISTRATION ] );
		$this->assertTrue( $clean[ FeatureFlags::INJECTION ] );
		$this->assertTrue( $clean[ FeatureFlags::EXTRACTION ] );
		$this->assertFalse( $clean[ FeatureFlags::FRONTEND_RENDER ] );
	}

	public function test_invalid_combination_is_normalized_safely(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$clean = $this->page->sanitize_settings(
			array(
				FeatureFlags::FRONTEND_RENDER => '1',
			)
		);

		$this->assertFalse( $clean[ FeatureFlags::FRONTEND_RENDER ] );
		$this->assertFalse( $clean[ FeatureFlags::EXTRACTION ] );
		$this->assertFalse( $clean[ FeatureFlags::INJECTION ] );
	}

	public function test_invalid_combination_queues_admin_notice_payload(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->page->sanitize_settings(
			array(
				FeatureFlags::INJECTION => '1',
			)
		);

		$payload = get_transient( SettingsPage::FLAG_NOTICE_TRANSIENT . '_' . $admin_id );
		$this->assertIsArray( $payload );
		$this->assertSame( SettingsPage::FLAG_NOTICE_ID, $payload['id'] );
		$this->assertContains( FeatureFlags::INJECTION, $payload['dropped'] );
	}

	public function test_flag_audit_action_fires_once_per_changed_flag(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		update_option( Settings::OPTION, Settings::defaults() );

		$events = array();
		add_action(
			'aiml_settings_flag_changed',
			static function ( array $payload ) use ( &$events ): void {
				$events[] = $payload;
			}
		);

		$this->page->sanitize_settings(
			array(
				FeatureFlags::REGISTRATION => '1',
				FeatureFlags::INJECTION    => '1',
			)
		);

		$this->assertCount( 2, $events );
		$this->assertSame( FeatureFlags::REGISTRATION, $events[0]['flag'] );
		$this->assertSame( FeatureFlags::INJECTION, $events[1]['flag'] );
	}

	public function test_flag_audit_action_does_not_fire_when_values_unchanged(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array(
					FeatureFlags::REGISTRATION => true,
				)
			)
		);

		$events = array();
		add_action(
			'aiml_settings_flag_changed',
			static function ( array $payload ) use ( &$events ): void {
				$events[] = $payload;
			}
		);

		$this->page->sanitize_settings(
			array(
				FeatureFlags::REGISTRATION => '1',
			)
		);

		$this->assertSame( array(), $events );
	}

	public function test_flag_audit_payload_contains_required_fields(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		update_option( Settings::OPTION, Settings::defaults() );

		$payload = null;
		add_action(
			'aiml_settings_flag_changed',
			static function ( array $event ) use ( &$payload ): void {
				$payload = $event;
			}
		);

		$this->page->sanitize_settings(
			array(
				FeatureFlags::REGISTRATION => '1',
			)
		);

		$this->assertIsArray( $payload );
		$this->assertSame( FeatureFlags::REGISTRATION, $payload['flag'] );
		$this->assertFalse( $payload['old'] );
		$this->assertTrue( $payload['new'] );
		$this->assertSame( $admin_id, $payload['user_id'] );
		$this->assertIsInt( $payload['timestamp'] );
		$this->assertGreaterThan( 0, $payload['timestamp'] );
		$this->assertSame( 'admin_settings', $payload['source'] );
	}

	public function test_subscriber_cannot_save_strategy_f_settings(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse( current_user_can( 'manage_options' ) );
	}

	public function test_submitted_values_are_sanitized(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$clean = $this->page->sanitize_settings(
			array(
				FeatureFlags::REGISTRATION => 'yes',
				FeatureFlags::INJECTION    => 1,
			)
		);

		$this->assertTrue( $clean[ FeatureFlags::REGISTRATION ] );
		$this->assertTrue( $clean[ FeatureFlags::INJECTION ] );
	}

	public function test_all_production_flags_remain_false_by_default(): void {
		$defaults = Settings::defaults();

		foreach ( FeatureFlags::PRODUCTION_FLAGS as $flag ) {
			$this->assertFalse( $defaults[ $flag ], $flag );
		}
	}

	/**
	 * Renders the settings screen and returns HTML.
	 */
	private function render_settings_html(): string {
		ob_start();
		$this->page->render_settings();

		return (string) ob_get_clean();
	}
}
