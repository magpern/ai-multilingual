<?php
/**
 * Read-only native/hosted term translation resolution.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Integration\WooCommerce\WooCommerceIntegration;
use AIMultilingual\Surface\AdmittedTaxonomies;

/**
 * The single place that knows both addresses a term translation can live at.
 *
 * Before TSC.1 a term translation was stored against the page that happened to
 * render it — the shop page for catalog terms, the posts page for core ones —
 * under an integration-owned segment key. Those rows still exist and still hold
 * real work, so every read has to look in two places until adoption moves them.
 *
 * Strictly read-only (ADR-0021 §8): no writes, no locks, no adoption, and no
 * publication decisions. Callers apply overlay eligibility and TI.7 to the row
 * this returns. Duplicating this lookup elsewhere would let the two copies
 * disagree about which row is authoritative, which is exactly the bug the
 * native-precedence rule exists to prevent.
 */
final class TermTranslationResolver {

	/**
	 * Provenance of a resolved row.
	 */
	public const IDENTITY_NATIVE        = 'native';
	public const IDENTITY_COMPATIBILITY = 'compatibility';

	/**
	 * Taxonomies whose hosted rows live on the WooCommerce shop page.
	 *
	 * @var list<string>
	 */
	private const SHOP_HOSTED_TAXONOMIES = array(
		WooCommerceIntegration::TAXONOMY_CAT,
		WooCommerceIntegration::TAXONOMY_TAG,
	);

	/**
	 * Taxonomies whose hosted rows live on the posts page.
	 *
	 * @var list<string>
	 */
	private const POSTS_PAGE_HOSTED_TAXONOMIES = array( 'category', 'post_tag' );

	/**
	 * Builds the resolver.
	 *
	 * @param Store               $store    Segment store (reads only).
	 * @param PluginIdentity|null $identity Segment key serializer.
	 */
	public function __construct(
		private Store $store,
		private ?PluginIdentity $identity = null
	) {
	}

	/**
	 * Resolves the authoritative row for one logical term field.
	 *
	 * @param int    $term_id       Term id.
	 * @param string $taxonomy      Taxonomy slug.
	 * @param string $logical_field name, description, or a Rank Math segment key.
	 * @param int    $language_id   Language id.
	 * @return array{row: object, identity: string, source_type: string, source_id: int, segment_key: string}|null
	 */
	public function resolve( int $term_id, string $taxonomy, string $logical_field, int $language_id ): ?array {
		$ref = $this->compat_ref( $term_id, $taxonomy, $logical_field, $language_id );

		if ( null === $ref ) {
			return null;
		}

		$native = $this->store->get(
			Store::SOURCE_TERM,
			$ref->term_id,
			$ref->language_id,
			$ref->native_segment_key
		);

		if ( null !== $native ) {
			return array(
				'row'         => $native,
				'identity'    => self::IDENTITY_NATIVE,
				'source_type' => Store::SOURCE_TERM,
				'source_id'   => $ref->term_id,
				'segment_key' => $ref->native_segment_key,
			);
		}

		if ( ! $ref->has_hosted_address() ) {
			return null;
		}

		$hosted = $this->store->get(
			$ref->hosted_source_type,
			$ref->hosted_source_id,
			$ref->language_id,
			$ref->hosted_segment_key
		);

		if ( null === $hosted ) {
			return null;
		}

		return array(
			'row'         => $hosted,
			'identity'    => self::IDENTITY_COMPATIBILITY,
			'source_type' => $ref->hosted_source_type,
			'source_id'   => $ref->hosted_source_id,
			'segment_key' => $ref->hosted_segment_key,
		);
	}

