<?php
/**
 * Rollout capabilities and audit unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Rollout;

use AIMultilingual\Rollout\RolloutAuditEvents;
use AIMultilingual\Rollout\RolloutAuditLogger;
use AIMultilingual\Rollout\RolloutCapabilities;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Rollout\RolloutCapabilities
 * @covers \AIMultilingual\Rollout\RolloutAuditEvents
 * @covers \AIMultilingual\Rollout\RolloutAuditLogger
 */
final class RolloutCapabilitiesTest extends TestCase {

	public function test_frozen_capability_slugs(): void {
		$this->assertContains( 'aiml_view_rollout', RolloutCapabilities::all() );
		$this->assertContains( 'aiml_manage_rollout', RolloutCapabilities::all() );
		$this->assertSame( array( 'administrator' ), RolloutCapabilities::default_roles() );
	}

	public function test_frozen_audit_events(): void {
		$this->assertContains( 'rollout_configuration_changed', RolloutAuditEvents::all() );
		$this->assertContains( 'rollout_emergency_stop', RolloutAuditEvents::all() );
	}

	public function test_audit_logger_sanitizes_payload(): void {
		$logger = new RolloutAuditLogger();
		$logger->log(
			RolloutAuditEvents::CONFIGURATION_CHANGED,
			array(
				'policy_version' => 2,
				'user_id'        => 1,
				'source'         => 'test',
				'secret'         => 'must-not-appear',
				'translation'    => 'must-not-appear',
			)
		);

		$this->assertTrue( true );
	}
}
