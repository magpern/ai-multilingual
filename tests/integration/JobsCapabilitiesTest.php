<?php
/**
 * Background translation job capability lifecycle tests (J5).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Jobs\JobsCapabilities;

/**
 * Capability matrix and activate/uninstall lifecycle for job capabilities.
 */
final class JobsCapabilitiesTest extends AimlTestCase {

	protected function tearDown(): void {
		JobsCapabilities::revoke_all_roles();

		parent::tearDown();
	}

	public function test_all_lists_job_capabilities(): void {
		$this->assertSame(
			array(
				'aiml_view_translation_jobs',
				'aiml_manage_translation_jobs',
				'aiml_run_translation_jobs',
				'aiml_cancel_translation_jobs',
			),
			JobsCapabilities::all()
		);
	}

	public function test_grant_default_roles_gives_administrator_all_caps(): void {
		JobsCapabilities::revoke_all_roles();
		JobsCapabilities::grant_default_roles();

		$role = get_role( 'administrator' );
		$this->assertNotNull( $role );
		foreach ( JobsCapabilities::all() as $cap ) {
			$this->assertTrue( $role->has_cap( $cap ), $cap );
		}
	}

	public function test_grant_default_roles_gives_editor_view_manage_cancel_only(): void {
		JobsCapabilities::revoke_all_roles();
		JobsCapabilities::grant_default_roles();

		$role = get_role( 'editor' );
		$this->assertNotNull( $role );
		$this->assertTrue( $role->has_cap( JobsCapabilities::VIEW_JOBS ) );
		$this->assertTrue( $role->has_cap( JobsCapabilities::MANAGE_JOBS ) );
		$this->assertTrue( $role->has_cap( JobsCapabilities::CANCEL_JOBS ) );
		$this->assertFalse( $role->has_cap( JobsCapabilities::RUN_JOBS ) );
	}

	public function test_subscriber_has_no_job_capabilities_by_default(): void {
		JobsCapabilities::grant_default_roles();

		$role = get_role( 'subscriber' );
		$this->assertNotNull( $role );
		foreach ( JobsCapabilities::all() as $cap ) {
			$this->assertFalse( $role->has_cap( $cap ), $cap );
		}
	}

	public function test_revoke_all_roles_removes_capabilities_from_every_role(): void {
		JobsCapabilities::grant_default_roles();
		JobsCapabilities::revoke_all_roles();

		$roles = wp_roles();
		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = $roles->get_role( (string) $role_name );
			$this->assertNotNull( $role );
			foreach ( JobsCapabilities::all() as $cap ) {
				$this->assertFalse( $role->has_cap( $cap ), $role_name . ':' . $cap );
			}
		}
	}
}
