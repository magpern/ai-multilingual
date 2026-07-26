<?php
/**
 * Language switcher.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Frontend;

use AIMultilingual\Language\LanguageContext;
use AIMultilingual\Language\Languages;
use AIMultilingual\Plugin;
use AIMultilingual\Settings;

/**
 * Renders links to the current page in each available language.
 *
 * One renderer, three entry points: the `[aiml_switcher]` shortcode, a
 * `wp_nav_menu_items` filter for themes that expect menu integration, and a
 * public `render()` a theme can call directly. A block wrapper is deliberately
 * left for a later milestone — it would be a thin shell around this same
 * method.
 *
 * URLs are built from the current request path with the prefix swapped, so a
 * visitor switching language lands on the same page rather than the home page.
 * Because the default language is never prefixed, switching to it removes the
 * prefix rather than replacing it.
 *
 * Preview languages appear only for viewers who may see them, matching the
 * routing rule; showing a link that 404s for everyone else would be worse than
 * showing nothing.
 */
final class Switcher {

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Language configuration.
	 *
	 * @var Languages
	 */
	private Languages $languages;

	/**
	 * Request language state.
	 *
	 * @var LanguageContext
	 */
	private LanguageContext $context;

	/**
	 * Builds the switcher renderer.
	 *
	 * @param Settings        $settings  Plugin settings.
	 * @param Languages       $languages Language configuration.
	 * @param LanguageContext $context   Request language state.
	 */
	public function __construct( Settings $settings, Languages $languages, LanguageContext $context ) {
		$this->settings  = $settings;
		$this->languages = $languages;
		$this->context   = $context;
	}

	/**
	 * Registers the shortcode and the optional menu integration.
	 */
	public function register(): void {
		add_shortcode( 'aiml_switcher', array( $this, 'shortcode' ) );
		add_filter( 'wp_nav_menu_items', array( $this, 'filter_nav_menu_items' ), 10, 2 );
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array( 'show' => 'auto' ),
			is_array( $atts ) ? $atts : array(),
			'aiml_switcher'
		);

		return $this->render( 'auto' === $atts['show'] ? null : 'native' === $atts['show'] );
	}

	/**
	 * Appends the switcher to a nav menu when the theme location opts in.
	 *
	 * @param string $items Menu item markup.
	 * @param object $args  Menu arguments.
	 */
	public function filter_nav_menu_items( $items, $args = null ) {
		if ( ! is_string( $items ) || ! is_object( $args ) ) {
			return $items;
		}

		/**
		 * Filters whether the language switcher is appended to a nav menu.
		 *
		 * Off by default: a theme decides which menu location, if any, should
		 * carry the switcher.
		 *
		 * @since 0.1.0
		 *
		 * @param bool   $show Whether to append the switcher. Default false.
		 * @param object $args wp_nav_menu() arguments.
		 */
		if ( ! apply_filters( 'aiml_switcher_in_menu', false, $args ) ) {
			return $items;
		}

		foreach ( $this->links() as $link ) {
			$items .= sprintf(
				'<li class="menu-item aiml-switcher__menu-item%1$s"><a href="%2$s" hreflang="%3$s" lang="%3$s">%4$s</a></li>',
				$link['current'] ? ' current-menu-item' : '',
				esc_url( $link['url'] ),
				esc_attr( $link['hreflang'] ),
				esc_html( $link['label'] )
			);
		}

		return $items;
	}

	/**
	 * Renders the switcher markup.
	 *
	 * @param bool|null $native Force native names on or off; null uses the setting.
	 */
	public function render( ?bool $native = null ): string {
		$links = $this->links( $native );

		if ( count( $links ) < 2 ) {
			return '';
		}

		$out = '<ul class="aiml-switcher">';

		foreach ( $links as $link ) {
			$out .= sprintf(
				'<li class="aiml-switcher__item%1$s"><a href="%2$s" hreflang="%3$s" lang="%3$s">%4$s</a></li>',
				$link['current'] ? ' is-current' : '',
				esc_url( $link['url'] ),
				esc_attr( $link['hreflang'] ),
				esc_html( $link['label'] )
			);
		}

		return $out . '</ul>';
	}

	/**
	 * Builds one link per available language.
	 *
	 * @param bool|null $native Force native names on or off; null uses the setting.
	 * @return array<int, array{code: string, label: string, url: string, hreflang: string, current: bool}>
	 */
	public function links( ?bool $native = null ): array {
		$use_native   = null === $native ? $this->settings->switcher_show_native_name() : $native;
		$hide_current = $this->settings->switcher_hide_current();

		$current    = $this->context->current();
		$current_id = null === $current ? 0 : (int) $current->language_id;

		$path  = $this->current_relative_path();
		$links = array();

		foreach ( $this->languages->routable( current_user_can( Plugin::CAPABILITY ) ) as $language ) {
			$is_current = (int) $language->language_id === $current_id;

			if ( $is_current && $hide_current ) {
				continue;
			}

			$label = $use_native && '' !== (string) $language->native_name
				? (string) $language->native_name
				: (string) $language->name;

			$links[] = array(
				'code'     => (string) $language->code,
				'label'    => $label,
				'url'      => $this->url_for( $language, $path ),
				'hreflang' => str_replace( '_', '-', (string) $language->locale ),
				'current'  => $is_current,
			);
		}

		return $links;
	}

	/**
	 * Builds the URL for the current page in a given language.
	 *
	 * @param object $language Target language.
	 * @param string $path     Site-relative path of the current request, unprefixed.
	 */
	private function url_for( object $language, string $path ): string {
		$path = '/' . ltrim( $path, '/' );

		if ( ! empty( $language->is_default ) ) {
			// home_url() is already prefixed for the active language when one is
			// set, so the default language's URL is built from the raw home.
			return $this->raw_home() . ltrim( $path, '/' );
		}

		return $this->raw_home() . (string) $language->code . $path;
	}

	/**
	 * Site home URL with a trailing slash, without any language prefix.
	 */
	private function raw_home(): string {
		return trailingslashit( (string) get_option( 'home' ) );
	}

	/**
	 * Current request path, relative to home and with any prefix removed.
	 *
	 * The router has already stripped the language prefix from REQUEST_URI by
	 * the time anything renders, so this is the unprefixed path.
	 */
	private function current_relative_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		$home = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		$home = trim( $home, '/' );

		if ( '' !== $home ) {
			$normalized = '/' . ltrim( $path, '/' );
			if ( 0 === strpos( $normalized, '/' . $home ) ) {
				$path = substr( $normalized, strlen( $home ) + 1 );
			}
		}

		$path = '/' . ltrim( (string) $path, '/' );

		return '' === $path ? '/' : $path;
	}
}
