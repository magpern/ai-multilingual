<?php
/**
 * A.7d frozen email identity matrix.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Integration;

use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Integration\Identity\PluginIdentity
 */
final class WooCommerceCustomerEmailsIdentityTest extends TestCase {

	public function test_frozen_subject_and_heading_keys(): void {
		$identity = new PluginIdentity();
		$expected = array(
			'p:woocommerce:email:customer_processing_order:subject',
			'p:woocommerce:email:customer_processing_order:heading',
			'p:woocommerce:email:customer_completed_order:subject',
			'p:woocommerce:email:customer_completed_order:heading',
			'p:woocommerce:email:customer_on_hold_order:subject',
			'p:woocommerce:email:customer_on_hold_order:heading',
			'p:woocommerce:email:customer_invoice:subject',
			'p:woocommerce:email:customer_invoice:heading',
			'p:woocommerce:email:customer_note:subject',
			'p:woocommerce:email:customer_note:heading',
			'p:woocommerce:email:customer_refunded_order:subject',
			'p:woocommerce:email:customer_refunded_order:heading',
			'p:woocommerce:email:customer_failed_order:subject',
			'p:woocommerce:email:customer_failed_order:heading',
			'p:woocommerce:email:customer_cancelled_order:subject',
			'p:woocommerce:email:customer_cancelled_order:heading',
		);

		$keys = array();
		foreach ( WooCommerceIntegration::EMAIL_ID_ALLOWLIST as $email_id ) {
			$keys[] = $identity->build( 'woocommerce', 'email', $email_id, 'subject' );
			$keys[] = $identity->build( 'woocommerce', 'email', $email_id, 'heading' );
		}

		$this->assertSame( $expected, $keys );
	}

	public function test_no_new_account_or_reset_password_in_allowlist(): void {
		$this->assertNotContains( 'customer_new_account', WooCommerceIntegration::EMAIL_ID_ALLOWLIST );
		$this->assertNotContains( 'customer_reset_password', WooCommerceIntegration::EMAIL_ID_ALLOWLIST );
	}
}
