<?php
/**
 * Global extension service bindings for public helpers.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

use AIMultilingual\Integration\IntegrationAdmission;
use AIMultilingual\Language\LanguageContext;
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
	 * Request language context binding (for aiml_visitor_language).
	 *
	 * @var LanguageContext|null
	 */
	private static ?LanguageContext $language_context = null;

	/**
	 * Whether visitor language API is past bootstrap (routing established).
	 *
	 * @var bool
	 */
	private static bool $visitor_language_ready = false;

	/**
	 * Binds request-scoped public extension services.
	 *
	 * @param RequestLocalInvalidationCoordinator $coordinator      Invalidation coordinator.
	 * @param VisitorTranslationResolver          $resolver         Public resolver.
	 * @param ExtensionRegistrar                  $registrar        Public registrar.
	 * @param ExtensionDiagnostics                $diagnostics      Diagnostics.
	 * @param LanguageContext|null                $language_context Optional language context for public helper.
	 */
	public static function bind(
		RequestLocalInvalidationCoordinator $coordinator,
		VisitorTranslationResolver $resolver,
		ExtensionRegistrar $registrar,
		ExtensionDiagnostics $diagnostics,
		?LanguageContext $language_context = null,
	): void {
		self::$coordinator      = $coordinator;
		self::$resolver         = $resolver;
		self::$registrar        = $registrar;
		self::$diagnostics      = $diagnostics;
		self::$language_context = $language_context;
		// Ready once Plugin::init completed bind — Router.resolve runs on plugins_loaded 999,
		// which is after Plugin::init (plugins_loaded 10). Consumers should still call after
		// request routing; mark ready on bind and return null when current() is unset.
		self::$visitor_language_ready = null !== $language_context;
	}

	/**
	 * Marks visitor language helper available (after routing). Prefer bind with LanguageContext.
	 */
	public static function mark_visitor_language_ready(): void {
		self::$visitor_language_ready = true;
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
	 * Public visitor language context from AIML URL/host resolution.
	 *
	 * @since 1.7.0
	 */
	public static function visitor_language(): ?VisitorLanguageContext {
		if ( ! self::$visitor_language_ready || null === self::$language_context ) {
			return null;
		}
		$current = self::$language_context->current();
		if ( null === $current ) {
			return null;
		}
		$code = (string) ( $current->code ?? '' );
		if ( '' === $code ) {
			return null;
		}
		return new VisitorLanguageContext(
			$code,
			self::$language_context->is_default()
		);
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
			$post_type = (string) $post->post_type;
			if ( AdmittedPostTypes::admits( $post_type, AdmittedPostTypes::CONTEXT_WORKSPACE )
				|| AdmittedPostTypes::admits( $post_type, AdmittedPostTypes::CONTEXT_FRONTEND_OVERLAY ) ) {
				return true;
			}
			$admission = IntegrationAdmission::registry();
			return null !== $admission && $admission->admits_post_type( $post_type );
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
		self::$coordinator            = null;
		self::$resolver               = null;
		self::$registrar              = null;
		self::$diagnostics            = null;
		self::$language_context       = null;
		self::$visitor_language_ready = false;
	}
}
