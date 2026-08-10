<?php
/**
 * Background translation concurrency policy unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\BackgroundTranslationConcurrencyPolicy;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Verifies stable concurrency admission semantics.
 */
final class BackgroundTranslationConcurrencyPolicyTest extends TestCase {

	public function test_error_code_is_stable(): void {
		$this->assertSame(
			'concurrency_limit_exceeded',
			BackgroundTranslationConcurrencyPolicy::ERROR_CODE
		);
	}

	public function test_invalid_admission_source_is_rejected_before_repository_write(): void {
		$result = ( new BackgroundTranslationConcurrencyPolicy() )->admit_and_mark_running( 1, 'paused' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'illegal_transition', $result->get_error_code() );
	}
}
