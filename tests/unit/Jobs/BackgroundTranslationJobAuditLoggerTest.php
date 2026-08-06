<?php
/**
 * Background translation job audit logger unit tests (plan §22).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Jobs;

use AIMultilingual\Jobs\BackgroundTranslationJobAuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * Audit payloads must never retain translation content, prompts, or secrets.
 */
final class BackgroundTranslationJobAuditLoggerTest extends TestCase {

	/**
	 * Disallowed content keys are stripped; safe keys survive.
	 */
	public function test_sanitize_strips_sensitive_fields(): void {
		$logger = new BackgroundTranslationJobAuditLogger();
		$clean  = $logger->sanitize_payload(
			array(
				'job_id'          => 12,
				'job_type'        => 'translate_selected',
				'language_id'     => 2,
				'provider_id'     => 'openai',
				'prompt_profile'  => 'default',
				'attempts'        => 3,
				'result_class'    => 'retryable',
				'user_id'         => 7,
				'source_surface'  => 'worker',
				'translated_text' => 'secret translation body',
				'source_text'     => 'secret source body',
				'prompt'          => 'full prompt text',
				'api_key'         => 'sk-secret',
				'stack_trace'     => 'Exception in ...',
				'error_message'   => 'full provider error body',
			)
		);

		$this->assertSame( 12, $clean['job_id'] );
		$this->assertSame( 'translate_selected', $clean['job_type'] );
		$this->assertSame( 2, $clean['language_id'] );
		$this->assertSame( 'openai', $clean['provider_id'] );
		$this->assertSame( 'default', $clean['prompt_profile'] );
		$this->assertSame( 3, $clean['attempts'] );
		$this->assertSame( 'retryable', $clean['result_class'] );
		$this->assertSame( 7, $clean['user_id'] );
		$this->assertSame( 'worker', $clean['source_surface'] );

		$this->assertArrayNotHasKey( 'translated_text', $clean );
		$this->assertArrayNotHasKey( 'source_text', $clean );
		$this->assertArrayNotHasKey( 'prompt', $clean );
		$this->assertArrayNotHasKey( 'api_key', $clean );
		$this->assertArrayNotHasKey( 'stack_trace', $clean );
		$this->assertArrayNotHasKey( 'error_message', $clean );
		$this->assertArrayHasKey( 'timestamp', $clean );
	}

	/**
	 * Logging without WordPress loaded is a safe no-op.
	 */
	public function test_log_without_wordpress_is_a_safe_noop(): void {
		$logger = new BackgroundTranslationJobAuditLogger();

		$logger->log( 'translation_job_created', array( 'job_id' => 1 ) );

		$this->addToAssertionCount( 1 );
	}
}
