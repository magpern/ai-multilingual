<?php
/**
 * Review capability lifecycle tests (R4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Workspace\Review\ReviewCapabilities;

/**
 * Capability matrix and activate/uninstall lifecycle for the reviewer capability.
 */
final class ReviewCapabilitiesTest extends AimlTestCase {

	protected function tearDown(): void {
		ReviewCapabilities::revoke_all_roles();

		parent::tearDown();
	}

	public function test_all_lists_the_review_capability(): void {
		$this->assertSame( array( 'aiml_review_translations' ), ReviewCapabilities::all() );
	}

	public function test_default_roles_is_administrator_only(): void {
		$this->assertSame( array( 'administrator' ), ReviewCapabilities::default_roles() );
	}

	public function test_grant_default_roles_gives_administrator_the_capability(): void {
		ReviewCapabilities::revoke_all_roles();
		ReviewCapabilities::grant_default_roles();

		$role = get_role( 'administrator' );
		$this->assertNotNull( $role );
		$this->assertTrue( $role->has_cap( ReviewCapabilities::REVIEW_TRANSLATIONS ) );
	}

	public function test_grant_default_roles_does_not_grant_editor_or_author(): void {
		ReviewCapabilities::revoke_all_roles();
		ReviewCapabilities::grant_default_roles();

		foreach ( array( 'editor', 'author', 'contributor', 'subscriber' ) as $role_name ) {
			$role = get_role( $role_name );
			$this->assertNotNull( $role );
			$this->assertFalse(
				$role->has_cap( ReviewCapabilities::REVIEW_TRANSLATIONS ),
				"{$role_name} should not receive aiml_review_translations by default."
			);
		}
	}

	public function test_administrator_user_can_review_by_default(): void {
		ReviewCapabilities::grant_default_roles();

		$admin_id = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue( current_user_can( ReviewCapabilities::REVIEW_TRANSLATIONS ) );
	}

	public function test_editor_user_cannot_review_by_default(): void {
		ReviewCapabilities::revoke_all_roles();
		ReviewCapabilities::grant_default_roles();

		$editor_id = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertFalse( current_user_can( ReviewCapabilities::REVIEW_TRANSLATIONS ) );
	}

	public function test_revoke_all_roles_removes_capability_from_every_role(): void {
		ReviewCapabilities::grant_default_roles();
		$this->assertTrue( (bool) get_role( 'administrator' )->has_cap( ReviewCapabilities::REVIEW_TRANSLATIONS ) );

		ReviewCapabilities::revoke_all_roles();

		$roles = wp_roles();
		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = $roles->get_role( (string) $role_name );
			$this->assertNotNull( $role );
			$this->assertFalse( $role->has_cap( ReviewCapabilities::REVIEW_TRANSLATIONS ) );
		}
	}
}
