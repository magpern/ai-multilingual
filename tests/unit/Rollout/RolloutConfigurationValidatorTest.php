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
				'schema_version'         => 1,
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
	}

	public function test_rejects_invalid_post_id(): void {
		$result = $this->validator->validate(
			array(
				'schema_version'   => 1,
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
				'schema_version'         => 1,
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
				'schema_version'    => 1,
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

		$result = $this->validator->validate( $migrated );
		$this->assertTrue( $result->valid );
	}
}
