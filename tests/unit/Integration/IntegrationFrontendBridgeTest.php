<?php
/**
 * Integration frontend bridge language-context contract.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\IntegrationFrontendBridge;
use AIMultilingual\Language\LanguageContext;
use PHPUnit\Framework\TestCase;

/**
 * Guards the A.1 bridge against calling a non-existent LanguageContext API.
 */
final class IntegrationFrontendBridgeTest extends TestCase {

	public function test_language_context_exposes_current_not_language(): void {
		$context = new LanguageContext();

		$this->assertTrue( method_exists( $context, 'current' ) );
		$this->assertFalse( method_exists( $context, 'language' ) );
		$this->assertNull( $context->current() );
		$this->assertTrue( $context->is_default() );
	}

	public function test_frontend_bridge_source_calls_current(): void {
		$path = dirname( __DIR__, 3 ) . '/src/Integration/IntegrationFrontendBridge.php';
		$src  = file_get_contents( $path );

		$this->assertIsString( $src );
		$this->assertStringContainsString( '$this->context->current()', $src );
		$this->assertStringNotContainsString( '$this->context->language()', $src );
		$this->assertTrue( class_exists( IntegrationFrontendBridge::class ) );
	}
}
