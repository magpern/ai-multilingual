<?php
/**
 * WooCommerce Product, Catalog, Archive Chrome, Customer Journey, and Customer Emails Integration API v1 consumer (A.7a–A.7d).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration\WooCommerce;

use AIMultilingual\Integration\CompatibilityStatus;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationSecurity;
use AIMultilingual\Integration\PluginIntegrationInterface;
use AIMultilingual\Integration\TranslationUnitDescriptor;
use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * Record-owned WooCommerce bridge for A.7a–A.7d Supported surfaces.
 *
 * Product: attribute names (P5) and variation attribute names (P7).
 * Catalog: product_cat / product_tag name + description (C3–C6) on shop page host.
 * Archive chrome: catalog orderby / orderedby labels (B1–B2) on shop page technical anchor.
 * Customer journey: checkout field labels + place order (CJ3); account menu + endpoint titles (CJ4);
 * thank-you text + order totals labels (CJ6). CJ1/CJ2/CJ5 remain Deferred.
 * Customer emails (A.7d): subject + heading for CE1–CE6/CE9–CE10 on checkout technical host.
 * CE7/CE8, body gettext, global footer remain Deferred. Title/excerpt/content remain on Extractor/Renderer.
 */
final class WooCommerceIntegration implements PluginIntegrationInterface {

	public const ID = 'woocommerce';

	public const MIN_VERSION = '10.0.0';

	public const PLUGIN_BASENAME = 'woocommerce/woocommerce.php';

	public const HOOK_ATTRIBUTE_LABEL = 'woocommerce_attribute_label';

	public const HOOK_SINGLE_TERM_TITLE = 'single_term_title';

	public const HOOK_TERM_DESCRIPTION = 'term_description';

	public const HOOK_PAGE_TITLE = 'woocommerce_page_title';

	public const HOOK_CATALOG_ORDERBY = 'woocommerce_catalog_orderby';

	public const HOOK_CATALOG_ORDEREDBY = 'woocommerce_catalog_orderedby';

	public const HOOK_CHECKOUT_FIELDS = 'woocommerce_checkout_fields';

	public const HOOK_ORDER_BUTTON_TEXT = 'woocommerce_order_button_text';

	public const HOOK_ACCOUNT_MENU_ITEMS = 'woocommerce_account_menu_items';

	public const HOOK_THANKYOU_RECEIVED = 'woocommerce_thankyou_order_received_text';

	public const HOOK_ORDER_ITEM_TOTALS = 'woocommerce_get_order_item_totals';

	public const TAXONOMY_CAT = 'product_cat';

	public const TAXONOMY_TAG = 'product_tag';

	public const OWNER_CATALOG_ORDERBY = 'catalog_orderby';

	public const OWNER_CATALOG_ORDEREDBY = 'catalog_orderedby';

	public const OWNER_CHECKOUT_FIELD = 'checkout_field';

	public const OWNER_CHECKOUT = 'checkout';

	public const OWNER_ACCOUNT_MENU = 'account_menu';

	public const OWNER_ENDPOINT = 'endpoint';

	public const OWNER_ORDER_TOTALS = 'order_totals';

	public const OWNER_EMAIL = 'email';

	public const FIELD_ATTRIBUTE_NAME = 'attribute_name';

	public const FIELD_VARIATION_ATTRIBUTE_NAME = 'variation_attribute_name';

	public const FIELD_NAME = 'name';

	public const FIELD_DESCRIPTION = 'description';

	public const FIELD_LABEL = 'label';

	public const FIELD_TITLE = 'title';

	public const FIELD_SUBJECT = 'subject';

	public const FIELD_HEADING = 'heading';

	public const CHECKOUT_ORDER_BUTTON_ID = 'order_button';

	public const CHECKOUT_THANKYOU_ID = 'thankyou_received';

	/**
	 * Frozen A.7d Supported customer email IDs (CE1–CE6, CE9–CE10).
	 *
	 * @var list<string>
	 */
	public const EMAIL_ID_ALLOWLIST = array(
		'customer_processing_order',
		'customer_completed_order',
		'customer_on_hold_order',
		'customer_invoice',
		'customer_note',
		'customer_refunded_order',
		'customer_failed_order',
		'customer_cancelled_order',
	);

	/**
	 * Default EN subject chrome templates (placeholders preserved).
	 *
	 * @var array<string, string>
	 */
	public const EMAIL_DEFAULT_SUBJECTS = array(
		'customer_processing_order' => 'Your {site_title} order has been received!',
		'customer_completed_order'  => 'Your order from {site_title} is on its way!',
		'customer_on_hold_order'    => 'Your {site_title} order has been received!',
		'customer_invoice'          => 'Details for order #{order_number} on {site_title}',
		'customer_note'             => 'A note has been added to your order from {site_title}',
		'customer_refunded_order'   => 'Your {site_title} order #{order_number} has been refunded',
		'customer_failed_order'     => 'Your order at {site_title} was unsuccessful',
		'customer_cancelled_order'  => '[{site_title}]: Your order #{order_number} has been cancelled',
	);

	/**
	 * Default EN heading chrome templates (placeholders preserved).
	 *
	 * @var array<string, string>
	 */
	public const EMAIL_DEFAULT_HEADINGS = array(
		'customer_processing_order' => 'Thank you for your order',
		'customer_completed_order'  => 'Good things are heading your way!',
		'customer_on_hold_order'    => 'Thank you for your order',
		'customer_invoice'          => 'Details for order #{order_number}',
		'customer_note'             => 'A note has been added to your order',
		'customer_refunded_order'   => 'Order refunded: {order_number}',
		'customer_failed_order'     => 'Sorry, your order was unsuccessful',
		'customer_cancelled_order'  => 'Order cancelled: #{order_number}',
	);

	/**
	 * Frozen B1/B2 functional option keys (never translated).
	 *
	 * @var list<string>
	 */
	public const ORDERBY_ALLOWLIST = array(
		'menu_order',
		'popularity',
		'rating',
		'date',
		'price',
		'price-desc',
		'relevance',
	);

	/**
	 * Frozen CJ6.2 order totals row keys (labels only).
	 *
	 * @var list<string>
	 */
	public const ORDER_TOTALS_ALLOWLIST = array(
		'cart_subtotal',
		'shipping',
		'discount',
		'order_total',
		'payment_method',
	);

