<?php
/**
 * ResponseValidator and SegmentConstraintAnalyzer unit tests (F11 / TI.1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\AI;

use AIMultilingual\Translation\AI\ResponseValidator;
use AIMultilingual\Translation\AI\SegmentConstraintAnalyzer;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Structural provider-pipeline validation (not QA).
 */
final class ResponseValidatorTest extends TestCase {

	public function test_accepts_structurally_valid_response(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'Hello {name}, you have 3 items',
			'Hej {name}, du har 3 artiklar',
			Store::FORMAT_PLAIN
		);

		$this->assertTrue( $result->valid );
	}

	public function test_rejects_missing_placeholder(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'Hello {name}',
			'Hej världen',
			Store::FORMAT_PLAIN
		);

		$this->assertFalse( $result->valid );
		$this->assertSame( ResponseValidator::CODE_PLACEHOLDER_MISMATCH, $result->code );
		$this->assertStringContainsString( '{name}', $result->message );
	}

	public function test_rejects_empty_target_for_non_empty_source(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate( 'Hello', '', Store::FORMAT_PLAIN );

		$this->assertFalse( $result->valid );
		$this->assertSame( ResponseValidator::CODE_EMPTY_TARGET, $result->code );
	}

	public function test_rejects_missing_html_tags(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'<p>Hello <strong>world</strong></p>',
			'<p>Hej värld</p>',
			Store::FORMAT_HTML
		);

		$this->assertFalse( $result->valid );
		$this->assertSame( ResponseValidator::CODE_HTML_MISMATCH, $result->code );
	}

	public function test_rejects_invented_forbidden_markup(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'<p>Safe</p>',
			'<p>Safe</p><script>alert(1)</script>',
			Store::FORMAT_HTML
		);

		$this->assertFalse( $result->valid );
		$this->assertSame( ResponseValidator::CODE_FORBIDDEN_MARKUP, $result->code );
		$this->assertContains( 'script', $result->data['tags'] ?? array() );
	}

	public function test_rejects_absolute_url_loss(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'See https://example.com/docs for help',
			'Se mer hjälp här',
			Store::FORMAT_PLAIN
		);

		$this->assertFalse( $result->valid );
		$this->assertSame( ResponseValidator::CODE_URL_MISMATCH, $result->code );
		$this->assertSame( 'https://example.com/docs', $result->data['url'] ?? null );
	}

	public function test_accepts_preserved_absolute_url(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'See https://example.com/docs for help',
			'Se https://example.com/docs för hjälp',
			Store::FORMAT_PLAIN
		);

		$this->assertTrue( $result->valid );
	}

	public function test_persist_constraints_omit_numbers_after_ts7_narrow(): void {
		$validator   = new ResponseValidator();
		$constraints = $validator->persist_constraints( 'Dose is 1.5 ml', Store::FORMAT_PLAIN );

		$this->assertContains( 'non_empty', $constraints );
		$this->assertNotContains( 'numbers', $constraints );
		$this->assertTrue( ResponseValidator::PERSIST_OMIT_NUMBER_CONSTRAINTS );
	}

	public function test_suggest_default_constraints_still_include_numbers(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'Dose is 1.5 ml',
			'Dosen är 1,5 ml',
			Store::FORMAT_PLAIN
		);

		$this->assertFalse( $result->valid );
		$this->assertSame( ResponseValidator::CODE_NUMBER_MISMATCH, $result->code );
	}

	public function test_persist_constraints_allow_swedish_decimal_localization(): void {
		$validator = new ResponseValidator();
		$result    = $validator->validate(
			'Dose is 1.5 ml',
			'Dosen är 1,5 ml',
			Store::FORMAT_PLAIN,
			$validator->persist_constraints( 'Dose is 1.5 ml', Store::FORMAT_PLAIN )
		);

		$this->assertTrue( $result->valid );
	}

	public function test_analyzer_extracts_placeholders_tags_and_urls(): void {
		$analyzer = new SegmentConstraintAnalyzer();
		$analysis = $analyzer->analyze(
			'<p>Buy %s for {count} SEK at https://shop.example/item</p>',
			Store::FORMAT_HTML
		);

		$this->assertContains( '%s', $analysis['placeholders'] );
		$this->assertContains( '{count}', $analysis['placeholders'] );
		$this->assertContains( 'p', $analysis['html_tags'] );
		$this->assertContains( 'https://shop.example/item', $analysis['urls'] );
		$this->assertContains( 'placeholders', $analysis['constraints'] );
		$this->assertContains( 'html', $analysis['constraints'] );
		$this->assertContains( 'urls', $analysis['constraints'] );
	}

	public function test_validator_documents_separation_from_qa(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 4 ) . '/src/Translation/AI/ResponseValidator.php'
		);

		$this->assertStringContainsString( 'not QAEngine', $source );
		$this->assertStringContainsString( 'Provider-pipeline', $source );
	}
}
