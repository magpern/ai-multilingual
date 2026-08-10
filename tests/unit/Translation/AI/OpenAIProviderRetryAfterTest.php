<?php
/**
 * OpenAI Retry-After parsing tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\AI;

use AIMultilingual\Translation\AI\PromptProfileRegistry;
use AIMultilingual\Translation\AI\ProviderSegment;
use AIMultilingual\Translation\AI\Providers\OpenAIProvider;
use AIMultilingual\Translation\AI\TranslationBatch;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Verifies provider backoff headers reach domain errors.
 */
final class OpenAIProviderRetryAfterTest extends TestCase {

	public function test_integer_retry_after_is_attached_to_rate_limit_error(): void {
		$provider = new OpenAIProvider(
			'sk-test',
			'gpt-4o-mini',
			null,
			static fn(): array => array(
				'response' => array( 'code' => 429 ),
				'headers'  => array( 'Retry-After' => '120' ),
				'body'     => '{"error":{"message":"slow down"}}',
			)
		);

		$result = $provider->translate_batch( $this->batch() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aiml_rate_limited', $result->get_error_code() );
		$this->assertSame( 120, $result->get_error_data()['retry_after'] );
		$this->assertTrue( $result->get_error_data()['provider_request_made'] );
	}

	public function test_retry_after_milliseconds_rounds_up(): void {
		$provider = new OpenAIProvider(
			'sk-test',
			'gpt-4o-mini',
			null,
			static fn(): array => array(
				'response' => array( 'code' => 429 ),
				'headers'  => array( 'retry-after-ms' => '1500' ),
				'body'     => '{"error":{"message":"slow down"}}',
			)
		);

		$result = $provider->translate_batch( $this->batch() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 2, $result->get_error_data()['retry_after'] );
	}

	private function batch(): TranslationBatch {
		return new TranslationBatch(
			'en_US',
			'sv_SE',
			PromptProfileRegistry::TRANSLATE,
			PromptProfileRegistry::VERSION,
			'',
			array( new ProviderSegment( 'title', 'Hello', 'plain' ) )
		);
	}
}
