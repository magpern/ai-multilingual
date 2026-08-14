<?php
/**
 * Prepared-route activation verification (read-only; MSEO.2 A6).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Language\Languages;
use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * Classifies active routes without mutating routes, history, or publication.
 */
final class SlugRouteActivationVerifier {

	/**
	 * Constructs the verifier.
	 *
	 * @param Languages                     $languages    Languages.
	 * @param RoutingCapabilityRegistry     $capabilities Capabilities.
	 * @param PathCanonicalizer             $paths        Path canonicalizer.
	 * @param SlugRouteRepository           $routes       Route repository.
	 * @param RouteHistoryRepository        $history      History repository.
	 * @param CanonicalPathCollisionChecker $collisions   Collision checker.
	 */
	public function __construct(
		private Languages $languages,
		private RoutingCapabilityRegistry $capabilities,
		private PathCanonicalizer $paths,
		private SlugRouteRepository $routes,
		private RouteHistoryRepository $history,
		private CanonicalPathCollisionChecker $collisions
	) {
	}

	/**
	 * Classifies one active route row.
	 *
	 * @param object $row Active route database row.
	 * @return array{outcome: string, message: string}
	 */
	public function classify( object $row ): array {
		$route_id = (int) ( $row->route_id ?? 0 );
		if ( $route_id <= 0 ) {
			return $this->result( SlugRouteActivationOutcome::INVALID_DATA, 'Route id is missing.' );
		}

		$language_id = (int) ( $row->language_id ?? 0 );
		if ( $language_id <= 0 ) {
			return $this->result( SlugRouteActivationOutcome::INVALID_DATA, 'Language id is missing.' );
		}

		$lang = $this->languages->find( $language_id );
		if ( null === $lang ) {
			return $this->result( SlugRouteActivationOutcome::INVALID_DATA, 'Language does not exist.' );
		}

		$source_type = (string) ( $row->source_type ?? '' );
		$source_id   = (int) ( $row->source_id ?? 0 );
		if ( Store::SOURCE_POST !== $source_type || $source_id <= 0 ) {
			return $this->result( SlugRouteActivationOutcome::INVALID_DATA, 'Unsupported source identity.' );
		}

		$post = get_post( $source_id );
		if ( ! $post instanceof WP_Post ) {
			return $this->result( SlugRouteActivationOutcome::INVALID_DATA, 'Source post does not exist.' );
		}

		try {
			$source_path    = $this->paths->canonicalize( (string) ( $row->source_path ?? '' ) );
			$localized_path = $this->paths->canonicalize( (string) ( $row->localized_path ?? '' ) );
		} catch ( InvalidPathException $e ) {
			return $this->result( SlugRouteActivationOutcome::INVALID_DATA, $e->getMessage() );
		}

		if ( ! $this->hash_matches_row( $row, 'source_path_hash', $source_path ) ) {
			return $this->result( SlugRouteActivationOutcome::INVALID_DATA, 'Source path hash mismatch.' );
		}

		if ( ! $this->hash_matches_row( $row, 'localized_path_hash', $localized_path ) ) {
			return $this->result( SlugRouteActivationOutcome::INVALID_DATA, 'Localized path hash mismatch.' );
		}

		$by_object = $this->routes->find_by_object( $source_type, $source_id, $language_id );
		if ( null === $by_object || (int) ( $by_object->route_id ?? 0 ) !== $route_id ) {
			return $this->result( SlugRouteActivationOutcome::CONFLICT, 'Object/language route identity mismatch.' );
		}

		$by_localized = $this->routes->find_by_localized_path( $language_id, $localized_path );
		if ( null !== $by_localized && (int) ( $by_localized->route_id ?? 0 ) !== $route_id ) {
			return $this->result( SlugRouteActivationOutcome::CONFLICT, 'Localized path owned by another route.' );
		}

		$by_source = $this->routes->find_by_source_path( $language_id, $source_path );
		if ( null !== $by_source && (int) ( $by_source->route_id ?? 0 ) !== $route_id ) {
			return $this->result( SlugRouteActivationOutcome::CONFLICT, 'Source path owned by another route.' );
		}

		$hist = $this->history->find_by_historical_path( $language_id, $localized_path );
		if ( null !== $hist ) {
			$same = (string) ( $hist->source_type ?? '' ) === $source_type
				&& (int) ( $hist->source_id ?? 0 ) === $source_id;
			if ( ! $same ) {
				return $this->result( SlugRouteActivationOutcome::CONFLICT, 'Localized path reserved in history by another object.' );
			}
		}

		$collision = $this->collisions->assert_available( $language_id, $localized_path, $source_type, $source_id );
		if ( $collision instanceof \WP_Error ) {
			return $this->result( SlugRouteActivationOutcome::CONFLICT, $collision->get_error_message() );
		}

		$capability = $this->capabilities->capability_for_post( $post );
		if ( null === $capability || ! $this->capabilities->supports_post( $post ) ) {
			return $this->result( SlugRouteActivationOutcome::SKIPPED_UNSUPPORTED, 'Routing capability is not MSEO.2 public-capable.' );
		}

		if ( Languages::STATUS_PUBLISHED !== (string) ( $lang->status ?? '' ) ) {
			return $this->result( SlugRouteActivationOutcome::SKIPPED_NOT_PUBLIC, 'Language is not published.' );
		}

		if ( ! in_array( (string) $post->post_status, array( 'publish', 'private' ), true ) ) {
			return $this->result( SlugRouteActivationOutcome::SKIPPED_NOT_PUBLIC, 'Source object is not public.' );
		}

		return $this->result( SlugRouteActivationOutcome::ADMITTED, '' );
	}

	/**
	 * Builds a classified activation result payload.
	 *
	 * @param string $outcome Outcome constant.
	 * @param string $message Human-readable diagnostic.
	 * @return array{outcome: string, message: string}
	 */
	private function result( string $outcome, string $message ): array {
		return array(
			'outcome' => $outcome,
			'message' => $message,
		);
	}

	/**
	 * Compares stored BINARY(32) hash with canonical path hash.
	 *
	 * @param object        $row    Route row.
	 * @param string        $column Hash column name.
	 * @param CanonicalPath $path   Canonical path.
	 */
	private function hash_matches_row( object $row, string $column, CanonicalPath $path ): bool {
		$stored = $row->{$column} ?? '';
		if ( ! is_string( $stored ) || 32 !== strlen( $stored ) ) {
			return false;
		}

		try {
			$expected = PathHash::from_canonical( $path )->hex();
		} catch ( InvalidPathException $e ) {
			return false;
		}

		return hash_equals( $expected, bin2hex( $stored ) );
	}
}
