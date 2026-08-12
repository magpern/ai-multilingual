<?php
/**
 * AdmittedPostTypes unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Surface;

use AIMultilingual\Surface\AdmittedPostTypes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Surface\AdmittedPostTypes
 */
final class AdmittedPostTypesTest extends TestCase {

	public function test_workspace_includes_nav_menu_item(): void {
		$this->assertSame(
			array( 'post', 'page', 'product', 'nav_menu_item' ),
			AdmittedPostTypes::for_context( AdmittedPostTypes::CONTEXT_WORKSPACE )
		);
		$this->assertTrue( AdmittedPostTypes::admits( 'nav_menu_item', AdmittedPostTypes::CONTEXT_WORKSPACE ) );
		$this->assertFalse( AdmittedPostTypes::admits( 'nav_menu_item', AdmittedPostTypes::CONTEXT_FRONTEND_OVERLAY ) );
	}

	public function test_frontend_and_rollout_exclude_nav_menu_item(): void {
		$this->assertSame(
			array( 'post', 'page', 'product' ),
			AdmittedPostTypes::for_context( AdmittedPostTypes::CONTEXT_FRONTEND_OVERLAY )
		);
		$this->assertSame(
			array( 'post', 'page', 'product' ),
			AdmittedPostTypes::for_context( AdmittedPostTypes::CONTEXT_ROLLOUT )
		);
	}

	public function test_legacy_admin_edit_is_page_and_post_only(): void {
		$this->assertSame(
			array( 'page', 'post' ),
			AdmittedPostTypes::for_context( AdmittedPostTypes::CONTEXT_LEGACY_ADMIN_EDIT )
		);
		$this->assertTrue( AdmittedPostTypes::admits( 'page', AdmittedPostTypes::CONTEXT_LEGACY_ADMIN_EDIT ) );
		$this->assertFalse( AdmittedPostTypes::admits( 'product', AdmittedPostTypes::CONTEXT_LEGACY_ADMIN_EDIT ) );
	}

	public function test_unknown_context_and_cpt_are_excluded(): void {
		$this->assertSame( array(), AdmittedPostTypes::for_context( 'unknown_context' ) );
		$this->assertFalse( AdmittedPostTypes::admits( 'book', AdmittedPostTypes::CONTEXT_WORKSPACE ) );
		$this->assertFalse( AdmittedPostTypes::admits( 'post', 'unknown_context' ) );
	}
}
