<?php
/**
 * Global extension service bindings for public helpers.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

use AIMultilingual\Surface\AdmittedPostTypes;
use AIMultilingual\Surface\AdmittedTaxonomies;
use AIMultilingual\Surface\RequestLocalInvalidationCoordinator;
use AIMultilingual\Translation\Store;

/**
 * Holds request-scoped public extension services set during Plugin bootstrap.
 */
final class ExtensionServices {

	/**
	 * Request-local invalidation coordinator binding.
	 *
	 * @var RequestLocalInvalidationCoordinator|null
	 */
	private static ?RequestLocalInvalidationCoordinator $coordinator = null;

	/**
	 * Public visitor translation resolver binding.
	 *
	 * @var VisitorTranslationResolver|null
	 */
	private static ?VisitorTranslationResolver $resolver = null;

	/**
	 * Public extension registrar binding.
	 *
	 * @var ExtensionRegistrar|null
	 */
	private static ?ExtensionRegistrar $registrar = null;

	/**
	 * Extension diagnostics sink binding.
	 *
	 * @var ExtensionDiagnostics|null
	 */
	private static ?ExtensionDiagnostics $diagnostics = null;

	/**
	 * Binds request-scoped public extension services.
	 *
	 * @param RequestLocalInvalidationCoordinator $coordinator Invalidation coordinator.
	 * @param VisitorTranslationResolver          $resolver    Public resolver.
	 * @param ExtensionRegistrar                  $registrar   Public registrar.
	 * @param ExtensionDiagnostics                $diagnostics Diagnostics.
	 */
	public static function bind(
		RequestLocalInvalidationCoordinator $coordinator,
		VisitorTranslationResolver $resolver,
		ExtensionRegistrar $registrar,
		ExtensionDiagnostics $diagnostics,
	): void {
		self::$coordinator = $coordinator;
		self::$resolver    = $resolver;
		self::$registrar   = $registrar;
		self::$diagnostics = $diagnostics;
	}

	/**
	 * Returns the bound public visitor resolver, if any.
	 */
	public static function resolver(): ?VisitorTranslationResolver {
		return self::$resolver;
	}

	/**
	 * Returns the bound public extension registrar, if any.
	 */
	public static function registrar(): ?ExtensionRegistrar {
		return self::$registrar;
	}

	/**
	 * Returns the bound extension diagnostics sink, if any.
	 */
	public static function diagnostics(): ?ExtensionDiagnostics {
		return self::$diagnostics;
	}

	/**
	 * Marks a source dirty via request-local coordinator (public invalidation helper).
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 */
	public static function mark_source_dirty( string $source_type, int $source_id ): bool {
		if ( null === self::$coordinator ) {
			return false;
		}
		if ( ! in_array( $source_type, array( Store::SOURCE_POST, Store::SOURCE_TERM ), true ) ) {
			return false;
		}
		if ( $source_id <= 0 ) {
			return false;
		}
		if ( ! self::source_is_admitted( $source_type, $source_id ) ) {
			return false;
		}

		self::$coordinator->mark_dirty( $source_type, $source_id );
		return true;
	}

	/**
	 * Checks whether a source object is admitted for invalidation.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Source id.
	 */
	private static function source_is_admitted( string $source_type, int $source_id ): bool {
		if ( Store::SOURCE_POST === $source_type ) {
			$post = get_post( $source_id );
			if ( ! $post instanceof \WP_Post ) {
				return false;
			}
			return AdmittedPostTypes::admits( (string) $post->post_type, AdmittedPostTypes::CONTEXT_WORKSPACE )
				|| AdmittedPostTypes::admits( (string) $post->post_type, AdmittedPostTypes::CONTEXT_FRONTEND_OVERLAY );
		}

		$term = get_term( $source_id );
		if ( ! $term instanceof \WP_Term ) {
			return false;
		}
		return AdmittedTaxonomies::admits( (string) $term->taxonomy );
	}

	/**
	 * Clears bindings (tests).
	 */
	public static function reset_for_tests(): void {
		self::$coordinator = null;
		self::$resolver    = null;
		self::$registrar   = null;
		self::$diagnostics = null;
	}
}
