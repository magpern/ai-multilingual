<?php
/**
 * Public admission authority for routing capabilities (MSEO.3 A1/A2).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Settings;

/**
 * Separates implemented capability from publicly admitted capability.
 *
 * Consumers of public URL surfaces must use this class — not raw
 * {@see RoutingCapabilityRegistry::supports_post()} alone — for new shapes.
 */
final class RoutingCapabilityAdmission {

	/**
	 * Code generation that introduced MSEO.3/MSEO.4 public-capable shapes.
	 */
	public const CODE_CAPABILITY_EPOCH = 2;

	public const SHAPE_TERM_ARCHIVE               = 'term_archive';
	public const SHAPE_PAGE_HIERARCHICAL          = 'page_hierarchical';
	public const SHAPE_PRODUCT_CATEGORY_PERMALINK = 'product_category_permalink';

	/**
	 * Shapes this code generation knows how to verify/admit.
	 */
	public const CODE_SHAPES = array(
		self::SHAPE_TERM_ARCHIVE,
		self::SHAPE_PAGE_HIERARCHICAL,
		self::SHAPE_PRODUCT_CATEGORY_PERMALINK,
	);

	/**
	 * Constructs the admission authority.
	 *
	 * @param Settings                  $settings     Plugin settings.
	 * @param RoutingCapabilityRegistry $capabilities Implemented capability facts.
	 */
	public function __construct(
		private Settings $settings,
		private RoutingCapabilityRegistry $capabilities
	) {
	}

	/**
	 * Whether a capability shape is publicly admitted for visitor-facing use.
	 *
	 * @param string $shape Capability shape id.
	 */
	public function is_publicly_admitted( string $shape ): bool {
		if ( ! in_array( $shape, self::CODE_SHAPES, true ) ) {
			// Legacy MSEO.2 shapes are admitted whenever generation is on;
			// technical support remains the gate.
			return true;
		}

		$admitted = $this->settings->localized_urls_admitted_capabilities();

		return in_array( $shape, $admitted, true );
	}

	/**
	 * Whether a post may use localized public URL generation for its shape.
	 *
	 * @param \WP_Post $post Source post.
	 */
	public function is_post_publicly_localizable( \WP_Post $post ): bool {
		if ( ! $this->capabilities->supports_post( $post ) ) {
			return false;
		}

		$shape = $this->capabilities->capability_for_post( $post );
		if ( RoutingCapabilityRegistry::PAGE_HIERARCHICAL === $shape ) {
			return $this->is_publicly_admitted( self::SHAPE_PAGE_HIERARCHICAL );
		}
		if ( RoutingCapabilityRegistry::PRODUCT_CATEGORY_PERMALINK === $shape ) {
			if ( ! $this->is_publicly_admitted( self::SHAPE_PRODUCT_CATEGORY_PERMALINK ) ) {
				return false;
			}
			$fp  = $this->settings->localized_urls_woo_product_fingerprint();
			$cur = ( new WooProductPermalinkFingerprint() )->hash();

			return '' !== $fp && hash_equals( $fp, $cur );
		}

		return true;
	}

	/**
	 * Whether a term taxonomy may use localized public URL generation.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function is_term_publicly_localizable( string $taxonomy ): bool {
		if ( ! $this->capabilities->supports_term_taxonomy( $taxonomy ) ) {
			return false;
		}

		return $this->is_publicly_admitted( self::SHAPE_TERM_ARCHIVE );
	}

	/**
	 * Persists admission only after a complete successful verification pass.
	 *
	 * @param array<int, string> $shapes      Shape ids to admit (union with existing).
	 * @param int                $epoch       Verified capability epoch to set.
	 * @param string|null        $fingerprint Optional Woo product fingerprint to persist.
	 */
	public function commit_admission( array $shapes, int $epoch, ?string $fingerprint = null ): void {
		$allowed  = self::CODE_SHAPES;
		$incoming = array();
		foreach ( $shapes as $shape ) {
			$shape = (string) $shape;
			if ( in_array( $shape, $allowed, true ) ) {
				$incoming[] = $shape;
			}
		}

		$merged = array_values(
			array_unique(
				array_merge(
					$this->settings->localized_urls_admitted_capabilities(),
					$incoming
				)
			)
		);

		$payload = array(
			'localized_urls_admitted_capabilities'     => $merged,
			'localized_urls_verified_capability_epoch' => max( 0, $epoch ),
		);
		if ( null !== $fingerprint && '' !== $fingerprint ) {
			$payload['localized_urls_woo_product_fingerprint'] = $fingerprint;
		}

		$next = array_merge( $this->settings->get(), $payload );
		$this->settings->save( Settings::sanitize( $next ) );
		$this->settings->reload();
	}

	/**
	 * Implemented registry (technical support).
	 */
	public function registry(): RoutingCapabilityRegistry {
		return $this->capabilities;
	}
}
