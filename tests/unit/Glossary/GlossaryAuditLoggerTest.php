<?php
/**
 * Glossary audit logger unit tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Glossary;

use AIMultilingual\Glossary\GlossaryAuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * Audit payloads must never retain full term strings.
 */
final class GlossaryAuditLoggerTest extends TestCase {

	/**
	 * Disallowed content keys are stripped.
	 */
	public function test_sanitize_strips_term_content(): void {
		$logger = new GlossaryAuditLogger();
		$clean  = $logger->sanitize_payload(
			array(
				'glossary_id'    => 9,
				'source_term'    => 'secret-source',
				'target_term'    => 'secret-target',
				'source_lang_id' => 1,
				'user_id'        => 3,
			)
		);

		$this->assertSame( 9, $clean['glossary_id'] );
		$this->assertSame( 1, $clean['source_lang_id'] );
		$this->assertSame( 3, $clean['user_id'] );
		$this->assertArrayNotHasKey( 'source_term', $clean );
		$this->assertArrayNotHasKey( 'target_term', $clean );
		$this->assertArrayHasKey( 'timestamp', $clean );
	}
}
