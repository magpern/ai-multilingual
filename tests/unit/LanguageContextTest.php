<?php
/**
 * Request language state and the switch stack.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit;

use AIMultilingual\Language\LanguageContext;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The switch stack exists for rendering that happens outside the request's own
 * language — order emails, background jobs. Those run in code paths where an
 * exception must not strand the context in the wrong language, so restoration
 * has to survive a throw.
 */
final class LanguageContextTest extends TestCase {

	/**
	 * Builds a language row.
	 *
	 * @param string $code       URL code.
	 * @param bool   $is_default Whether this is the source language.
	 */
	private function language( string $code, bool $is_default = false ): object {
		return (object) array(
			'language_id' => abs( crc32( $code ) ) % 1000,
			'code'        => $code,
			'locale'      => $code . '_XX',
			'is_default'  => $is_default,
			'status'      => 'published',
			'direction'   => 'ltr',
		);
	}

	public function test_unresolved_context_counts_as_default(): void {
		$context = new LanguageContext();

		$this->assertNull( $context->current() );
		$this->assertTrue( $context->is_default() );
		$this->assertFalse( $context->is_translated() );
		$this->assertSame( 0, $context->current_id() );
	}

	public function test_default_language_is_not_translated(): void {
		$context = new LanguageContext();
		$english = $this->language( 'en', true );

		$context->set_default( $english );
		$context->set_current( $english );

		$this->assertTrue( $context->is_default() );
		$this->assertFalse( $context->is_translated() );
	}

	public function test_target_language_is_translated(): void {
		$context = new LanguageContext();
		$swedish = $this->language( 'sv' );

		$context->set_current( $swedish );

		$this->assertFalse( $context->is_default() );
		$this->assertTrue( $context->is_translated() );
		$this->assertSame( (int) $swedish->language_id, $context->current_id() );
	}

	public function test_switch_and_restore_round_trip(): void {
		$context = new LanguageContext();
		$english = $this->language( 'en', true );
		$swedish = $this->language( 'sv' );

		$context->set_current( $english );
		$context->switch_to( $swedish );

		$this->assertSame( 'sv', $context->current()->code );

		$context->restore();

		$this->assertSame( 'en', $context->current()->code );
	}

	public function test_switches_nest(): void {
		$context = new LanguageContext();

		$context->set_current( $this->language( 'en', true ) );
		$context->switch_to( $this->language( 'sv' ) );
		$context->switch_to( $this->language( 'de' ) );

		$this->assertSame( 'de', $context->current()->code );

		$context->restore();
		$this->assertSame( 'sv', $context->current()->code );

		$context->restore();
		$this->assertSame( 'en', $context->current()->code );
	}

	public function test_restore_without_a_switch_is_harmless(): void {
		$context = new LanguageContext();
		$context->set_current( $this->language( 'en', true ) );

		$context->restore();

		$this->assertSame( 'en', $context->current()->code );
	}

	public function test_with_restores_the_previous_language(): void {
		$context = new LanguageContext();
		$context->set_current( $this->language( 'en', true ) );

		$seen = $context->with(
			$this->language( 'sv' ),
			static function () use ( $context ): string {
				return (string) $context->current()->code;
			}
		);

		$this->assertSame( 'sv', $seen );
		$this->assertSame( 'en', $context->current()->code );
	}

	public function test_with_restores_even_when_the_callback_throws(): void {
		$context = new LanguageContext();
		$context->set_current( $this->language( 'en', true ) );

		try {
			$context->with(
				$this->language( 'sv' ),
				static function (): void {
					throw new RuntimeException( 'email template blew up' );
				}
			);

			$this->fail( 'The exception should have propagated.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'email template blew up', $e->getMessage() );
		}

		$this->assertSame(
			'en',
			$context->current()->code,
			'A throwing callback must not leave the context in the switched language.'
		);
	}
}