	/**
	 * Term reference behind one concrete Store address, when it holds a term field.
	 *
	 * Callers that start from a stored row — OTL, review, publication, Jobs —
	 * know a `(source_type, source_id, segment_key)` triple, not a logical term
	 * field. This translates one into the other so the authority lock and the
	 * adoption trigger share the resolver's view of where a term field lives
	 * instead of re-deriving it. Returns null for everything that is not a term
	 * field, which is the common case.
	 *
	 * @param string $source_type Stored source type.
	 * @param int    $source_id   Stored source id.
	 * @param string $segment_key Stored segment key.
	 * @param int    $language_id Language id.
	 */
	public function ref_for_store_address( string $source_type, int $source_id, string $segment_key, int $language_id ): ?TermCompatRef {
		if ( Store::SOURCE_TERM === $source_type ) {
			return $this->compat_ref( $source_id, $this->taxonomy_of( $source_id ), $segment_key, $language_id );
		}

		if ( Store::SOURCE_POST !== $source_type ) {
			return null;
		}

		$hosted = $this->term_field_for_segment_key( $segment_key );
		if ( null === $hosted ) {
			return null;
		}

		$taxonomy = '' !== $hosted['taxonomy'] ? $hosted['taxonomy'] : $this->taxonomy_of( $hosted['term_id'] );

		$ref = $this->compat_ref( $hosted['term_id'], $taxonomy, $hosted['logical_field'], $language_id );

		// A key that would be hosted somewhere else is not this row's term
		// field; remapping it would move a write onto an unrelated identity.
		if ( null === $ref || $ref->hosted_source_id !== $source_id ) {
			return null;
		}

		return $ref;
	}

	/**
	 * Term field a hosted compatibility segment key stands for.
	 *
	 * @param string $segment_key Stored segment key.
	 * @return array{term_id: int, taxonomy: string, logical_field: string}|null
	 */
	public function term_field_for_segment_key( string $segment_key ): ?array {
		if ( ! $this->is_plugin_key( $segment_key ) ) {
			return null;
		}

		$parsed = $this->identity()->parse( $segment_key );
		if ( ! is_array( $parsed ) ) {
			return null;
		}

		$integration = (string) $parsed['integration_id'];
		$owner_type  = (string) $parsed['owner_type'];
		$owner_id    = (string) $parsed['owner_id'];
		$field       = (string) $parsed['field'];

		if ( ! ctype_digit( $owner_id ) || (int) $owner_id <= 0 ) {
			return null;
		}

		if (
			WooCommerceIntegration::ID === $integration
			&& in_array( $owner_type, self::SHOP_HOSTED_TAXONOMIES, true )
			&& in_array( $field, array( TermExtractor::FIELD_NAME, TermExtractor::FIELD_DESCRIPTION ), true )
		) {
			return array(
				'term_id'       => (int) $owner_id,
				'taxonomy'      => $owner_type,
				'logical_field' => $field,
			);
		}

		if ( RankMathIntegration::ID === $integration && RankMathIntegration::OWNER_TERM === $owner_type ) {
			// Rank Math term SEO keeps its segment key through adoption, so the
			// key itself is the logical field.
			return array(
				'term_id'       => (int) $owner_id,
				'taxonomy'      => '',
				'logical_field' => $segment_key,
			);
		}

		return null;
	}

	/**
	 * Builds the native and hosted addresses of one logical term field.
	 *
	 * @param int    $term_id       Term id.
	 * @param string $taxonomy      Taxonomy slug.
	 * @param string $logical_field name, description, or a Rank Math segment key.
	 * @param int    $language_id   Language id.
	 */
	public function compat_ref( int $term_id, string $taxonomy, string $logical_field, int $language_id ): ?TermCompatRef {
		if ( $term_id <= 0 || $language_id <= 0 || '' === $logical_field ) {
			return null;
		}

		if ( ! AdmittedTaxonomies::admits( $taxonomy ) ) {
			return null;
		}

		if ( $this->is_plugin_key( $logical_field ) ) {
			// Rank Math term SEO keeps its own segment key after adoption; only
			// the identity it hangs from changes.
			$native_field_key   = Contract::FIELD_KEY;
			$native_segment_key = $logical_field;
		} elseif ( in_array( $logical_field, array( TermExtractor::FIELD_NAME, TermExtractor::FIELD_DESCRIPTION ), true ) ) {
			$native_field_key   = $logical_field;
			$native_segment_key = $logical_field;
		} else {
			return null;
		}

		$hosted_source_id   = $this->hosted_source_id( $taxonomy );
		$hosted_segment_key = $hosted_source_id > 0
			? $this->hosted_segment_key( $taxonomy, $term_id, $logical_field )
			: '';

		if ( '' === $hosted_segment_key ) {
			$hosted_source_id = 0;
		}

		return new TermCompatRef(
			$term_id,
			$taxonomy,
			$language_id,
			$logical_field,
			$native_field_key,
			$native_segment_key,
			$hosted_source_id > 0 ? Store::SOURCE_POST : '',
			$hosted_source_id,
			$hosted_source_id > 0 ? Contract::FIELD_KEY : '',
			$hosted_segment_key
		);
	}

