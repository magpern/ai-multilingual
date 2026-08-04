<?php
/**
 * Rollout configuration repository integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Rollout;

use AIMultilingual\Rollout\RolloutConfiguration;
use AIMultilingual\Rollout\RolloutConfigurationRepository;
use AIMultilingual\Rollout\RolloutSnapshotStore;
use AIMultilingual\Tests\Integration\AimlTestCase;

/**
 * @covers \AIMultilingual\Rollout\RolloutConfigurationRepository
 */
final class RolloutConfigurationRepositoryTest extends AimlTestCase {

	private RolloutConfigurationRepository $repository;

	protected function setUp(): void {
		parent::setUp();

		delete_option( RolloutConfigurationRepository::OPTION );
		delete_option( RolloutSnapshotStore::OPTION );

		$this->repository = new RolloutConfigurationRepository();
	}

	public function test_apply_change_increments_policy_version_and_stores_snapshot(): void {
		$before = $this->repository->get();
		$this->assertSame( 1, $before->policy_version );

		$result = $this->repository->apply_change(
			array(
				'rollout_stage'          => 1,
				'rollout_render_enabled' => true,
				'allowed_post_ids'       => array( 100 ),
			),
			1
		);

		$this->assertTrue( $result->valid );
		$this->assertSame( 2, $result->config?->policy_version );

		$after = $this->repository->get();
		$this->assertSame( 2, $after->policy_version );
		$this->assertSame( 1, $after->rollout_stage );

		$versions = $this->repository->list_snapshot_versions();
		$this->assertContains( 1, $versions );
	}

	public function test_restore_increments_policy_version(): void {
		$this->repository->apply_change(
			array(
				'rollout_stage'          => 2,
				'rollout_render_enabled' => true,
				'allowed_post_ids'       => array( 200 ),
			),
			1
		);

		$this->repository->apply_change(
			array(
				'rollout_stage'    => 3,
				'allowed_post_ids' => array( 300 ),
			),
			1
		);

		$restore = $this->repository->restore( 2, 1 );
		$this->assertTrue( $restore->valid );
		$this->assertSame( 4, $restore->config?->policy_version );
		$this->assertSame( 2, $restore->config?->rollout_stage );
		$this->assertSame( array( 200 ), $restore->config?->allowed_post_ids );
	}

	public function test_malformed_option_fails_closed_to_defaults(): void {
		update_option( RolloutConfigurationRepository::OPTION, 'not-an-array' );

		$config = $this->repository->get();
		$this->assertSame( RolloutConfiguration::defaults()->rollout_stage, $config->rollout_stage );
		$this->assertFalse( $config->rollout_render_enabled );
	}

	public function test_rejected_change_preserves_active_policy(): void {
		$this->repository->apply_change(
			array(
				'rollout_stage'          => 2,
				'rollout_render_enabled' => true,
				'allowed_post_ids'       => array( 10 ),
			),
			1
		);

		$result = $this->repository->apply_change(
			array(
				'allowed_post_ids' => array( -1 ),
			),
			1
		);

		$this->assertFalse( $result->valid );

		$active = $this->repository->get();
		$this->assertSame( array( 10 ), $active->allowed_post_ids );
		$this->assertSame( 2, $active->policy_version );
	}
}