	/**
	 * Builds the WooCommerce integration.
	 *
	 * @param PluginIdentity                                                                             $identity                 Serializer.
	 * @param bool|null                                                                                  $installed                Test override.
	 * @param bool|null                                                                                  $active                   Test override.
	 * @param string|null                                                                                $version                  Test override.
	 * @param bool|null                                                                                  $disabled                 Test override.
	 * @param bool|null                                                                                  $hooks_present            Test override.
	 * @param int|null                                                                                   $shop_page_id             Test override shop page ID.
	 * @param (callable(int): list<array{slug:string,label:string,variation:bool}>)|null                 $attributes_provider      Test product attributes.
	 * @param (callable(): list<array{taxonomy:string,term_id:int,name:string,description:string}>)|null $terms_provider Test catalog terms.
	 * @param (callable(): array<string, string>)|null                                                   $orderby_labels_provider  Test B1 labels.
	 * @param (callable(): array<string, string>)|null                                                   $orderedby_labels_provider Test B2 labels.
	 * @param int|null                                                                                   $checkout_page_id         Test checkout page ID.
	 * @param int|null                                                                                   $myaccount_page_id        Test myaccount page ID.
	 * @param (callable(): array<string, string>)|null                                                   $checkout_field_labels_provider Test CJ3.1 field labels.
	 * @param (callable(): array<string, string>)|null                                                   $account_menu_provider    Test CJ4.1 menu.
	 * @param (callable(): array<string, string>)|null                                                   $order_totals_labels_provider Test CJ6.2 labels.
	 * @param (callable(): array<string, array{subject:string,heading:string}>)|null                     $email_chrome_provider Test A.7d email chrome.
	 */
	public function __construct(
		private PluginIdentity $identity,
		private ?bool $installed = null,
		private ?bool $active = null,
		private ?string $version = null,
		private ?bool $disabled = null,
		private ?bool $hooks_present = null,
		private ?int $shop_page_id = null,
		private $attributes_provider = null,
		private $terms_provider = null,
		private $orderby_labels_provider = null,
		private $orderedby_labels_provider = null,
		private ?int $checkout_page_id = null,
		private ?int $myaccount_page_id = null,
		private $checkout_field_labels_provider = null,
		private $account_menu_provider = null,
		private $order_totals_labels_provider = null,
		private $email_chrome_provider = null,
	) {
	}

