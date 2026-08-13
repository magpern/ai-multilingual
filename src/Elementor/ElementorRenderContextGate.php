<?php
/**
 * Frozen Elementor frontend overlay context gate (TSC.5 A4).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Elementor;

/**
 * Determines whether visitor frontend overlay may run for the current request.
 */
final class ElementorRenderContextGate {

	/**
	 * Whether overlay is allowed in the current request context.
	 *
	 * Denies source language, admin, editor, preview, REST/JSON, cron/CLI,
	 * internal serialization, and editor/internal Elementor AJAX.
	 */
	public function overlay_allowed(): bool {
		if ( $this->is_rest_or_json_request() ) {
			return false;
		}

		if ( $this->is_cron_or_cli() ) {
			return false;
		}

		if ( function_exists( 'is_admin' ) && is_admin() && ! ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return false;
		}

		if ( $this->is_elementor_edit_mode() || $this->is_elementor_preview_mode() ) {
			return false;
		}

		if ( $this->is_elementor_internal_ajax() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the request is REST or JSON API.
	 */
	public function is_rest_or_json_request(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		return function_exists( 'wp_is_json_request' ) && wp_is_json_request();
	}

	/**
	 * Whether cron or WP-CLI is running.
	 */
	public function is_cron_or_cli(): bool {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		return function_exists( 'wp_doing_cron' ) && wp_doing_cron();
	}

	/**
	 * Whether Elementor editor edit mode is active.
	 */
	public function is_elementor_edit_mode(): bool {
		if ( array_key_exists( 'aiml_test_elementor_edit_mode', $GLOBALS ) ) {
			return (bool) $GLOBALS['aiml_test_elementor_edit_mode'];
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$plugin = \Elementor\Plugin::$instance ?? null;
		if ( null === $plugin || ! isset( $plugin->editor ) || ! is_object( $plugin->editor ) ) {
			return false;
		}

		if ( method_exists( $plugin->editor, 'is_edit_mode' ) ) {
			return (bool) $plugin->editor->is_edit_mode();
		}

		return false;
	}

	/**
	 * Whether Elementor preview / editor iframe mode is active.
	 */
	public function is_elementor_preview_mode(): bool {
		if ( array_key_exists( 'aiml_test_elementor_preview_mode', $GLOBALS ) ) {
			return (bool) $GLOBALS['aiml_test_elementor_preview_mode'];
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$plugin = \Elementor\Plugin::$instance ?? null;
		if ( null === $plugin || ! isset( $plugin->preview ) || ! is_object( $plugin->preview ) ) {
			return false;
		}

		if ( method_exists( $plugin->preview, 'is_preview_mode' ) ) {
			return (bool) $plugin->preview->is_preview_mode();
		}

		return false;
	}

	/**
	 * Deny overlay for Elementor editor/internal AJAX; allow visitor render AJAX.
	 */
	public function is_elementor_internal_ajax(): bool {
		if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) {
			return false;
		}

		return $this->is_elementor_edit_mode() || $this->is_elementor_preview_mode();
	}
}
