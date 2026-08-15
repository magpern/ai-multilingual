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
	 * Code generation that introduced MSEO.3 public-capable shapes.
	 */
	public const CODE_CAPABILITY_EPOCH = 1;

	public const SHAPE_TERM_ARCHIVE       = 'term_archive';
	public const SHAPE_PAGE_HIERARCHICAL  = 'page_hierarchical';

	/**
	 * Shapes this code generation knows how to verify/admit.
	 */
	public const CODE_SHAPES = array(
		self::SHAPE_TERM_ARCHIVE,
		self::SHAPE_PAGE_HIERARCHICAL,
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
	 * @param list<string> $shapes Shape ids to admit (union with existing).
	 * @param int          $epoch  Verified capability epoch to set.
	 */
	public function commit_admission( array $shapes, int $epoch ): void {
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

		$next = array_merge(
			$this->settings->get(),
			array(
				'localized_urls_admitted_capabilities'     => $merged,
				'localized_urls_verified_capability_epoch' => max( 0, $epoch ),
			)
		);
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
