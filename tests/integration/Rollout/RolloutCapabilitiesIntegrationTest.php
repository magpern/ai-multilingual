<?php
/**
 * Rollout capabilities integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Rollout;

use AIMultilingual\Rollout\RolloutAccess;
use AIMultilingual\Rollout\RolloutAuditEvents;
use AIMultilingual\Rollout\RolloutAuditLogger;
use AIMultilingual\Rollout\RolloutCapabilities;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Rollout\RolloutConfigurationService;
use AIMultilingual\Tests\Integration\AimlTestCase;

/**
 * @covers \AIMultilingual\Rollout\RolloutCapabilities
 * @covers \AIMultilingual\Rollout\RolloutConfigurationService
 */
final class RolloutCapabilitiesIntegrationTest extends AimlTestCase {

	public function test_administrator_has_rollout_capabilities(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );

		foreach ( RolloutCapabilities::all() as $cap ) {
			$this->assertTrue( RolloutAccess::user_can( (int) $admin, $cap ), $cap );
		}
	}

	public function test_unauthorized_config_change_rejected(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$service = new RolloutConfigurationService(
			new RolloutConfigurationRepository(),
			new RolloutAuditLogger()
		);

		$result = $service->apply(
			array( 'rollout_stage' => 1 ),
			(int) $subscriber
		);

		$this->assertFalse( $result->valid );
		$this->assertContains( 'unauthorized', $result->errors );
	}

	public function test_authorized_config_change_emits_audit_hook(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( (int) $admin );

		$seen = array();
		add_action(
			'aiml_rollout_audit',
			static function ( $event, $payload ) use ( &$seen ): void {
				$seen[] = array( $event, $payload );
			},
			10,
			2
		);

		$service = new RolloutConfigurationService(
			new RolloutConfigurationRepository(),
			new RolloutAuditLogger()
		);

		$result = $service->apply(
			array(
				'rollout_render_enabled' => true,
				'rollout_stage'          => 1,
				'allowed_post_ids'       => array( 1 ),
			),
			(int) $admin,
			'integration_test'
		);

		$this->assertTrue( $result->valid );
		$this->assertNotEmpty( $seen );
		$this->assertSame( RolloutAuditEvents::CONFIGURATION_CHANGED, $seen[0][0] );
	}
}
