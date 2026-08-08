<?php
/**
 * A.7d customer email extract/overlay unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Cache\Cache;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\WooCommerce\CustomerEmailBridge;
use AIMultilingual\Integration\WooCommerce\OrderTransactionalLanguage;
use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * @covers \AIMultilingual\Integration\WooCommerce\WooCommerceIntegration
 * @covers \AIMultilingual\Integration\WooCommerce\CustomerEmailBridge
 */
final class WooCommerceCustomerEmailsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		foreach ( WooCommerceIntegration::EMAIL_ID_ALLOWLIST as $email_id ) {
			remove_all_filters( 'woocommerce_email_subject_' . $email_id );
			remove_all_filters( 'woocommerce_email_heading_' . $email_id );
		}
	}

	public function test_extract_checkout_emits_sixteen_email_units(): void {
		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$units = $integration->extract_for_post( $this->fake_post( 4506 ) );
		$keys  = array_map( static fn( $u ) => $u->segment_key, $units );
		$this->assertContains( 'p:woocommerce:email:customer_processing_order:subject', $keys );
		$this->assertContains( 'p:woocommerce:email:customer_processing_order:heading', $keys );
		$this->assertContains( 'p:woocommerce:email:customer_cancelled_order:heading', $keys );
		$email_keys = array_values(
			array_filter(
				$keys,
				static fn( string $k ): bool => str_starts_with( $k, 'p:woocommerce:email:' )
			)
		);
		$this->assertCount( 16, $email_keys );
	}

	public function test_overlay_subject_uses_snapshot_language_and_restores(): void {
		$context = new LanguageContext();
		$en      = $this->lang( 1, 'en', true );
		$sv      = $this->lang( 2, 'sv', false );
		$context->set_default( $en );
		$context->set_current( $en );

		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );

		$transactional = new OrderTransactionalLanguage(
			$context,
			new Languages( new Cache() ),
			null,
			array( $en, $sv )
		);

		$bridge = new CustomerEmailBridge(
			$integration,
			$transactional,
			new Store( new Cache() ),
			new PluginIdentity(),
			null,
			static function ( int $source_id, int $language_id, string $key ): ?string {
				if ( 4506 === $source_id && 2 === $language_id && 'p:woocommerce:email:customer_processing_order:subject' === $key ) {
					return 'Din {site_title}-beställning har mottagits!';
				}
				return null;
			}
		);
		$bridge->register();

		$order = $this->fake_order( array( OrderTransactionalLanguage::META_KEY => 'sv' ) );
		$email = new class() {
			/**
			 * @param string $string Template.
			 */
			public function format_string( string $string ): string {
				return str_replace( '{site_title}', 'Biopentra', $string );
			}
		};

		$result = apply_filters(
			'woocommerce_email_subject_customer_processing_order',
			'Your Biopentra order has been received!',
			$order,
			$email
		);

		$this->assertSame( 'Din Biopentra-beställning har mottagits!', $result );
		$this->assertSame( 'en', $context->current()?->code );
	}

	public function test_overlay_falls_back_when_translation_missing(): void {
		$context = new LanguageContext();
		$en      = $this->lang( 1, 'en', true );
		$sv      = $this->lang( 2, 'sv', false );
		$context->set_default( $en );
		$context->set_current( $en );

		$integration = $this->make_integration();
		$integration->configure( true, true, '10.9.4', false, true );
		$transactional = new OrderTransactionalLanguage(
			$context,
			new Languages( new Cache() ),
			null,
			array( $en, $sv )
		);
		$bridge = new CustomerEmailBridge(
			$integration,
			$transactional,
			new Store( new Cache() ),
			new PluginIdentity(),
			null,
			static fn(): ?string => null
		);
		$bridge->register();

		$source = 'Your Biopentra order has been received!';
		$result = apply_filters(
			'woocommerce_email_subject_customer_processing_order',
			$source,
			$this->fake_order( array( OrderTransactionalLanguage::META_KEY => 'sv' ) ),
			null
		);
		$this->assertSame( $source, $result );
	}

	private function make_integration(): WooCommerceIntegration {
		return new WooCommerceIntegration(
			new PluginIdentity(),
			null,
			null,
			null,
			null,
			null,
			3755,
			static fn() => array(),
			static fn() => array(),
			null,
			null,
			4506,
			84,
			static fn() => array(),
			static fn() => array(),
			static fn() => array(),
			null
		);
	}

	private function fake_post( int $id ): WP_Post {
		$post              = new WP_Post( new \stdClass() );
		$post->ID          = $id;
		$post->post_type   = 'page';
		$post->post_status = 'publish';
		return $post;
	}

	/**
	 * @param int    $id         ID.
	 * @param string $code       Code.
	 * @param bool   $is_default Default.
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
	 * @param array<string, string> $meta Meta.
	 */
	private function fake_order( array $meta = array() ): object {
		return new class( $meta ) {
			/** @var array<string, string> */
			public array $meta;

			/** @param array<string, string> $meta Meta. */
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
		};
	}
}
