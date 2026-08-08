<?php
/**
 * ADR-0018 WooCommerce order transactional language snapshot.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration\WooCommerce;

use AIMultilingual\Integration\IntegrationDiagnostics;
use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;

/**
 * Captures and resolves the customer transactional language for order-backed emails.
 *
 * Meta is AIML operational state on the Woo order (CRUD / HPOS). It is not
 * translated content and must never contain PII.
 */
final class OrderTransactionalLanguage {

	public const META_KEY = '_aiml_transactional_language';

	public const COUNTER_SNAPSHOT_CAPTURED              = 'snapshot_captured';
	public const COUNTER_SNAPSHOT_PRESENT               = 'snapshot_present';
	public const COUNTER_SNAPSHOT_MISSING               = 'snapshot_missing';
	public const COUNTER_SNAPSHOT_INVALID               = 'snapshot_invalid';
	public const COUNTER_TRANSACTIONAL_LANGUAGE_RESOLVED = 'transactional_language_resolved';
	public const COUNTER_SOURCE_LANGUAGE_FALLBACK       = 'source_language_fallback';
	public const COUNTER_CONTEXT_RESTORED               = 'context_restored';

	/**
	 * @param LanguageContext             $context             Request language state.
	 * @param Languages                   $languages           Language rows.
	 * @param IntegrationDiagnostics|null $diagnostics         Optional counters.
	 * @param list<object>|null           $languages_override  Test language rows.
	 */
	public function __construct(
		private LanguageContext $context,
		private Languages $languages,
		private ?IntegrationDiagnostics $diagnostics = null,
		private ?array $languages_override = null,
	) {
	}

	/**
	 * Register capture hooks (visitor checkout / Store API checkout).
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processed' ), 1, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_store_api_order' ), 1, 1 );
	}

	/**
	 * Classic checkout: capture after order is created, before deferred email needs.
	 *
	 * @param mixed $order_id Order ID.
	 * @param mixed $posted   Posted data (unused).
	 * @param mixed $order    Order object when provided.
	 */
	public function on_order_processed( $order_id, $posted = null, $order = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->capture_for_order( $this->normalize_order( $order_id, $order ) );
	}

	/**
	 * Block / Store API checkout.
	 *
	 * @param mixed $order Order object.
	 */
	public function on_store_api_order( $order ): void {
		$this->capture_for_order( $this->normalize_order( 0, $order ) );
	}

	/**
	 * Capture once when LanguageContext has a deterministic current language.
	 *
	 * @param object|null $order WC_Order-like object.
	 */
	public function capture_for_order( ?object $order ): void {
		if ( null === $order || ! is_callable( array( $order, 'get_meta' ) ) || ! is_callable( array( $order, 'update_meta_data' ) ) ) {
			return;
		}

		$existing = $this->read_meta( $order );
		if ( null !== $existing && $this->is_supported_code( $existing ) ) {
			$this->bump( self::COUNTER_SNAPSHOT_PRESENT );
			return;
		}

		$current = $this->context->current();
		if ( null === $current || ! isset( $current->code ) ) {
			return;
		}

		$code = strtolower( trim( (string) $current->code ) );
		if ( ! $this->is_supported_code( $code ) ) {
			$this->bump( self::COUNTER_SNAPSHOT_INVALID );
			return;
		}

		$order->update_meta_data( self::META_KEY, $code );
		if ( is_callable( array( $order, 'save_meta_data' ) ) ) {
			$order->save_meta_data();
		} elseif ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		$this->bump( self::COUNTER_SNAPSHOT_CAPTURED );
	}

	/**
	 * Resolve the language row for an order-backed transactional send.
	 *
	 * Ladder: valid snapshot → source/default. Never uses admin/request locale.
	 *
	 * @param object|null $order WC_Order-like object.
	 */
	public function resolve_language_for_order( ?object $order ): ?object {
		$code = null === $order ? null : $this->read_meta( $order );

		if ( null !== $code && $this->is_supported_code( $code ) ) {
			$language = $this->find_language_by_code( $code );
			if ( null !== $language ) {
				$this->bump( self::COUNTER_SNAPSHOT_PRESENT );
				$this->bump( self::COUNTER_TRANSACTIONAL_LANGUAGE_RESOLVED );
				return $language;
			}
			$this->bump( self::COUNTER_SNAPSHOT_INVALID );
		} elseif ( null === $code || '' === $code ) {
			$this->bump( self::COUNTER_SNAPSHOT_MISSING );
		} else {
			$this->bump( self::COUNTER_SNAPSHOT_INVALID );
		}

		$this->bump( self::COUNTER_SOURCE_LANGUAGE_FALLBACK );
		return $this->default_language();
	}

	/**
	 * Run work in the resolved transactional language and always restore.
	 *
	 * Callback receives the resolved language row (or null).
	 *
	 * @param object|null                $order    Order.
	 * @param callable(?object): mixed   $callback Work.
	 * @return mixed
	 */
	public function with_order_language( ?object $order, callable $callback ) {
		$language = $this->resolve_language_for_order( $order );
		try {
			return $this->context->with(
				$language,
				static function () use ( $callback, $language ) {
					return $callback( $language );
				}
			);
		} finally {
			$this->bump( self::COUNTER_CONTEXT_RESTORED );
		}
	}

	/**
	 * @param object $order Order.
	 */
	private function read_meta( object $order ): ?string {
		if ( ! is_callable( array( $order, 'get_meta' ) ) ) {
			return null;
		}
		$value = $order->get_meta( self::META_KEY, true );
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = strtolower( trim( $value ) );
		return '' !== $value ? $value : null;
	}

	/**
	 * @param string $code Candidate code.
	 */
	private function is_supported_code( string $code ): bool {
		if ( ! Languages::is_valid_code( $code ) ) {
			return false;
		}
		return null !== $this->find_language_by_code( $code );
	}

	/**
	 * @param string $code Language code.
	 */
	private function find_language_by_code( string $code ): ?object {
		foreach ( $this->all_languages() as $language ) {
			if ( ! isset( $language->code ) ) {
				continue;
			}
			if ( strtolower( (string) $language->code ) !== $code ) {
				continue;
			}
			$status = isset( $language->status ) ? (string) $language->status : '';
			if ( Languages::STATUS_DISABLED === $status ) {
				return null;
			}
			return $language;
		}
		return null;
	}

	/**
	 * @return list<object>
	 */
	private function all_languages(): array {
		if ( null !== $this->languages_override ) {
			return $this->languages_override;
		}
		return $this->languages->all();
	}

	/**
	 * Default / source language row.
	 */
	private function default_language(): ?object {
		if ( null !== $this->languages_override ) {
			foreach ( $this->languages_override as $language ) {
				if ( ! empty( $language->is_default ) ) {
					return $language;
				}
			}
			return null;
		}
		return $this->languages->default();
	}

	/**
	 * @param mixed $order_id Order ID.
	 * @param mixed $order    Order object.
	 */
	private function normalize_order( $order_id, $order ): ?object {
		if ( is_object( $order ) && is_callable( array( $order, 'get_meta' ) ) ) {
			return $order;
		}
		$id = (int) $order_id;
		if ( $id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}
		$resolved = wc_get_order( $id );
		return is_object( $resolved ) ? $resolved : null;
	}

	/**
	 * @param string $key Counter key.
	 */
	private function bump( string $key ): void {
		if ( null !== $this->diagnostics ) {
			$this->diagnostics->increment( $key );
		}
	}
}
