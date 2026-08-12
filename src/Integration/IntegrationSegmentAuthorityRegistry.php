<?php
/**
 * Registry of IntegrationSegmentAuthority providers (TSC.3).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Integration;

/**
 * First-match authority lookup for hosted integration segments.
 */
final class IntegrationSegmentAuthorityRegistry {

	/**
	 * Registered authorities (first match wins).
	 *
	 * @var list<IntegrationSegmentAuthority>
	 */
	private array $authorities = array();

	/**
	 * Registers an authority (order = first match wins).
	 *
	 * @param IntegrationSegmentAuthority $authority Authority.
	 */
	public function register( IntegrationSegmentAuthority $authority ): void {
		$this->authorities[] = $authority;
	}

	/**
	 * First authority that applies to the row, or null.
	 *
	 * @param object $row Store row.
	 */
	public function for_row( object $row ): ?IntegrationSegmentAuthority {
		foreach ( $this->authorities as $authority ) {
			if ( $authority->applies( $row ) ) {
				return $authority;
			}
		}

		return null;
	}

	/**
	 * Whether any authority marks the row non-writable (compat deny).
	 *
	 * @param object $row Store row.
	 */
	public function denies_write( object $row ): bool {
		$key = (string) ( $row->segment_key ?? '' );
		if ( WooCommerce\AttributeLabelIdentity::is_taxonomy_compat_product_key( $key ) ) {
			return true;
		}

		return false;
	}
}
