<?php
/**
 * BackgroundTranslationRetryPolicy unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\BackgroundTranslationRetryPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Retry taxonomy and backoff unit coverage (J4).
 */
final class BackgroundTranslationRetryPolicyTest extends TestCase {

	private BackgroundTranslationRetryPolicy $policy;

	protected function setUp(): void {
		parent::setUp();
		$this->policy = new BackgroundTranslationRetryPolicy();
	}

	public function test_classify_retryable_codes(): void {
		foreach ( array( 'rate_limit', 'aiml_rate_limited', 'network', 'http_request_failed', 'lock_contention' ) as $code ) {
			$this->assertSame(
				BackgroundTranslationRetryPolicy::DISPOSITION_RETRYABLE,
				$this->policy->classify( $code )
			);
		}
	}

	public function test_classify_provider_5xx_via_http_status(): void {
		$this->assertSame(
			BackgroundTranslationRetryPolicy::DISPOSITION_RETRYABLE,
			$this->policy->classify( 'provider_error', 503 )
		);
	}

	public function test_classify_terminal_codes(): void {
		foreach (
			array(
				'invalid_language',
				'aiml_invalid_language',
				'unsupported_provider',
				'validation',
				'source_conflict',
				'stale_source',
				'translation_conflict',
				'malformed_item',
				'aiml_invalid_segment',
				'cancelled',
				'empty_target',
				'placeholder_mismatch',
				'html_structure_mismatch',
				'number_mismatch',
				'forbidden_markup',
				'url_mismatch',
				'aiml_ai_invalid_response',
			) as $code
		) {
			$this->assertSame(
				BackgroundTranslationRetryPolicy::DISPOSITION_TERMINAL,
				$this->policy->classify( $code )
			);
		}
	}

	public function test_should_retry_respects_max_attempts(): void {
		$this->assertTrue( $this->policy->should_retry( 1 ) );
		$this->assertTrue( $this->policy->should_retry( 4 ) );
		$this->assertFalse( $this->policy->should_retry( 5 ) );
		$this->assertFalse( $this->policy->should_retry( 6 ) );
	}

	public function test_delay_seconds_exponential_with_cap(): void {
		$first = $this->policy->delay_seconds( 1 );
		$this->assertGreaterThanOrEqual( BackgroundTranslationRetryPolicy::BASE_DELAY_SECONDS, $first );
		$this->assertLessThanOrEqual( BackgroundTranslationRetryPolicy::MAX_DELAY_SECONDS, $first );

		$large = $this->policy->delay_seconds( 10 );
		$this->assertSame( BackgroundTranslationRetryPolicy::MAX_DELAY_SECONDS, $large );
	}

	public function test_delay_seconds_honors_retry_after_capped(): void {
		$delay = $this->policy->delay_seconds( 1, 1200 );
		$this->assertSame( BackgroundTranslationRetryPolicy::MAX_DELAY_SECONDS, $delay );
	}
}