	/**
	 * Taxonomy of a term, or '' when it cannot be read.
	 *
	 * @param int $term_id Term id.
	 */
	private function taxonomy_of( int $term_id ): string {
		if ( $term_id <= 0 || ! function_exists( 'get_term' ) ) {
			return '';
		}

		$term = get_term( $term_id );

		return is_object( $term ) && isset( $term->taxonomy ) ? (string) $term->taxonomy : '';
	}

	/**
	 * Post that hosts this taxonomy's compatibility rows, or 0 when none does.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	private function hosted_source_id( string $taxonomy ): int {
		if ( in_array( $taxonomy, self::SHOP_HOSTED_TAXONOMIES, true ) ) {
			return $this->shop_page_id();
		}

		if ( in_array( $taxonomy, self::POSTS_PAGE_HOSTED_TAXONOMIES, true ) ) {
			return $this->posts_page_id();
		}

		// Global attribute term values were never hosted anywhere: they are
		// native from the first write.
		return 0;
	}

	/**
	 * Segment key a hosted row for this field would carry.
	 *
	 * @param string $taxonomy      Taxonomy slug.
	 * @param int    $term_id       Term id.
	 * @param string $logical_field Logical field.
	 */
	private function hosted_segment_key( string $taxonomy, int $term_id, string $logical_field ): string {
		if ( $this->is_plugin_key( $logical_field ) ) {
			return $logical_field;
		}

		// Only the WooCommerce catalog taxonomies ever had hosted name and
		// description rows; core terms went straight to the native identity.
		if ( ! in_array( $taxonomy, self::SHOP_HOSTED_TAXONOMIES, true ) ) {
			return '';
		}

		try {
			return $this->identity()->build(
				WooCommerceIntegration::ID,
				$taxonomy,
				(string) $term_id,
				$logical_field
			);
		} catch ( \InvalidArgumentException $error ) {
			unset( $error );

			return '';
		}
	}

	/**
	 * Whether a logical field is already a serialized plugin segment key.
	 *
	 * @param string $logical_field Logical field.
	 */
	private function is_plugin_key( string $logical_field ): bool {
		return str_starts_with( $logical_field, Contract::SEGMENT_KEY_PREFIX . ':' );
	}

	/**
	 * Rank Math term segment key for a field (sole builder for term SEO keys).
	 *
	 * @param int    $term_id Term id.
	 * @param string $field   Rank Math field token.
	 */
	public function rank_math_segment_key( int $term_id, string $field ): string {
		if ( $term_id <= 0 || '' === $field ) {
			return '';
		}

		try {
			return $this->identity()->build(
				RankMathIntegration::ID,
				RankMathIntegration::OWNER_TERM,
				(string) $term_id,
				$field
			);
		} catch ( \InvalidArgumentException $error ) {
			unset( $error );

			return '';
		}
	}

	/**
	 * WooCommerce shop page id, or 0.
	 */
	private function shop_page_id(): int {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return 0;
		}

		$shop_id = (int) wc_get_page_id( 'shop' );

		return $shop_id > 0 ? $shop_id : 0;
	}

	/**
	 * Blog posts page id, or 0.
	 */
	private function posts_page_id(): int {
		if ( ! function_exists( 'get_option' ) ) {
			return 0;
		}

		$posts_page = (int) get_option( 'page_for_posts' );

		return $posts_page > 0 ? $posts_page : 0;
	}

	/**
	 * Lazily built segment key serializer.
	 */
	private function identity(): PluginIdentity {
		if ( null === $this->identity ) {
			$this->identity = new PluginIdentity();
		}

		return $this->identity;
	}
}
