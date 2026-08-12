<?php
/**
 * Visitor-only overlays for admitted taxonomy term name/description (TSC.1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Translation\Store;
use AIMultilingual\Translation\TermTranslationResolver;

/**
 * Registers frozen visitor seams without mutating WP_Term objects.
 */
final class TermVisitorOverlay {

	/**
	 * Builds the overlay.
	 *
	 * @param TermTranslationResolver $resolver    Term resolver.
	 * @param int                     $language_id Active visitor language id.
	 */
	public function __construct(
		private TermTranslationResolver $resolver,
		private int $language_id
	) {
	}

	/**
	 * Registers supported hooks.
	 */
	public function register(): void {
		add_filter( 'single_term_title', array( $this, 'overlay_title' ), 20, 1 );
		add_filter( 'term_description', array( $this, 'overlay_description' ), 20, 2 );
	}

	/**
	 * Overlay admitted term archive titles.
	 *
	 * @param mixed $title Source title.
	 * @return mixed
	 */
	public function overlay_title( $title ) {
		if ( ! is_string( $title ) || $this->is_non_visitor_context() ) {
			return $title;
		}

		$term = $this->queried_admitted_term();
		if ( null === $term ) {
			return $title;
		}

		$resolved = $this->resolver->resolve( $term['term_id'], $term['taxonomy'], 'name', $this->language_id );
		if ( null === $resolved || ! Store::is_publicly_overlay_eligible( $resolved['row'] ) ) {
			return $title;
		}

		$text = IntegrationSecurity::sanitize_plain( (string) ( $resolved['row']->translated_text ?? '' ) );

		return '' !== $text ? $text : $title;
	}

	/**
	 * Overlay admitted term descriptions with hard visitor guards.
	 *
	 * @param mixed $description Source description.
	 * @param mixed $term_id     Term id.
	 * @return mixed
	 */
	public function overlay_description( $description, $term_id = 0 ) {
		if ( ! is_string( $description ) || $this->is_non_visitor_context() ) {
			return $description;
		}

		$term_id = (int) $term_id;
		$term    = $this->term_from_id( $term_id );
		if ( null === $term ) {
			$term = $this->queried_admitted_term();
		}
		if ( null === $term ) {
			return $description;
		}

		$resolved = $this->resolver->resolve( $term['term_id'], $term['taxonomy'], 'description', $this->language_id );
		if ( null === $resolved || ! Store::is_publicly_overlay_eligible( $resolved['row'] ) ) {
			return $description;
		}

		$text = (string) ( $resolved['row']->translated_text ?? '' );
		if ( '' === $text ) {
			return $description;
		}

		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $text ) : $text;
	}

	/**
	 * Hard visitor guards.
	 */
	private function is_non_visitor_context(): bool {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return true;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return true;
		}
		if ( function_exists( 'is_embed' ) && is_embed() ) {
			return true;
		}

		return false;
	}

	/**
	 * Queried admitted term.
	 *
	 * @return array{term_id: int, taxonomy: string}|null
	 */
	private function queried_admitted_term(): ?array {
		if ( ! function_exists( 'get_queried_object' ) ) {
			return null;
		}
		$queried = get_queried_object();
		if ( ! is_object( $queried ) || ! isset( $queried->term_id, $queried->taxonomy ) ) {
			return null;
		}
		$taxonomy = (string) $queried->taxonomy;
		$term_id  = (int) $queried->term_id;
		if ( $term_id <= 0 || ! AdmittedTaxonomies::admits( $taxonomy ) ) {
			return null;
		}

		return array(
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
		);
	}

	/**
	 * Admitted term from id.
	 *
	 * @param int $term_id Term id.
	 * @return array{term_id: int, taxonomy: string}|null
	 */
	private function term_from_id( int $term_id ): ?array {
		if ( $term_id <= 0 || ! function_exists( 'get_term' ) ) {
			return null;
		}
		$term = get_term( $term_id );
		if ( ! is_object( $term ) || ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) ) {
			return null;
		}
		$taxonomy = (string) ( $term->taxonomy ?? '' );
		if ( ! AdmittedTaxonomies::admits( $taxonomy ) ) {
			return null;
		}

		return array(
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
		);
	}
}
