<?php
/**
 * Rollout configuration validator unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Rollout;

use AIMultilingual\Rollout\RolloutConfiguration;
use AIMultilingual\Rollout\RolloutConfigurationMigrator;
use AIMultilingual\Rollout\RolloutConfigurationValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Rollout\RolloutConfigurationValidator
 * @covers \AIMultilingual\Rollout\RolloutConfigurationMigrator
 */
final class RolloutConfigurationValidatorTest extends TestCase {

	private RolloutConfigurationValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new RolloutConfigurationValidator();
	}

	public function test_valid_configuration(): void {
		$result = $this->validator->validate(
			array(
				'schema_version'         => RolloutConfiguration::SCHEMA_VERSION,
				'policy_version'         => 1,
				'rollout_stage'          => 2,
				'rollout_render_enabled' => true,
				'allowed_post_ids'       => array( 1, 2, 2 ),
				'allowed_post_types'     => array( 'post', 'page' ),
				'allowed_language_codes' => array( 'sv' ),
			),
			array( 'en', 'sv' )
		);

		$this->assertTrue( $result->valid );
		$this->assertSame( array( 1, 2 ), $result->config?->allowed_post_ids );
		$this->assertFalse( $result->config?->general_rollout_enabled );
	}

	public function test_rejects_invalid_post_id(): void {
		$result = $this->validator->validate(
			array(
				'schema_version'   => RolloutConfiguration::SCHEMA_VERSION,
				'rollout_stage'    => 0,
				'allowed_post_ids' => array( 0 ),
			)
		);

		$this->assertFalse( $result->valid );
		$this->assertContains( 'invalid_post_ids', $result->errors );
	}

	public function test_rejects_unknown_language(): void {
		$result = $this->validator->validate(
			array(
				'schema_version'         => RolloutConfiguration::SCHEMA_VERSION,
				'allowed_language_codes' => array( 'xx' ),
			),
			array( 'en' )
		);

		$this->assertFalse( $result->valid );
		$this->assertNotEmpty( $result->errors );
	}

	public function test_rejects_percentage_cohort_field(): void {
		$result = $this->validator->validate(
			array(
				'schema_version'    => RolloutConfiguration::SCHEMA_VERSION,
				'cohort_percentage' => 10,
			)
		);

		$this->assertFalse( $result->valid );
		$this->assertStringContainsString( 'unsupported_cohort_field', $result->errors[0] );
	}

	public function test_migrator_upgrades_legacy_schema(): void {
		$migrator = new RolloutConfigurationMigrator();
		$migrated = $migrator->migrate(
			array(
				'rollout_stage' => 1,
			)
		);

		$this->assertIsArray( $migrated );
		$this->assertSame( RolloutConfiguration::SCHEMA_VERSION, $migrated['schema_version'] );
		$this->assertFalse( ! empty( $migrated['general_rollout_enabled'] ) );

		$result = $this->validator->validate( $migrated );
		$this->assertTrue( $result->valid );
	}

	public function test_migrator_upgrades_v1_to_v2_defaults_ga_off(): void {
		$migrator = new RolloutConfigurationMigrator();
		$migrated = $migrator->migrate(
			array(
				'schema_version'         => 1,
				'policy_version'         => 4,
				'rollout_stage'          => 2,
				'rollout_render_enabled' => true,
				'allowed_post_ids'       => array( 6321 ),
				'allowed_post_types'     => array( 'post', 'page' ),
				'allowed_language_codes' => array( 'sv' ),
				'render_cache_enabled'   => false,
			)
		);

		$this->assertIsArray( $migrated );
		$this->assertSame( 2, $migrated['schema_version'] );
		$this->assertFalse( ! empty( $migrated['general_rollout_enabled'] ) );
		$this->assertSame( array( 6321 ), $migrated['allowed_post_ids'] );
		$this->assertSame( 4, $migrated['policy_version'] );

		$result = $this->validator->validate( $migrated, array( 'sv' ) );
		$this->assertTrue( $result->valid );
		$this->assertFalse( $result->config?->general_rollout_enabled );
	}

	public function test_accepts_stage_6(): void {
		$result = $this->validator->validate(
			array(
				'schema_version'          => RolloutConfiguration::SCHEMA_VERSION,
				'rollout_stage'           => 6,
				'general_rollout_enabled' => true,
				'allowed_post_types'      => array( 'page' ),
				'allowed_language_codes'  => array( 'sv' ),
			),
			array( 'sv' )
		);

		$this->assertTrue( $result->valid );
		$this->assertSame( 6, $result->config?->rollout_stage );
		$this->assertTrue( $result->config?->general_rollout_enabled );
	}

	public function test_rejects_stage_above_max(): void {
		$result = $this->validator->validate(
			array(
				'schema_version' => RolloutConfiguration::SCHEMA_VERSION,
				'rollout_stage'  => 7,
			)
		);

		$this->assertFalse( $result->valid );
		$this->assertContains( 'invalid_rollout_stage', $result->errors );
	}
}
