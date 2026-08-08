<?php
/**
 * WooCommerce Product, Catalog, and Archive Chrome Integration API v1 consumer (A.7a / A.7b).
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
 * Record-owned WooCommerce bridge for A.7a product/catalog and A.7b archive chrome.
 *
 * Product: attribute names (P5) and variation attribute names (P7).
 * Catalog: product_cat / product_tag name + description (C3–C6) on shop page host.
 * Archive chrome: catalog orderby / orderedby labels (B1–B2) on shop page technical anchor.
 * Title/excerpt/content (P1–P3, C1–C2) remain on the existing post Extractor/Renderer path.
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

	public const TAXONOMY_CAT = 'product_cat';

	public const TAXONOMY_TAG = 'product_tag';

	public const OWNER_CATALOG_ORDERBY = 'catalog_orderby';

	public const OWNER_CATALOG_ORDEREDBY = 'catalog_orderedby';

	public const FIELD_ATTRIBUTE_NAME = 'attribute_name';

	public const FIELD_VARIATION_ATTRIBUTE_NAME = 'variation_attribute_name';

	public const FIELD_NAME = 'name';

	public const FIELD_DESCRIPTION = 'description';

	public const FIELD_LABEL = 'label';

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
			$plain = IntegrationSecurity::sanitize_plain( $translated );
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
			'menu_order'  => 'Default sorting',
			'popularity'  => 'Sort by popularity',
			'rating'      => 'Sort by average rating',
			'date'        => 'Sort by latest',
			'price'       => 'Sort by price: low to high',
			'price-desc'  => 'Sort by price: high to low',
			'relevance'   => 'Relevance',
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
