<?php
/**
 * RequestLocalInvalidationCoordinator unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Surface;

use AIMultilingual\Block\BlockIdentityMigration;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Surface\RequestLocalInvalidationCoordinator
 */
final class RequestLocalInvalidationCoordinatorTest extends TestCase {

	private RequestLocalInvalidationCoordinator $coordinator;

	protected function setUp(): void {
		parent::setUp();
		BlockIdentityMigration::reset_for_tests();
		$this->coordinator = new RequestLocalInvalidationCoordinator(
			new Store( new Cache() ),
			new Extractor()
		);
	}

	protected function tearDown(): void {
		BlockIdentityMigration::reset_for_tests();
		parent::tearDown();
	}

	public function test_mark_dirty_coalesces_duplicate_identities(): void {
		$this->coordinator->mark_dirty( Store::SOURCE_POST, 42 );
		$this->coordinator->mark_dirty( Store::SOURCE_POST, 42 );
		$this->coordinator->mark_dirty( Store::SOURCE_POST, 42 );

		$this->assertSame( 1, $this->coordinator->dirty_count() );
	}

	public function test_flush_runs_once_and_leaves_later_marks_unflushed(): void {
		// Non-post identities are accepted into the dirty set but skipped by sync_identity
		// (no Store call). That lets us prove flush-once without a WordPress DB.
		$this->coordinator->mark_dirty( 'term', 7 );
		$this->coordinator->mark_dirty( 'term', 7 );
		$this->assertSame( 1, $this->coordinator->dirty_count() );

		$this->coordinator->flush();
		$this->assertSame( 0, $this->coordinator->dirty_count() );
		$this->assertSame( 0, $this->coordinator->sync_count() );

		$this->coordinator->mark_dirty( 'term', 8 );
		$this->assertSame( 1, $this->coordinator->dirty_count() );

		$this->coordinator->flush();
		$this->assertSame( 1, $this->coordinator->dirty_count(), 'Second flush must be a no-op once flushed.' );
	}

	public function test_clear_dirty_drops_pending_mark(): void {
		$this->coordinator->mark_dirty( Store::SOURCE_POST, 9 );
		$this->coordinator->clear_dirty( Store::SOURCE_POST, 9 );
		$this->assertSame( 0, $this->coordinator->dirty_count() );

		$this->coordinator->flush();
		$this->assertSame( 0, $this->coordinator->sync_count() );
	}

	public function test_invalid_marks_are_ignored(): void {
		$this->coordinator->mark_dirty( '', 1 );
		$this->coordinator->mark_dirty( Store::SOURCE_POST, 0 );
		$this->coordinator->mark_dirty( Store::SOURCE_POST, -3 );

		$this->assertSame( 0, $this->coordinator->dirty_count() );
	}

	public function test_reset_for_tests_allows_another_flush_cycle(): void {
		$this->coordinator->mark_dirty( 'term', 1 );
		$this->coordinator->flush();

		$this->coordinator->reset_for_tests();
		$this->coordinator->mark_dirty( 'term', 2 );
		$this->coordinator->flush();

		$this->assertSame( 0, $this->coordinator->dirty_count() );
	}

	public function test_distinct_identities_remain_distinct_until_flush(): void {
		$this->coordinator->mark_dirty( Store::SOURCE_POST, 1 );
		$this->coordinator->mark_dirty( Store::SOURCE_POST, 2 );
		$this->coordinator->mark_dirty( 'term', 1 );

		$this->assertSame( 3, $this->coordinator->dirty_count() );
	}
}