	/**
	 * Convenience factory for production wiring.
	 *
	 * @param PluginIdentity $identity Serializer.
	 */
	public static function create_default( PluginIdentity $identity ): self {
		return new self( $identity );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_api_version(): string {
		return Contract::API_VERSION;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_compatibility(): CompatibilityStatus {
		if ( ! $this->is_installed() ) {
			return new CompatibilityStatus( Contract::STATE_UNAVAILABLE, 'plugin_missing' );
		}
		if ( $this->is_disabled() ) {
			return new CompatibilityStatus( Contract::STATE_DISABLED, 'integration_disabled' );
		}
		if ( ! $this->is_active() ) {
			return new CompatibilityStatus( Contract::STATE_UNAVAILABLE, 'plugin_inactive' );
		}
		if ( version_compare( $this->resolved_version(), self::MIN_VERSION, '<' ) ) {
			return new CompatibilityStatus( Contract::STATE_UNSUPPORTED_VERSION, 'version_too_low' );
		}
		if ( ! $this->required_hooks_present() ) {
			return new CompatibilityStatus( Contract::STATE_MISSING_REQUIRED_HOOK, 'hooks_missing' );
		}
		return new CompatibilityStatus( Contract::STATE_COMPATIBLE, 'ok' );
	}

	/**
	 * Extract allowlisted A.7a Woo units for product or shop page contexts.
	 *
	 * @param WP_Post $post Canonical post.
	 * @return list<TranslationUnitDescriptor>
	 * @throws \RuntimeException When duplicate segment keys are produced.
	 */
	public function extract_for_post( WP_Post $post ): array {
		if ( ! $this->get_compatibility()->allows_operation() ) {
			return array();
		}

		$units = array();
		$seen  = array();

		if ( 'product' === $post->post_type ) {
			foreach ( $this->extract_product_attribute_units( (int) $post->ID ) as $unit ) {
				$this->append_unique( $units, $seen, $unit );
			}
		}

		if ( $this->is_shop_page( $post ) ) {
			foreach ( $this->extract_catalog_term_units() as $unit ) {
				$this->append_unique( $units, $seen, $unit );
			}
			foreach ( $this->extract_catalog_order_units() as $unit ) {
				$this->append_unique( $units, $seen, $unit );
			}
		}

		if ( $this->is_checkout_page( $post ) ) {
			foreach ( $this->extract_checkout_journey_units() as $unit ) {
				$this->append_unique( $units, $seen, $unit );
			}
			foreach ( $this->extract_customer_email_units() as $unit ) {
				$this->append_unique( $units, $seen, $unit );
			}
		}

		if ( $this->is_myaccount_page( $post ) ) {
			foreach ( $this->extract_account_journey_units() as $unit ) {
				$this->append_unique( $units, $seen, $unit );
			}
		}

		return $units;
	}

	/**
	 * Register official Woo/WP overlay filters for Supported A.7a surfaces.
	 *
	 * @param callable(string): (?string) $resolve Segment key resolver.
	 */
	public function register_output_hooks( callable $resolve ): void {
		if ( ! $this->get_compatibility()->allows_overlay() ) {
			return;
		}

		add_filter(
			self::HOOK_ATTRIBUTE_LABEL,
			function ( $label, $name, $product = null ) use ( $resolve ) {
				return $this->overlay_attribute_label( $label, $name, $product, $resolve );
			},
			10,
			3
		);

		add_filter(
			self::HOOK_SINGLE_TERM_TITLE,
			function ( $title ) use ( $resolve ) {
				return $this->overlay_current_term_name( $title, $resolve );
			},
			10,
			1
		);

		add_filter(
			self::HOOK_PAGE_TITLE,
			function ( $title ) use ( $resolve ) {
				return $this->overlay_current_term_name( $title, $resolve );
			},
			10,
			1
		);

		add_filter(
			self::HOOK_TERM_DESCRIPTION,
			function ( $description, $term_id ) use ( $resolve ) {
				return $this->overlay_term_description( $description, $term_id, $resolve );
			},
			10,
			2
		);

		add_filter(
			self::HOOK_CATALOG_ORDERBY,
			function ( $options ) use ( $resolve ) {
				return $this->overlay_catalog_order_map( $options, self::OWNER_CATALOG_ORDERBY, $resolve );
			},
			10,
			1
		);

		add_filter(
			self::HOOK_CATALOG_ORDEREDBY,
			function ( $options ) use ( $resolve ) {
				return $this->overlay_catalog_order_map( $options, self::OWNER_CATALOG_ORDEREDBY, $resolve );
			},
			10,
			1
		);

		add_filter(
			self::HOOK_CHECKOUT_FIELDS,
			function ( $fields ) use ( $resolve ) {
				return $this->overlay_checkout_fields( $fields, $resolve );
			},
			10,
			1
		);

		add_filter(
			self::HOOK_ORDER_BUTTON_TEXT,
			function ( $text ) use ( $resolve ) {
				return $this->overlay_plain_singleton(
					$text,
					self::OWNER_CHECKOUT,
					self::CHECKOUT_ORDER_BUTTON_ID,
					self::FIELD_LABEL,
					$resolve
				);
			},
			10,
			1
		);

		add_filter(
			self::HOOK_ACCOUNT_MENU_ITEMS,
			function ( $items ) use ( $resolve ) {
				return $this->overlay_string_map( $items, self::OWNER_ACCOUNT_MENU, self::FIELD_LABEL, $resolve );
			},
			10,
			1
		);

		foreach ( array_keys( $this->read_account_menu_labels() ) as $endpoint ) {
			$endpoint = $this->normalize_token( (string) $endpoint );
			if ( '' === $endpoint ) {
				continue;
			}
			$hook = 'woocommerce_endpoint_' . $endpoint . '_title';
			add_filter(
				$hook,
				function ( $title ) use ( $resolve, $endpoint ) {
					return $this->overlay_plain_singleton(
						$title,
						self::OWNER_ENDPOINT,
						$endpoint,
						self::FIELD_TITLE,
						$resolve
					);
				},
				10,
				1
			);
		}

		add_filter(
			self::HOOK_THANKYOU_RECEIVED,
			function ( $text, $order = null ) use ( $resolve ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return $this->overlay_plain_singleton(
					$text,
					self::OWNER_CHECKOUT,
					self::CHECKOUT_THANKYOU_ID,
					self::FIELD_LABEL,
					$resolve
				);
			},
			10,
			2
		);

		add_filter(
			self::HOOK_ORDER_ITEM_TOTALS,
			function ( $totals, $order = null, $tax_display = '' ) use ( $resolve ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return $this->overlay_order_item_totals( $totals, $resolve );
			},
			10,
			3
		);
	}

	/**
	 * Overlay catalog orderby / orderedby option labels (B1 / B2).
	 *
	 * Mutates map values only. Functional option keys are never changed.
	 *
	 * @param mixed                       $options    Option map.
	 * @param string                      $owner_type Identity owner_type (catalog_orderby|catalog_orderedby).
	 * @param callable(string): (?string) $resolve    Resolver.
	 * @return mixed
	 */
	private function overlay_catalog_order_map( $options, string $owner_type, callable $resolve ) {
		if ( ! is_array( $options ) ) {
			return $options;
		}

		$out = array();
		foreach ( $options as $key => $label ) {
			if ( ! is_string( $key ) || ! is_string( $label ) ) {
				$out[ $key ] = $label;
				continue;
			}
			if ( ! in_array( $key, self::ORDERBY_ALLOWLIST, true ) ) {
				$out[ $key ] = $label;
				continue;
			}

			try {
				$segment_key = $this->identity->build( self::ID, $owner_type, $key, self::FIELD_LABEL );
			} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$out[ $key ] = $label;
				continue;
			}

			$translated = $resolve( $segment_key );
			if ( ! is_string( $translated ) ) {
				$out[ $key ] = $label;
				continue;
			}
			$plain       = IntegrationSecurity::sanitize_plain( $translated );
			$out[ $key ] = '' !== $plain ? $plain : $label;
		}

		return $out;
	}

	/**
	 * Overlay attribute / variation attribute labels (P5 / P7).
	 *
	 * @param mixed                       $label    Source label.
	 * @param mixed                       $name     Attribute name/slug.
	 * @param mixed                       $product  Optional WC product.
	 * @param callable(string): (?string) $resolve  Resolver.
	 * @return mixed
	 */
	private function overlay_attribute_label( $label, $name, $product, callable $resolve ) {
		if ( ! is_string( $label ) || ! is_string( $name ) || '' === $name ) {
			return $label;
		}
		$product_id = 0;
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			$product_id = (int) $product->get_id();
		}
		if ( $product_id <= 0 && function_exists( 'get_the_ID' ) ) {
			$product_id = (int) get_the_ID();
		}
		if ( $product_id <= 0 ) {
			return $label;
		}

		$slug = $this->normalize_token( $name );
		if ( '' === $slug ) {
			$slug = $this->normalize_token( sanitize_title( $name ) );
		}
		if ( '' === $slug ) {
			return $label;
		}

		$is_variation = $this->product_attribute_is_variation( $product, $name, $slug );
		$keys         = array();
		if ( $is_variation ) {
			$keys[] = array( self::FIELD_VARIATION_ATTRIBUTE_NAME, $slug );
		}
		$keys[] = array( self::FIELD_ATTRIBUTE_NAME, $slug );

		foreach ( $keys as $parts ) {
			try {
				$key = $this->identity->build( self::ID, 'product', (string) $product_id, $parts[0], $parts[1] );
			} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				continue;
			}
			$translated = $resolve( $key );
			if ( ! is_string( $translated ) ) {
				continue;
			}
			$plain = IntegrationSecurity::sanitize_plain( $translated );
			if ( '' !== $plain ) {
				return $plain;
			}
		}

