<?php
/**
 * ADR-0018 order transactional language unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Integration\IntegrationDiagnostics;
use AIMultilingual\Integration\WooCommerce\OrderTransactionalLanguage;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Integration\WooCommerce\OrderTransactionalLanguage
 */
final class WooCommerceOrderTransactionalLanguageTest extends TestCase {

	public function test_capture_writes_once_and_is_idempotent(): void {
		$context = new LanguageContext();
		$sv      = $this->lang( 2, 'sv', false );
		$en      = $this->lang( 1, 'en', true );
		$context->set_default( $en );
		$context->set_current( $sv );

		$diagnostics = new IntegrationDiagnostics();
		$service     = new OrderTransactionalLanguage(
			$context,
			new Languages( new Cache() ),
			$diagnostics,
			array( $en, $sv )
		);

		$order = $this->fake_order();
		$service->capture_for_order( $order );
		$this->assertSame( 'sv', $order->meta[ OrderTransactionalLanguage::META_KEY ] );
		$this->assertSame( 1, $diagnostics->snapshot()[ OrderTransactionalLanguage::COUNTER_SNAPSHOT_CAPTURED ] ?? 0 );

		$context->set_current( $en );
		$service->capture_for_order( $order );
		$this->assertSame( 'sv', $order->meta[ OrderTransactionalLanguage::META_KEY ] );
		$this->assertSame( 1, $diagnostics->snapshot()[ OrderTransactionalLanguage::COUNTER_SNAPSHOT_PRESENT ] ?? 0 );
	}

	public function test_resolve_uses_snapshot_not_current_request_language(): void {
		$context = new LanguageContext();
		$sv      = $this->lang( 2, 'sv', false );
		$en      = $this->lang( 1, 'en', true );
		$context->set_default( $en );
		$context->set_current( $en );

		$service = new OrderTransactionalLanguage(
			$context,
			new Languages( new Cache() ),
			null,
			array( $en, $sv )
		);

		$order    = $this->fake_order( array( OrderTransactionalLanguage::META_KEY => 'sv' ) );
		$resolved = $service->resolve_language_for_order( $order );
		$this->assertNotNull( $resolved );
		$this->assertSame( 'sv', $resolved->code );

		$seen = array();
		$service->with_order_language(
			$order,
			function ( ?object $language ) use ( $context, &$seen ) {
				$seen[] = $context->current()?->code;
				$this->assertSame( 'sv', $language?->code );
				return 'ok';
			}
		);
		$this->assertSame( array( 'sv' ), $seen );
		$this->assertSame( 'en', $context->current()?->code );
	}

	public function test_missing_snapshot_falls_back_to_default(): void {
		$context = new LanguageContext();
		$en      = $this->lang( 1, 'en', true );
		$context->set_default( $en );
		$context->set_current( $en );

		$diagnostics = new IntegrationDiagnostics();
		$service     = new OrderTransactionalLanguage(
			$context,
			new Languages( new Cache() ),
			$diagnostics,
			array( $en )
		);

		$resolved = $service->resolve_language_for_order( $this->fake_order() );
		$this->assertSame( 'en', $resolved?->code );
		$this->assertSame( 1, $diagnostics->snapshot()[ OrderTransactionalLanguage::COUNTER_SNAPSHOT_MISSING ] ?? 0 );
		$this->assertSame( 1, $diagnostics->snapshot()[ OrderTransactionalLanguage::COUNTER_SOURCE_LANGUAGE_FALLBACK ] ?? 0 );
	}

	public function test_exception_still_restores_language(): void {
		$context = new LanguageContext();
		$sv      = $this->lang( 2, 'sv', false );
		$en      = $this->lang( 1, 'en', true );
		$context->set_default( $en );
		$context->set_current( $en );

		$service = new OrderTransactionalLanguage(
			$context,
			new Languages( new Cache() ),
			null,
			array( $en, $sv )
		);
		$order   = $this->fake_order( array( OrderTransactionalLanguage::META_KEY => 'sv' ) );

		try {
			$service->with_order_language(
				$order,
				static function (): string {
					throw new \RuntimeException( 'boom' );
				}
			);
			$this->fail( 'expected exception' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'boom', $e->getMessage() );
		}
		$this->assertSame( 'en', $context->current()?->code );
	}

	/**
	 * @param int    $id         Language ID.
	 * @param string $code       Code.
	 * @param bool   $is_default Default flag.
	 */
	private function lang( int $id, string $code, bool $is_default ): object {
		return (object) array(
			'language_id' => $id,
			'code'        => $code,
			'is_default'  => $is_default ? 1 : 0,
			'status'      => Languages::STATUS_PUBLISHED,
		);
	}

	/**
	 * @param array<string, string> $meta Initial meta.
	 */
	private function fake_order( array $meta = array() ): object {
		return new class( $meta ) {
			/** @var array<string, string> */
			public array $meta;

			/**
			 * @param array<string, string> $meta Meta map.
			 */
			public function __construct( array $meta ) {
				$this->meta = $meta;
			}

			/**
			 * @param string $key Meta key.
			 * @param bool   $single Single.
			 * @return mixed
			 */
			public function get_meta( string $key, bool $single = true ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return $this->meta[ $key ] ?? '';
			}

			/**
			 * @param string $key Meta key.
			 * @param mixed  $value Value.
			 */
			public function update_meta_data( string $key, $value ): void {
				$this->meta[ $key ] = (string) $value;
			}

			public function save_meta_data(): void {
			}
		};
	}
}
