<?php
/**
 * TranslationMemoryService unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\Memory;

use AIMultilingual\Translation\Memory\TMRepository;
use AIMultilingual\Translation\Memory\TranslationMemoryService;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Pure policy and scoring coverage for F11 WP2.
 */
final class TranslationMemoryServiceTest extends TestCase {

	public function test_write_back_eligible_for_human_and_ai_accepted(): void {
		$this->assertTrue(
			TranslationMemoryService::is_write_back_eligible( 'human', Store::FORMAT_PLAIN )
		);
		$this->assertTrue(
			TranslationMemoryService::is_write_back_eligible( 'ai_accepted', Store::FORMAT_HTML )
		);
		$this->assertTrue(
			TranslationMemoryService::is_write_back_eligible( 'import', Store::FORMAT_PLAIN )
		);
	}

	public function test_machine_persist_is_not_write_back_eligible(): void {
		$this->assertFalse(
			TranslationMemoryService::is_write_back_eligible( 'machine', Store::FORMAT_PLAIN )
		);
		$this->assertFalse(
			TranslationMemoryService::is_write_back_eligible( 'tm_accepted', Store::FORMAT_PLAIN )
		);
	}

	public function test_slug_json_code_formats_never_write_back(): void {
		foreach ( array( Store::FORMAT_SLUG, Store::FORMAT_JSON, Store::FORMAT_CODE ) as $format ) {
			$this->assertFalse(
				TranslationMemoryService::is_write_back_eligible( 'human', $format ),
				"Format {$format} must never enter TM"
			);
		}
	}

	public function test_origin_mapping(): void {
		$this->assertSame( TMRepository::ORIGIN_HUMAN, TranslationMemoryService::origin_for_save( 'human' ) );
		$this->assertSame( TMRepository::ORIGIN_AI, TranslationMemoryService::origin_for_save( 'ai_accepted' ) );
		$this->assertSame( TMRepository::ORIGIN_IMPORT, TranslationMemoryService::origin_for_save( 'import' ) );
		$this->assertNull( TranslationMemoryService::origin_for_save( 'machine' ) );
	}

	public function test_ambiguity_gate(): void {
		$this->assertFalse( TranslationMemoryService::passes_ambiguity_gate( 'Free' ) );
		$this->assertFalse( TranslationMemoryService::passes_ambiguity_gate( 'Short phrase here' ) );
		$this->assertTrue(
			TranslationMemoryService::passes_ambiguity_gate(
				'Free shipping on all orders over fifty'
			)
		);
	}

	public function test_derive_context(): void {
		$this->assertSame( 'block:core/button', TranslationMemoryService::derive_context( 'core/button' ) );
		$this->assertSame( 'field:post_title', TranslationMemoryService::derive_context( '', 'post_title' ) );
		$this->assertSame( '', TranslationMemoryService::derive_context() );
	}

	public function test_fuzzy_confidence_scales_between_sixty_and_ninety_four(): void {
		$low  = TranslationMemoryService::scale_fuzzy_confidence( 85.0, 85.0 );
		$high = TranslationMemoryService::scale_fuzzy_confidence( 99.5, 85.0 );

		$this->assertSame( 60.0, $low );
		$this->assertGreaterThanOrEqual( 60.0, $high );
		$this->assertLessThanOrEqual( 94.0, $high );
	}

	public function test_similarity_identical_strings(): void {
		$this->assertSame(
			100.0,
			TranslationMemoryService::similarity_percent( 'hello world', 'hello world' )
		);
	}
}
