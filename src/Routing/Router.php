<?php
/**
 * Language prefix routing.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Routing;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\LanguageResolver;
use AIMultilingual\Language\Languages;
use AIMultilingual\Plugin;

/**
 * Resolves the request language from the URL and keeps generated URLs in that
 * language.
 *
 * Inbound, the language prefix is removed from `REQUEST_URI` before WordPress
 * parses the request. Every rewrite rule the site already has — core's, and any
 * plugin's — then matches unchanged, with no per-language rule duplication and
 * nothing to flush (ADR-0002).
 *
 * Timing is not arbitrary. Resolution runs on `plugins_loaded` at priority 999:
 * late enough that every plugin has loaded, early enough that the `locale`
 * filter is in place before `load_default_textdomain()` runs, and before
 * `WP::parse_request()` reads `REQUEST_URI`.
 *
 * Outbound, `home_url` gains the prefix — but only after routing has finished.
 * `WP::parse_request()` calls `home_url()` itself and strips that path from the
 * request URI with an unanchored `|^path|i` pattern. With the filter live
 * during routing, a Swedish request for a page whose slug merely starts with
 * the language code would have those characters eaten: `/svenska-sidan/` would
 * be truncated to `enska-sidan/` and 404. Attaching on `parse_request` — which
 * fires at the very end of `WP::parse_request()` — avoids that entirely.
 *
 * Milestone 1 sets no cookie. The URL is the only authority, so front-end
 * responses carry no `Set-Cookie` header and stay cacheable at the edge
 * (ADR-0002). Cookie-based propagation arrives with the Store API work.
 */
final class Router {

	/**
	 * Language configuration.
	 *
	 * @var Languages
	 */
	private Languages $languages;

	/**
	 * Pure resolver.
	 *
	 * @var LanguageResolver
	 */
	private LanguageResolver $resolver;

	/**
	 * Request language state.
	 *
	 * @var LanguageContext
	 */
	private LanguageContext $context;

	/**
	 * Request URI as it arrived, before the prefix was removed.
	 *
	 * @var string
	 */
	private string $original_uri = '';

	/**
	 * Whether this request carried a language prefix.
	 *
	 * @var bool
	 */
	private bool $prefixed = false;

	/**
	 * Site home path, e.g. '' for a root install or '/blog' for a subdirectory.
	 *
	 * @var string|null
	 */
	private ?string $home_path = null;

	/**
	 * Builds the router.
	 *
	 * @param Languages        $languages Language configuration.
	 * @param LanguageResolver $resolver  Pure resolver.
	 * @param LanguageContext  $context   Request language state.
	 */
	public function __construct( Languages $languages, LanguageResolver $resolver, LanguageContext $context ) {
		$this->languages = $languages;
		$this->resolver  = $resolver;
		$this->context   = $context;
	}

	/**
	 * Registers routing hooks.
	 */
	public function register(): void {
		add_action( 'plugins_loaded', array( $this, 'resolve' ), 999 );
	}

	/**
	 * Resolves the request language and strips the prefix from REQUEST_URI.
	 */
	public function resolve(): void {
		$this->context->set_default( $this->languages->default() );

		if ( ! $this->should_route() ) {
			return;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$this->original_uri = (string) $uri;

		$path  = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
		$query = (string) wp_parse_url( (string) $uri, PHP_URL_QUERY );

		$resolved = $this->resolver->resolve(
			$this->strip_home_path( $path ),
			$this->languages->all(),
			current_user_can( Plugin::CAPABILITY )
		);

		$this->context->set_current( $resolved['language'] );

		if ( ! $resolved['prefixed'] ) {
			return;
		}

		$this->prefixed = true;

		$rebuilt = $this->home_path() . ltrim( $resolved['path'], '/' );
		$rebuilt = '/' . ltrim( $rebuilt, '/' );

		if ( '' !== $query ) {
			$rebuilt .= '?' . $query;
		}

		$_SERVER['REQUEST_URI'] = $rebuilt;

		add_filter( 'locale', array( $this, 'filter_locale' ) );
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ) );
		add_filter( 'redirect_canonical', array( $this, 'filter_redirect_canonical' ) );

