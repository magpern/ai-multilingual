<?php
/**
 * ItemResult and scheduler unit tests (J3).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\ItemResult;
use AIMultilingual\Jobs\ItemStatuses;
use AIMultilingual\Translation\AI\ProviderResult;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * J3 DTO and scheduler unit coverage.
 */
final class ItemResultTest extends TestCase {

	public function test_completed_factory_sets_status_and_glossary(): void {
		$result = ItemResult::completed( 7, 1, 12 );

		$this->assertSame( ItemStatuses::COMPLETED, $result->status );
		$this->assertSame( ItemStatuses::COMPLETED, $result->result_code );
		$this->assertSame( 7, $result->glossary_version_actual );
		$this->assertSame( 1, $result->usage_requests );
		$this->assertSame( 12, $result->usage_tokens );
	}

	public function test_stale_source_carries_skip_reason(): void {
		$result = ItemResult::stale_source( 'Source drifted.' );

		$this->assertSame( ItemStatuses::STALE_SOURCE, $result->status );
		$this->assertSame( 'Source drifted.', $result->skip_reason );
		$this->assertSame( 'stale_source', $result->error_code );
	}

	public function test_skipped_conflict_is_permanent(): void {
		$result = ItemResult::skipped_conflict( 'Human edited.' );

		$this->assertSame( ItemStatuses::SKIPPED_CONFLICT, $result->status );
		$this->assertSame( 'permanent', $result->error_class );
	}

	public function test_from_error_bounds_long_messages(): void {
		$result = ItemResult::from_error(
			ItemStatuses::FAILED,
			'provider_error',
			ProviderResult::ERROR_PERMANENT,
			str_repeat( 'x', 600 )
		);

		$this->assertLessThanOrEqual( 500, strlen( $result->error_message ) );
		$this->assertStringEndsWith( '...', $result->error_message );
	}

	public function test_retryable_flag(): void {
		$retry = ItemResult::from_error(
			ItemStatuses::RETRY_WAIT,
			'aiml_rate_limited',
			ProviderResult::ERROR_RETRYABLE,
			'slow down'
		);

		$this->assertTrue( $retry->is_retryable() );
	}
}
