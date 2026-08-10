<?php
/**
 * A.7d customer email subject/heading overlay bridge (ADR-0018 language switch).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration\WooCommerce;

use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationDiagnostics;
use AIMultilingual\Integration\IntegrationSecurity;
use AIMultilingual\Translation\Store;

/**
 * Registers Woo email subject/heading filters outside the frontend `wp` bridge.
 *
 * Resolves Store rows under the checkout page technical host using the order
 * transactional language snapshot — never the active admin/request locale.
 */
final class CustomerEmailBridge {

	public const FIELD_SUBJECT = 'subject';

	public const FIELD_HEADING = 'heading';

	/**
	 * Builds the customer email overlay bridge.
	 *
	 * @param WooCommerceIntegration                     $integration        Woo integration.
	 * @param OrderTransactionalLanguage                 $transactional      Language snapshot service.
	 * @param Store                                      $store              Translation store.
	 * @param PluginIdentity                             $identity           Identity builder.
	 * @param IntegrationDiagnostics|null                $diagnostics        Counters.
	 * @param (callable(int,int,string): (?string))|null $translation_lookup Test Store override.
	 */
	public function __construct(
		private WooCommerceIntegration $integration,
		private OrderTransactionalLanguage $transactional,
		private Store $store,
		private PluginIdentity $identity,
		private ?IntegrationDiagnostics $diagnostics = null,
		private $translation_lookup = null,
	) {
	}

	/**
	 * Register subject/heading filters for the Supported email ID allowlist.
	 */
	public function register(): void {
		if ( ! $this->integration->get_compatibility()->allows_overlay() ) {
			return;
		}

		foreach ( WooCommerceIntegration::EMAIL_ID_ALLOWLIST as $email_id ) {
			add_filter(
				'woocommerce_email_subject_' . $email_id,
				function ( $subject, $order_object = null, $email = null ) use ( $email_id ) {
					return $this->overlay_email_chrome( $subject, $order_object, $email, $email_id, self::FIELD_SUBJECT );
				},
				10,
				3
			);
			add_filter(
				'woocommerce_email_heading_' . $email_id,
				function ( $heading, $order_object = null, $email = null ) use ( $email_id ) {
					return $this->overlay_email_chrome( $heading, $order_object, $email, $email_id, self::FIELD_HEADING );
				},
				10,
				3
			);
		}
	}

	/**
	 * Overlay one subject or heading using ADR-0018 language resolution.
	 *
	 * @param mixed  $formatted    Already format_string'd source from Woo.
	 * @param mixed  $order_object Order object.
	 * @param mixed  $email        WC_Email instance.
	 * @param string $email_id     Email id token.
	 * @param string $field        subject|heading.
	 * @return mixed
	 */
	private function overlay_email_chrome( $formatted, $order_object, $email, string $email_id, string $field ) {
		if ( ! is_string( $formatted ) ) {
			return $formatted;
		}

		$order = is_object( $order_object ) ? $order_object : null;

		return $this->transactional->with_order_language(
			$order,
			function ( ?object $language ) use ( $formatted, $email, $email_id, $field ) {
				if ( null === $language || ! empty( $language->is_default ) ) {
					$this->bump( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
					return $formatted;
				}

				$source_id = $this->integration->resolved_checkout_page_id();
				if ( $source_id <= 0 ) {
					$this->bump( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
					return $formatted;
				}

				try {
					$segment_key = $this->identity->build(
						WooCommerceIntegration::ID,
						WooCommerceIntegration::OWNER_EMAIL,
						$email_id,
						$field
					);
				} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					$this->bump( IntegrationDiagnostics::COUNTER_IDENTITY_ERROR );
					return $formatted;
				}

				$text = $this->lookup_translation( $source_id, (int) $language->language_id, $segment_key );
				if ( null === $text || '' === $text ) {
					$this->bump( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
					return $formatted;
				}

				$text = IntegrationSecurity::sanitize_plain( $text );
				if ( '' === $text ) {
					$this->bump( IntegrationDiagnostics::COUNTER_SOURCE_FALLBACK );
					return $formatted;
				}

				$out = $this->apply_format_string( $text, $email );
				$this->bump( IntegrationDiagnostics::COUNTER_OVERLAY_APPLIED );
				return $out;
			}
		);
	}

	/**
	 * Look up an approved translation for an email chrome segment.
	 *
	 * @param int    $source_id   Checkout page ID.
	 * @param int    $language_id Language ID.
	 * @param string $segment_key Segment key.
	 */
	private function lookup_translation( int $source_id, int $language_id, string $segment_key ): ?string {
		if ( null !== $this->translation_lookup ) {
			$hit = ( $this->translation_lookup )( $source_id, $language_id, $segment_key );
			return is_string( $hit ) ? $hit : null;
		}

		$row = $this->store->get( 'post', $source_id, $language_id, $segment_key );
		if ( null === $row || ! Store::is_publicly_overlay_eligible( $row ) ) {
			return null;
		}
		$text = (string) ( $row->translated_text ?? '' );

		return '' === $text ? null : $text;
	}

	/**
	 * Apply Woo format_string so placeholders remain runtime.
	 *
	 * @param string $template Translated template (may include {placeholders}).
	 * @param mixed  $email    WC_Email.
	 */
	private function apply_format_string( string $template, $email ): string {
		if ( is_object( $email ) && is_callable( array( $email, 'format_string' ) ) ) {
			$formatted = $email->format_string( $template );
			return is_string( $formatted ) ? $formatted : $template;
		}
		return $template;
	}

	/**
	 * Increment a bounded diagnostics counter.
	 *
	 * @param string $key Counter.
	 */
	private function bump( string $key ): void {
		if ( null !== $this->diagnostics ) {
			$this->diagnostics->increment( $key );
		}
	}
}
