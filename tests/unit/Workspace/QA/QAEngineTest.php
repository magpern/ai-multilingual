<?php
/**
 * QAEngine unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Workspace\QA;

use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAEngine;
use AIMultilingual\Workspace\QA\QAIssue;
use PHPUnit\Framework\TestCase;

/**
 * F11 WP7 — modular source-independent QA.
 */
final class QAEngineTest extends TestCase {

	public function test_placeholder_mismatch_is_error(): void {
		$engine = new QAEngine();
		$result = $engine->evaluate( 'Hello {name}', 'Hej', Store::FORMAT_PLAIN );

		$this->assertTrue( $result->has_errors() );
		$this->assertSame( 'qd5_placeholder_loss', $result->issues[0]->code );
		$this->assertTrue( $engine->should_block_save( $result ) );
	}

	public function test_same_checks_regardless_of_origin_context(): void {
		$engine = new QAEngine();
		$manual = $engine->evaluate( 'Buy %s now', 'Köp nu', Store::FORMAT_PLAIN );
		$ai     = $engine->evaluate( 'Buy %s now', 'Köp nu', Store::FORMAT_PLAIN );

		$this->assertSame( $manual->to_array(), $ai->to_array() );
	}

	public function test_new_check_registers_without_changing_defaults(): void {
		$engine = new QAEngine( array() );
		$engine->register(
			new class() implements QACheck {
				public function get_id(): string {
					return 'custom';
				}

				public function default_severity(): string {
					return QAIssue::SEVERITY_INFO;
				}

				public function check( string $source_text, string $target_text, string $text_format ): array {
					unset( $source_text, $text_format );
					return array(
						new QAIssue( 'custom', QAIssue::SEVERITY_INFO, 'Custom', array( 'target' => $target_text ) ),
					);
				}
			}
		);

		$result = $engine->evaluate( 'a', 'b', Store::FORMAT_PLAIN );
		$this->assertCount( 1, $result->issues );
		$this->assertSame( 'custom', $result->issues[0]->code );
		$this->assertFalse( $engine->should_block_save( $result ) );
	}

	public function test_block_on_error_policy_can_be_disabled(): void {
		$engine = new QAEngine( null, false );
		$result = $engine->evaluate( 'Hello {name}', '', Store::FORMAT_PLAIN );

		$this->assertTrue( $result->has_errors() );
		$this->assertFalse( $engine->should_block_save( $result ) );
	}

	public function test_meta_qa_shape(): void {
		$engine  = new QAEngine();
		$payload = $engine->evaluate( 'Hi', 'Hej', Store::FORMAT_PLAIN )->to_array();

		$this->assertArrayHasKey( 'issues', $payload );
		$this->assertArrayHasKey( 'summary', $payload );
		$this->assertArrayHasKey( 'errors', $payload['summary'] );
		$this->assertArrayNotHasKey( 'origin', $payload );
	}
}