		// Deferred deliberately: see the class docblock. Attaching this before
		// WP::parse_request() has read home_url() would corrupt any slug
		// beginning with the language code.
		add_action( 'parse_request', array( $this, 'enable_url_prefixing' ), 0 );
	}

	/**
	 * Starts prefixing generated URLs, once routing is finished.
	 */
	public function enable_url_prefixing(): void {
		add_filter( 'home_url', array( $this, 'filter_home_url' ) );
	}

	/**
	 * Serves the active language's locale.
	 *
	 * @param string $locale Locale WordPress determined.
	 */
	public function filter_locale( $locale ) {
		$language = $this->context->current();

		if ( null === $language ) {
			return $locale;
		}

		return (string) $language->locale;
	}

	/**
	 * Emits the active language's lang and dir attributes.
	 *
	 * @param string $output Attribute string WordPress built.
	 */
	public function filter_language_attributes( $output ) {
		$language = $this->context->current();

		if ( null === $language ) {
			return $output;
		}

		$attributes = array( 'lang="' . esc_attr( str_replace( '_', '-', (string) $language->locale ) ) . '"' );

		if ( 'rtl' === (string) $language->direction ) {
			$attributes[] = 'dir="rtl"';
		} else {
			$attributes[] = 'dir="ltr"';
		}

		return implode( ' ', $attributes );
	}

	/**
	 * Language-preserving redirect_canonical policy (A.SEOb).
	 *
	 * Prefixed requests must never be redirected to an unprefixed equivalent
	 * (that strips the language and can loop). Same-language corrections that
	 * retain the active language prefix remain allowed.
	 *
	 * @param string|false $redirect_url URL core wants to redirect to.
	 * @return string|false
	 */
	public function filter_redirect_canonical( $redirect_url ) {
		if ( ! $this->prefixed ) {
			return $redirect_url;
		}

		if ( ! is_string( $redirect_url ) || '' === $redirect_url ) {
			return false;
		}

		$language = $this->context->current();
		if ( null === $language || ! empty( $language->is_default ) ) {
			return false;
		}

		$code = (string) $language->code;
		$path = (string) wp_parse_url( $redirect_url, PHP_URL_PATH );
		$path = '/' . ltrim( $path, '/' );

		$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		$home = trim( $home, '/' );
		if ( '' !== $home && 0 === strpos( $path, '/' . $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) + 1 );
			$path = '/' . ltrim( (string) $path, '/' );
		} elseif ( '' !== $home && rtrim( $path, '/' ) === '/' . $home ) {
			$path = '/';
		}

		$prefix        = '/' . $code . '/';
		$path_noslash  = rtrim( $path, '/' );
		$language_root = '/' . $code;
		if ( 0 === strpos( $path, $prefix ) || $language_root === $path_noslash ) {
			return $redirect_url;
		}

		return false;
	}

	/**
	 * Injects the language prefix into generated home URLs.
	 *
	 * The path argument WordPress also passes is deliberately not accepted: the
	 * prefix is inserted by rewriting the finished URL, so the already-joined
	 * result is the only input needed.
	 *
	 * @param string $url Fully qualified URL.
	 */
	public function filter_home_url( $url ) {
		if ( ! is_string( $url ) || ! $this->context->is_translated() ) {
			return $url;
		}

		$language = $this->context->current();
		if ( null === $language ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['host'] ) ) {
			return $url;
		}

		$url_path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$code     = (string) $language->code;
		$home     = $this->home_path();

		// The REST API is not language-prefixed; it takes an explicit language
		// argument instead, so that its routes stay stable.
		$rest_prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
		if ( '' !== $rest_prefix && false !== strpos( $url_path, '/' . $rest_prefix ) ) {
			return $url;
		}

		if ( false !== strpos( $url_path, '/wp-admin' ) || false !== strpos( $url_path, '/wp-login.php' ) ) {
			return $url;
		}

		$relative = $this->strip_home_path( $url_path );

		// Already prefixed (a caller passed a path that includes it).
		if ( '/' . $code === $relative || 0 === strpos( $relative, '/' . $code . '/' ) ) {
			return $url;
		}

		$prefixed = $home . $code . ( '/' === $relative ? '/' : $relative );
		$prefixed = '/' . ltrim( $prefixed, '/' );

		$rebuilt = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '//' ) . $parts['host'];

		if ( isset( $parts['port'] ) ) {
			$rebuilt .= ':' . $parts['port'];
		}

		$rebuilt .= $prefixed;

		if ( isset( $parts['query'] ) ) {
			$rebuilt .= '?' . $parts['query'];
		}

		if ( isset( $parts['fragment'] ) ) {
			$rebuilt .= '#' . $parts['fragment'];
		}

		return $rebuilt;
	}

	/**
	 * The request URI exactly as it arrived, prefix included.
	 */
	public function original_uri(): string {
		return $this->original_uri;
	}

	/**
	 * Whether this request carried a language prefix.
	 */
	public function is_prefixed(): bool {
		return $this->prefixed;
	}

	// -- Internals --

	/**
	 * Whether language resolution applies to this request.
	 *
	 * Admin screens, the REST API, AJAX, cron, WP-CLI, XML-RPC and the login
	 * screen all keep the site's own language: they are not visitor-facing
	 * rendering, and giving them a front-end language context would leak it
	 * into places that must stay canonical.
	 */
	private function should_route(): bool {
		if ( is_admin() ) {
			return false;
		}

		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}

		if ( isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? (string) wp_unslash( $_SERVER['SCRIPT_NAME'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' !== $script && in_array( basename( $script ), array( 'wp-login.php', 'wp-register.php', 'xmlrpc.php' ), true ) ) {
			return false;
		}

		$uri         = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rest_prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
		if ( '' !== $rest_prefix && false !== strpos( $uri, '/' . $rest_prefix . '/' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Home path with leading and trailing slashes, e.g. '/' or '/blog/'.
	 */
	private function home_path(): string {
		if ( null === $this->home_path ) {
			// The raw option, not home_url(), so this stays correct even once
			// the prefixing filter is live.
			$path = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
			$path = trim( $path, '/' );

			$this->home_path = '' === $path ? '/' : '/' . $path . '/';
		}

		return $this->home_path;
	}

	/**
	 * Removes the site's home path from a path, leaving a site-relative path.
	 *
	 * @param string $path Absolute path.
	 */
	private function strip_home_path( string $path ): string {
		$home = $this->home_path();

		if ( '/' === $home ) {
			return '' === $path ? '/' : '/' . ltrim( $path, '/' );
		}

		$normalized = '/' . ltrim( $path, '/' );

		if ( 0 === strpos( $normalized, $home ) ) {
			$normalized = '/' . substr( $normalized, strlen( $home ) );
		} elseif ( rtrim( $home, '/' ) === rtrim( $normalized, '/' ) ) {
			$normalized = '/';
		}

		return '' === $normalized ? '/' : $normalized;
	}
}