		return $label;
	}

	/**
	 * Overlay term archive titles (C3 / C5).
	 *
	 * @param mixed                       $title   Source title.
	 * @param callable(string): (?string) $resolve Resolver.
	 * @return mixed
	 */
	private function overlay_current_term_name( $title, callable $resolve ) {
		if ( ! is_string( $title ) ) {
			return $title;
		}
		$term = $this->current_catalog_term();
		if ( null === $term ) {
			return $title;
		}
		try {
			$key = $this->identity->build( self::ID, $term['taxonomy'], (string) $term['term_id'], self::FIELD_NAME );
		} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return $title;
		}
		$translated = $resolve( $key );
		if ( ! is_string( $translated ) ) {
			return $title;
		}
		$plain = IntegrationSecurity::sanitize_plain( $translated );
		return '' !== $plain ? $plain : $title;
	}

	/**
	 * Overlay term descriptions (C4 / C6).
	 *
	 * @param mixed                       $description Source description.
	 * @param mixed                       $term_id     Term ID.
	 * @param callable(string): (?string) $resolve     Resolver.
	 * @return mixed
	 */
	private function overlay_term_description( $description, $term_id, callable $resolve ) {
		if ( ! is_string( $description ) ) {
			return $description;
		}
		$term_id = (int) $term_id;
		if ( $term_id <= 0 ) {
			return $description;
		}
		$taxonomy = '';
		if ( function_exists( 'get_term' ) ) {
			$term = get_term( $term_id );
			if ( is_object( $term ) && ! is_wp_error( $term ) && isset( $term->taxonomy ) ) {
				$taxonomy = (string) $term->taxonomy;
			}
		}
		if ( self::TAXONOMY_CAT !== $taxonomy && self::TAXONOMY_TAG !== $taxonomy ) {
			return $description;
		}
		try {
			$key = $this->identity->build( self::ID, $taxonomy, (string) $term_id, self::FIELD_DESCRIPTION );
		} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return $description;
		}
		$translated = $resolve( $key );
		if ( ! is_string( $translated ) || '' === $translated ) {
			return $description;
		}
		if ( function_exists( 'wp_kses_post' ) ) {
			return wp_kses_post( $translated );
		}
		return $translated;
	}

	/**
	 * Whether a product attribute is variation-enabled.
	 *
	 * @param mixed  $product Product object.
	 * @param string $name    Attribute name.
	 * @param string $slug    Normalized slug.
	 */
	private function product_attribute_is_variation( $product, string $name, string $slug ): bool {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_attributes' ) ) {
			return false;
		}
		foreach ( $product->get_attributes() as $key => $attribute ) {
			$key_slug  = $this->normalize_token( is_string( $key ) ? $key : '' );
			$attr_name = is_object( $attribute ) && method_exists( $attribute, 'get_name' )
				? (string) $attribute->get_name()
				: '';
			$matches   = ( $slug === $key_slug )
				|| ( $slug === $this->normalize_token( $attr_name ) )
				|| ( $name === $attr_name );
			if ( ! $matches ) {
				continue;
			}
			return is_object( $attribute )
				&& method_exists( $attribute, 'get_variation' )
				&& (bool) $attribute->get_variation();
		}
		return false;
	}

	/**
	 * Current queried product_cat / product_tag term, if any.
	 *
	 * @return array{taxonomy:string,term_id:int}|null
	 */
	private function current_catalog_term(): ?array {
		if ( ! function_exists( 'get_queried_object' ) ) {
			return null;
		}
		$obj = get_queried_object();
		if ( ! is_object( $obj ) || ! isset( $obj->term_id, $obj->taxonomy ) ) {
			return null;
		}
		$taxonomy = (string) $obj->taxonomy;
		if ( self::TAXONOMY_CAT !== $taxonomy && self::TAXONOMY_TAG !== $taxonomy ) {
			return null;
		}
		$term_id = (int) $obj->term_id;
		if ( $term_id <= 0 ) {
			return null;
		}
		return array(
			'taxonomy' => $taxonomy,
			'term_id'  => $term_id,
		);
	}

	/**
	 * Test helper: mutate simulated plugin state.
	 *
	 * @param bool|null   $installed     Installed.
	 * @param bool|null   $active        Active.
	 * @param string|null $version       Version.
	 * @param bool|null   $disabled      Disabled.
	 * @param bool|null   $hooks_present Hooks present.
	 */
	public function configure(
		?bool $installed = null,
		?bool $active = null,
		?string $version = null,
		?bool $disabled = null,
		?bool $hooks_present = null
	): void {
		if ( null !== $installed ) {
			$this->installed = $installed;
		}
		if ( null !== $active ) {
			$this->active = $active;
		}
		if ( null !== $version ) {
			$this->version = $version;
		}
		if ( null !== $disabled ) {
			$this->disabled = $disabled;
		}
		if ( null !== $hooks_present ) {
			$this->hooks_present = $hooks_present;
		}
	}

	/**
	 * Resolved shop page ID (production via wc_get_page_id).
	 */
	public function resolved_shop_page_id(): int {
		if ( null !== $this->shop_page_id ) {
			return $this->shop_page_id;
		}
		if ( function_exists( 'wc_get_page_id' ) ) {
			$id = (int) wc_get_page_id( 'shop' );
			return $id > 0 ? $id : 0;
		}
		return 0;
	}

	/**
	 * Append a unit if its segment key is unique.
	 *
	 * @param array<int, TranslationUnitDescriptor> $units Units.
	 * @param array<string, true>                   $seen  Seen keys.
	 * @param TranslationUnitDescriptor             $unit  Unit.
	 * @throws \RuntimeException Duplicate keys.
	 */
	private function append_unique( array &$units, array &$seen, TranslationUnitDescriptor $unit ): void {
		if ( isset( $seen[ $unit->segment_key ] ) ) {
			throw new \RuntimeException( 'Duplicate WooCommerce segment key.' );
		}
		$seen[ $unit->segment_key ] = true;
		$units[]                    = $unit;
	}

	/**
	 * Extract Supported attribute-name units for one product.
	 *
	 * @param int $product_id Product post ID.
	 * @return list<TranslationUnitDescriptor>
	 */
	private function extract_product_attribute_units( int $product_id ): array {
		$units = array();
		foreach ( $this->read_product_attributes( $product_id ) as $attr ) {
			$slug  = $this->normalize_token( $attr['slug'] );
			$label = IntegrationSecurity::sanitize_plain( $attr['label'] );
			if ( '' === $slug || '' === $label ) {
				continue;
			}

			try {
				$key = $this->identity->build(
					self::ID,
					'product',
					(string) $product_id,
					self::FIELD_ATTRIBUTE_NAME,
					$slug
				);
			} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				continue;
			}

			$units[] = new TranslationUnitDescriptor(
				$key,
				$label,
				Store::source_hash( $label, Store::FORMAT_PLAIN ),
				Store::FORMAT_PLAIN,
				Contract::OWNERSHIP_RECORD,
				'product',
				(string) $product_id,
				self::FIELD_ATTRIBUTE_NAME,
				'Attribute name',
				self::ID,
				'Product #' . $product_id
			);

			if ( ! empty( $attr['variation'] ) ) {
				try {
					$vkey = $this->identity->build(
						self::ID,
						'product',
						(string) $product_id,
						self::FIELD_VARIATION_ATTRIBUTE_NAME,
						$slug
					);
				} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					continue;
				}
				$units[] = new TranslationUnitDescriptor(
					$vkey,
					$label,
					Store::source_hash( $label, Store::FORMAT_PLAIN ),
					Store::FORMAT_PLAIN,
					Contract::OWNERSHIP_RECORD,
					'product',
					(string) $product_id,
					self::FIELD_VARIATION_ATTRIBUTE_NAME,
					'Variation attribute name',
					self::ID,
					'Product #' . $product_id
				);
			}
		}
		return $units;
	}

	/**
	 * Extract Supported catalog term units for the shop page host.
	 *
	 * @return list<TranslationUnitDescriptor>
	 */
	private function extract_catalog_term_units(): array {
		$units = array();
		foreach ( $this->read_catalog_terms() as $term ) {
			$taxonomy = $term['taxonomy'];
			if ( self::TAXONOMY_CAT !== $taxonomy && self::TAXONOMY_TAG !== $taxonomy ) {
				continue;
			}
			$term_id = (int) $term['term_id'];
			if ( $term_id <= 0 ) {
				continue;
			}

			$name = IntegrationSecurity::sanitize_plain( $term['name'] );
			if ( '' !== $name ) {
				try {
					$key = $this->identity->build( self::ID, $taxonomy, (string) $term_id, self::FIELD_NAME );
				} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					$key = null;
				}
				if ( null !== $key ) {
					$units[] = new TranslationUnitDescriptor(
						$key,
						$name,
						Store::source_hash( $name, Store::FORMAT_PLAIN ),
						Store::FORMAT_PLAIN,
						Contract::OWNERSHIP_RECORD,
						$taxonomy,
						(string) $term_id,
						self::FIELD_NAME,
						'Term name',
						self::ID,
						$taxonomy
					);
				}
			}

			$description = trim( (string) $term['description'] );
			if ( '' !== $description ) {
				try {
					$dkey = $this->identity->build( self::ID, $taxonomy, (string) $term_id, self::FIELD_DESCRIPTION );
				} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					$dkey = null;
				}
				if ( null !== $dkey ) {
					$units[] = new TranslationUnitDescriptor(
						$dkey,
						$description,
						Store::source_hash( $description, Store::FORMAT_HTML ),
						Store::FORMAT_HTML,
						Contract::OWNERSHIP_RECORD,
						$taxonomy,
						(string) $term_id,
						self::FIELD_DESCRIPTION,
						'Term description',
						self::ID,
						$taxonomy
					);
				}
			}
		}
		return $units;
	}

	/**
	 * Extract Supported B1/B2 catalog ordering label units for the shop technical host.
	 *
	 * @return list<TranslationUnitDescriptor>
	 */
	private function extract_catalog_order_units(): array {
		$units = array();
		foreach ( $this->read_catalog_orderby_labels() as $key => $label ) {
			$unit = $this->make_catalog_order_unit( self::OWNER_CATALOG_ORDERBY, $key, $label, 'Catalog orderby: ' . $key );
			if ( null !== $unit ) {
				$units[] = $unit;
			}
		}
		foreach ( $this->read_catalog_orderedby_labels() as $key => $label ) {
			$unit = $this->make_catalog_order_unit( self::OWNER_CATALOG_ORDEREDBY, $key, $label, 'Catalog orderedby: ' . $key );
			if ( null !== $unit ) {
				$units[] = $unit;
			}
		}
		return $units;
	}

	/**
	 * Build one B1/B2 translation unit when key and label are valid.
	 *
	 * @param string $owner_type Identity owner_type.
	 * @param string $key        Functional Woo option key.
	 * @param string $label      Display label source.
	 * @param string $field_label Workspace field label.
	 */
	private function make_catalog_order_unit( string $owner_type, string $key, string $label, string $field_label ): ?TranslationUnitDescriptor {
		if ( ! in_array( $key, self::ORDERBY_ALLOWLIST, true ) ) {
			return null;
		}
		$plain = IntegrationSecurity::sanitize_plain( $label );
		if ( '' === $plain ) {
			return null;
		}
		try {
			$segment_key = $this->identity->build( self::ID, $owner_type, $key, self::FIELD_LABEL );
		} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return null;
		}

		return new TranslationUnitDescriptor(
			$segment_key,
			$plain,
			Store::source_hash( $plain, Store::FORMAT_PLAIN ),
			Store::FORMAT_PLAIN,
			Contract::OWNERSHIP_RECORD,
			$owner_type,
			$key,
			self::FIELD_LABEL,
			$field_label,
			self::ID,
			'WooCommerce archive chrome'
		);
	}

	/**
	 * Canonical B1 display labels (classic archive orderby map + relevance).
	 *
	 * @return array<string, string>
	 */
	private function read_catalog_orderby_labels(): array {
		if ( null !== $this->orderby_labels_provider ) {
			return ( $this->orderby_labels_provider )();
		}
		return array(
			'menu_order' => 'Default sorting',
			'popularity' => 'Sort by popularity',
			'rating'     => 'Sort by average rating',
			'date'       => 'Sort by latest',
			'price'      => 'Sort by price: low to high',
			'price-desc' => 'Sort by price: high to low',
			'relevance'  => 'Relevance',
		);
	}

	/**
	 * Canonical B2 display labels (classic orderedby / result-count SR map).
	 *
	 * @return array<string, string>
	 */
	private function read_catalog_orderedby_labels(): array {
		if ( null !== $this->orderedby_labels_provider ) {
			return ( $this->orderedby_labels_provider )();
		}
		return array(
			'menu_order' => 'Default sorting',
			'popularity' => 'Sorted by popularity',
			'rating'     => 'Sorted by average rating',
			'date'       => 'Sorted by latest',
			'price'      => 'Sorted by price: low to high',
			'price-desc' => 'Sorted by price: high to low',
		);
	}

	/**
	 * Read product attributes via WooCommerce API or test provider.
	 *
	 * @param int $product_id Product ID.
	 * @return list<array{slug:string,label:string,variation:bool}>
	 */
	private function read_product_attributes( int $product_id ): array {
		if ( null !== $this->attributes_provider ) {
			return ( $this->attributes_provider )( $product_id );
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}
		$out = array();
		foreach ( $product->get_attributes() as $key => $attribute ) {
			$slug = is_string( $key ) ? $key : '';
			if ( is_object( $attribute ) && method_exists( $attribute, 'get_name' ) ) {
				$name = (string) $attribute->get_name();
				if ( method_exists( $attribute, 'is_taxonomy' ) && $attribute->is_taxonomy() ) {
					$slug = $name;
				} elseif ( '' === $slug ) {
					$slug = sanitize_title( $name );
				}
				$label     = function_exists( 'wc_attribute_label' )
					? (string) wc_attribute_label( $name, $product )
					: $name;
				$variation = method_exists( $attribute, 'get_variation' ) && $attribute->get_variation();
				$out[]     = array(
					'slug'      => $slug,
					'label'     => $label,
					'variation' => (bool) $variation,
				);
			}
		}
		return $out;
	}

	/**
	 * Read allowlisted product_cat / product_tag terms.
	 *
	 * @return list<array{taxonomy:string,term_id:int,name:string,description:string}>
	 */
	private function read_catalog_terms(): array {
		if ( null !== $this->terms_provider ) {
			return ( $this->terms_provider )();
		}
		if ( ! function_exists( 'get_terms' ) ) {
			return array();
		}
		$out = array();
		foreach ( array( self::TAXONOMY_CAT, self::TAXONOMY_TAG ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( ! is_object( $term ) || ! isset( $term->term_id ) ) {
					continue;
				}
				$out[] = array(
					'taxonomy'    => $taxonomy,
					'term_id'     => (int) $term->term_id,
					'name'        => (string) ( $term->name ?? '' ),
					'description' => (string) ( $term->description ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Whether the post is the configured WooCommerce checkout page.
	 *
	 * @param WP_Post $post Post.
	 */
	private function is_checkout_page( WP_Post $post ): bool {
		$id = $this->resolved_checkout_page_id();
		return $id > 0 && (int) $post->ID === $id;
	}

	/**
	 * Whether the post is the configured WooCommerce myaccount page.
	 *
	 * @param WP_Post $post Post.
	 */
	private function is_myaccount_page( WP_Post $post ): bool {
		$id = $this->resolved_myaccount_page_id();
		return $id > 0 && (int) $post->ID === $id;
	}

	/**
	 * Resolved checkout page ID.
	 */
	public function resolved_checkout_page_id(): int {
		if ( null !== $this->checkout_page_id ) {
			return $this->checkout_page_id;
		}
		if ( function_exists( 'wc_get_page_id' ) ) {
			$id = (int) wc_get_page_id( 'checkout' );
			return $id > 0 ? $id : 0;
		}
		return 0;
	}

	/**
	 * Resolved myaccount page ID.
	 */
	public function resolved_myaccount_page_id(): int {
		if ( null !== $this->myaccount_page_id ) {
			return $this->myaccount_page_id;
		}
		if ( function_exists( 'wc_get_page_id' ) ) {
			$id = (int) wc_get_page_id( 'myaccount' );
			return $id > 0 ? $id : 0;
		}
		return 0;
	}

	/**
	 * Extract Supported CJ3 + CJ6 units for the checkout technical host.
	 *
	 * @return list<TranslationUnitDescriptor>
	 */
	private function extract_checkout_journey_units(): array {
		$units = array();
		foreach ( $this->read_checkout_field_labels() as $field_key => $label ) {
			$unit = $this->make_journey_unit(
				self::OWNER_CHECKOUT_FIELD,
				(string) $field_key,
				self::FIELD_LABEL,
				$label,
				'Checkout field: ' . $field_key
			);
			if ( null !== $unit ) {
				$units[] = $unit;
			}
		}

		$button = $this->make_journey_unit(
			self::OWNER_CHECKOUT,
			self::CHECKOUT_ORDER_BUTTON_ID,
			self::FIELD_LABEL,
			'Place order',
			'Place order button'
		);
		if ( null !== $button ) {
			$units[] = $button;
		}

		$thanks = $this->make_journey_unit(
			self::OWNER_CHECKOUT,
			self::CHECKOUT_THANKYOU_ID,
			self::FIELD_LABEL,
			'Thank you. Your order has been received.',
			'Order received text'
		);
		if ( null !== $thanks ) {
			$units[] = $thanks;
		}

		foreach ( $this->read_order_totals_labels() as $row_key => $label ) {
			if ( ! in_array( $row_key, self::ORDER_TOTALS_ALLOWLIST, true ) ) {
				continue;
			}
			$unit = $this->make_journey_unit(
				self::OWNER_ORDER_TOTALS,
				(string) $row_key,
				self::FIELD_LABEL,
				$label,
				'Order totals: ' . $row_key
			);
			if ( null !== $unit ) {
				$units[] = $unit;
			}
		}

		return $units;
	}

	/**
	 * Extract Supported A.7d email subject/heading units (checkout technical host).
	 *
	 * @return list<TranslationUnitDescriptor>
	 */
	private function extract_customer_email_units(): array {
		$units  = array();
		$chrome = $this->read_email_chrome();
		foreach ( self::EMAIL_ID_ALLOWLIST as $email_id ) {
			$row = $chrome[ $email_id ] ?? null;
			if ( ! is_array( $row ) ) {
				continue;
			}
			$subject = isset( $row['subject'] ) ? (string) $row['subject'] : '';
			$heading = isset( $row['heading'] ) ? (string) $row['heading'] : '';
			$subject_unit = $this->make_journey_unit(
				self::OWNER_EMAIL,
				$email_id,
				self::FIELD_SUBJECT,
				$subject,
				'Email subject: ' . $email_id,
				'WooCommerce customer emails'
			);
			if ( null !== $subject_unit ) {
				$units[] = $subject_unit;
			}
			$heading_unit = $this->make_journey_unit(
				self::OWNER_EMAIL,
				$email_id,
				self::FIELD_HEADING,
				$heading,
				'Email heading: ' . $email_id,
				'WooCommerce customer emails'
			);
			if ( null !== $heading_unit ) {
				$units[] = $heading_unit;
			}
		}
		return $units;
	}

	/**
	 * Source subject/heading chrome templates for Supported emails.
	 *
	 * @return array<string, array{subject:string,heading:string}>
	 */
	private function read_email_chrome(): array {
		if ( null !== $this->email_chrome_provider ) {
			$provided = ( $this->email_chrome_provider )();
			return is_array( $provided ) ? $provided : array();
		}

		$out = array();
		foreach ( self::EMAIL_ID_ALLOWLIST as $email_id ) {
			$subject = self::EMAIL_DEFAULT_SUBJECTS[ $email_id ] ?? '';
			$heading = self::EMAIL_DEFAULT_HEADINGS[ $email_id ] ?? '';
			if ( function_exists( 'WC' ) && is_object( WC() ) && is_callable( array( WC(), 'mailer' ) ) ) {
				$mailer = WC()->mailer();
				if ( is_object( $mailer ) && is_callable( array( $mailer, 'get_emails' ) ) ) {
					foreach ( (array) $mailer->get_emails() as $email ) {
						if ( ! is_object( $email ) || ! isset( $email->id ) || (string) $email->id !== $email_id ) {
							continue;
						}
						if ( is_callable( array( $email, 'get_default_subject' ) ) ) {
							$subject = (string) $email->get_default_subject();
						}
						if ( is_callable( array( $email, 'get_default_heading' ) ) ) {
							$heading = (string) $email->get_default_heading();
						}
						if ( is_callable( array( $email, 'get_option' ) ) ) {
							$opt_subject = $email->get_option( 'subject' );
							$opt_heading = $email->get_option( 'heading' );
							if ( is_string( $opt_subject ) && '' !== $opt_subject ) {
								$subject = $opt_subject;
							}
							if ( is_string( $opt_heading ) && '' !== $opt_heading ) {
								$heading = $opt_heading;
							}
						}
						break;
					}
				}
			}
			$out[ $email_id ] = array(
				'subject' => $subject,
				'heading' => $heading,
			);
		}
		return $out;
	}

	/**
	 * Extract Supported CJ4 units for the myaccount technical host.
	 *
	 * @return list<TranslationUnitDescriptor>
	 */
	private function extract_account_journey_units(): array {
		$units = array();
		$menu  = $this->read_account_menu_labels();
		foreach ( $menu as $endpoint => $label ) {
			$endpoint = $this->normalize_token( (string) $endpoint );
			if ( '' === $endpoint ) {
				continue;
			}
			$menu_unit = $this->make_journey_unit(
				self::OWNER_ACCOUNT_MENU,
				$endpoint,
				self::FIELD_LABEL,
				$label,
				'Account menu: ' . $endpoint
			);
			if ( null !== $menu_unit ) {
				$units[] = $menu_unit;
			}
			$title_unit = $this->make_journey_unit(
				self::OWNER_ENDPOINT,
				$endpoint,
				self::FIELD_TITLE,
				$label,
				'Account endpoint: ' . $endpoint
			);
			if ( null !== $title_unit ) {
				$units[] = $title_unit;
			}
		}
		return $units;
	}

	/**
	 * Build one journey / email translation unit.
	 *
	 * @param string $owner_type     Identity owner_type.
	 * @param string $owner_id       Identity owner_id.
	 * @param string $field          Identity field.
	 * @param string $label          Source label.
	 * @param string $field_label    Workspace label.
	 * @param string $parent_context Parent context string.
	 */
	private function make_journey_unit(
		string $owner_type,
		string $owner_id,
		string $field,
		string $label,
		string $field_label,
		string $parent_context = 'WooCommerce customer journey'
	): ?TranslationUnitDescriptor {
		$owner_id = $this->normalize_token( $owner_id );
		$plain    = IntegrationSecurity::sanitize_plain( $label );
		if ( '' === $owner_id || '' === $plain ) {
			return null;
		}
		try {
			$segment_key = $this->identity->build( self::ID, $owner_type, $owner_id, $field );
		} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return null;
		}

		return new TranslationUnitDescriptor(
			$segment_key,
			$plain,
			Store::source_hash( $plain, Store::FORMAT_PLAIN ),
			Store::FORMAT_PLAIN,
			Contract::OWNERSHIP_RECORD,
			$owner_type,
			$owner_id,
			$field,
			$field_label,
			self::ID,
			$parent_context
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function read_checkout_field_labels(): array {
		if ( null !== $this->checkout_field_labels_provider ) {
			return ( $this->checkout_field_labels_provider )();
		}
		$out = array();
		if ( function_exists( 'WC' ) && WC() && WC()->checkout() ) {
			$fields = WC()->checkout()->get_checkout_fields();
			if ( is_array( $fields ) ) {
				foreach ( $fields as $group ) {
					if ( ! is_array( $group ) ) {
						continue;
					}
					foreach ( $group as $key => $field ) {
						if ( ! is_array( $field ) || ! isset( $field['label'] ) || ! is_string( $field['label'] ) ) {
							continue;
						}
						$key = $this->normalize_token( (string) $key );
						if ( '' !== $key && '' !== $field['label'] ) {
							$out[ $key ] = $field['label'];
						}
					}
				}
			}
		}
		if ( array() !== $out ) {
			return $out;
		}
		return array(
			'billing_first_name'  => 'First name',
			'billing_last_name'   => 'Last name',
			'billing_email'       => 'Email address',
			'billing_phone'       => 'Phone',
			'billing_country'     => 'Country / Region',
			'billing_address_1'   => 'Street address',
			'billing_city'        => 'Town / City',
			'billing_postcode'    => 'Postcode / ZIP',
			'shipping_first_name' => 'First name',
			'shipping_last_name'  => 'Last name',
			'order_comments'      => 'Order notes',
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function read_account_menu_labels(): array {
		if ( null !== $this->account_menu_provider ) {
			return ( $this->account_menu_provider )();
		}
		$defaults = array(
			'dashboard'       => 'Dashboard',
			'orders'          => 'Orders',
			'downloads'       => 'Downloads',
			'edit-address'    => 'Addresses',
			'edit-account'    => 'Account details',
			'customer-logout' => 'Log out',
			'gift-cards'      => 'Gift cards',
		);
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'woocommerce_account_menu_items', $defaults );
			if ( is_array( $filtered ) ) {
				$out = array();
				foreach ( $filtered as $key => $label ) {
					if ( is_string( $key ) && is_string( $label ) && '' !== $label ) {
						$out[ $key ] = $label;
					}
				}
				if ( array() !== $out ) {
					return $out;
				}
			}
		}
		return $defaults;
	}

	/**
	 * @return array<string, string>
	 */
	private function read_order_totals_labels(): array {
		if ( null !== $this->order_totals_labels_provider ) {
			return ( $this->order_totals_labels_provider )();
		}
		return array(
			'cart_subtotal'  => 'Subtotal',
			'shipping'       => 'Shipping',
			'discount'       => 'Discount',
			'order_total'    => 'Total',
			'payment_method' => 'Payment method',
		);
	}

	/**
	 * Overlay checkout field labels (CJ3.1).
	 *
	 * @param mixed                       $fields  Checkout fields map.
	 * @param callable(string): (?string) $resolve Resolver.
	 * @return mixed
	 */
	private function overlay_checkout_fields( $fields, callable $resolve ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}
		foreach ( $fields as $group => $group_fields ) {
			if ( ! is_array( $group_fields ) ) {
				continue;
			}
			foreach ( $group_fields as $key => $field ) {
				if ( ! is_array( $field ) || ! isset( $field['label'] ) || ! is_string( $field['label'] ) ) {
					continue;
				}
				$norm = $this->normalize_token( (string) $key );
				if ( '' === $norm ) {
					continue;
				}
				try {
					$segment_key = $this->identity->build( self::ID, self::OWNER_CHECKOUT_FIELD, $norm, self::FIELD_LABEL );
				} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
					continue;
				}
				$translated = $resolve( $segment_key );
				if ( ! is_string( $translated ) ) {
					continue;
				}
				$plain = IntegrationSecurity::sanitize_plain( $translated );
				if ( '' !== $plain ) {
					$fields[ $group ][ $key ]['label'] = $plain;
				}
			}
		}
		return $fields;
	}

	/**
	 * Overlay a flat endpoint => label map (CJ4.1).
	 *
	 * @param mixed                       $items      Map.
	 * @param string                      $owner_type Identity owner_type.
	 * @param string                      $field      Identity field.
	 * @param callable(string): (?string) $resolve    Resolver.
	 * @return mixed
	 */
	private function overlay_string_map( $items, string $owner_type, string $field, callable $resolve ) {
		if ( ! is_array( $items ) ) {
			return $items;
		}
		$out = array();
		foreach ( $items as $key => $label ) {
			if ( ! is_string( $key ) || ! is_string( $label ) ) {
				$out[ $key ] = $label;
				continue;
			}
			$norm = $this->normalize_token( $key );
			if ( '' === $norm ) {
				$out[ $key ] = $label;
				continue;
			}
			try {
				$segment_key = $this->identity->build( self::ID, $owner_type, $norm, $field );
			} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$out[ $key ] = $label;
				continue;
			}
			$translated = $resolve( $segment_key );
			if ( ! is_string( $translated ) ) {
				$out[ $key ] = $label;
				continue;
			}
			$plain       = IntegrationSecurity::sanitize_plain( $translated );
			$out[ $key ] = '' !== $plain ? $plain : $label;
		}
		return $out;
	}

	/**
	 * Overlay a singleton plain string (CJ3.2 / CJ4.2 / CJ6.1).
	 *
	 * @param mixed                       $text       Source text.
	 * @param string                      $owner_type Owner type.
	 * @param string                      $owner_id   Owner id.
	 * @param string                      $field      Field.
	 * @param callable(string): (?string) $resolve    Resolver.
	 * @return mixed
	 */
	private function overlay_plain_singleton( $text, string $owner_type, string $owner_id, string $field, callable $resolve ) {
		if ( ! is_string( $text ) ) {
			return $text;
		}
		try {
			$segment_key = $this->identity->build( self::ID, $owner_type, $owner_id, $field );
		} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return $text;
		}
		$translated = $resolve( $segment_key );
		if ( ! is_string( $translated ) ) {
			return $text;
		}
		$plain = IntegrationSecurity::sanitize_plain( $translated );
		return '' !== $plain ? $plain : $text;
	}

	/**
	 * Overlay order item totals labels (CJ6.2); values untouched.
	 *
	 * @param mixed                       $totals  Totals rows.
	 * @param callable(string): (?string) $resolve Resolver.
	 * @return mixed
	 */
	private function overlay_order_item_totals( $totals, callable $resolve ) {
		if ( ! is_array( $totals ) ) {
			return $totals;
		}
		foreach ( $totals as $key => $row ) {
			if ( ! is_string( $key ) || ! in_array( $key, self::ORDER_TOTALS_ALLOWLIST, true ) ) {
				continue;
			}
			if ( ! is_array( $row ) || ! isset( $row['label'] ) || ! is_string( $row['label'] ) ) {
				continue;
			}
			try {
				$segment_key = $this->identity->build( self::ID, self::OWNER_ORDER_TOTALS, $key, self::FIELD_LABEL );
			} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				continue;
			}
			$translated = $resolve( $segment_key );
			if ( ! is_string( $translated ) ) {
				continue;
			}
			$plain = IntegrationSecurity::sanitize_plain( $translated );
			if ( '' !== $plain ) {
				$totals[ $key ]['label'] = $plain;
			}
		}
		return $totals;
	}

	/**
	 * Whether the post is the WooCommerce shop page.
	 *
	 * @param WP_Post $post Post.
	 */
	private function is_shop_page( WP_Post $post ): bool {
		$shop_id = $this->resolved_shop_page_id();
		return $shop_id > 0 && (int) $post->ID === $shop_id;
	}

	/**
	 * Normalize an identity token to PluginIdentity-safe characters.
	 *
	 * @param string $token Raw token.
	 */
	private function normalize_token( string $token ): string {
		$token = strtolower( trim( $token ) );
		$token = preg_replace( '/[^a-z0-9_-]/', '', $token ) ?? '';
		return $token;
	}

	/**
	 * Whether WooCommerce appears installed.
	 */
	private function is_installed(): bool {
		if ( null !== $this->installed ) {
			return $this->installed;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		return isset( $plugins[ self::PLUGIN_BASENAME ] ) || class_exists( 'WooCommerce', false );
	}

	/**
	 * Whether WooCommerce is active.
	 */
	private function is_active(): bool {
		if ( null !== $this->active ) {
			return $this->active;
		}
		return class_exists( 'WooCommerce', false )
			|| ( function_exists( 'is_plugin_active' ) && is_plugin_active( self::PLUGIN_BASENAME ) );
	}

	/**
	 * Whether integration is disabled via filter.
	 */
	private function is_disabled(): bool {
		if ( null !== $this->disabled ) {
			return $this->disabled;
		}
		/**
		 * Filters whether the WooCommerce A.7a integration is disabled.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $disabled Whether the integration is disabled.
		 */
		return (bool) apply_filters( 'aiml_woocommerce_integration_disabled', false );
	}

	/**
	 * Resolved plugin version string.
	 */
	private function resolved_version(): string {
		if ( null !== $this->version ) {
			return $this->version;
		}
		if ( defined( 'WC_VERSION' ) ) {
			return (string) WC_VERSION;
		}
		return '0';
	}

	/**
	 * Whether required overlay hooks exist as filter names (registration-time check).
	 */
	private function required_hooks_present(): bool {
		if ( null !== $this->hooks_present ) {
			return $this->hooks_present;
		}
		// Woo / WP always define these as filterable symbols when the plugin is loaded;
		// presence is treated as true when WooCommerce class exists.
		return class_exists( 'WooCommerce', false ) || function_exists( 'wc_attribute_label' );
	}
}
