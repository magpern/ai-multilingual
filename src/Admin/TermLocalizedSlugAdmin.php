<?php
/**
 * Term edit-screen Localized URL slug operator panel (P0).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Admin;

use AIMultilingual\Language\Languages;
use AIMultilingual\Plugin;
use AIMultilingual\Surface\AdmittedTaxonomies;

/**
 * Enqueues REST-backed term slug UI on admitted taxonomy edit screens.
 */
final class TermLocalizedSlugAdmin {

	/**
	 * Constructs the term Localized URL admin panel.
	 *
	 * @param Languages $languages Languages.
	 */
	public function __construct(
		private Languages $languages
	) {
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_init', array( $this, 'register_term_fields' ) );
	}

	/**
	 * Attaches edit-form fields for currently admitted taxonomies.
	 */
	public function register_term_fields(): void {
		foreach ( AdmittedTaxonomies::all() as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_row' ), 20, 1 );
		}
	}

	/**
	 * Enqueues term slug admin script on term edit screens.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue( string $hook ): void {
		if ( 'term.php' !== $hook ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || empty( $screen->taxonomy ) || ! AdmittedTaxonomies::admits( (string) $screen->taxonomy ) ) {
			return;
		}

		if ( ! current_user_can( Plugin::CAP_TRANSLATE ) ) {
			return;
		}

		$handle = 'aiml-term-slug-admin';
		$url    = plugins_url( 'assets/term-slug-admin/term-slug-admin.js', AIML_PLUGIN_FILE );
		$ver    = defined( 'AIML_VERSION' ) ? AIML_VERSION : '1.6.0';

		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( $handle, $url, array( 'wp-api-fetch', 'wp-dom-ready' ), $ver, true );

		$langs = array();
		foreach ( $this->languages->all() as $row ) {
			$langs[] = array(
				'code'       => (string) ( $row->code ?? '' ),
				'name'       => (string) ( $row->name ?? '' ),
				'is_default' => ! empty( $row->is_default ),
			);
		}

		wp_localize_script(
			$handle,
			'aimlTermSlugAdmin',
			array(
				'restNamespace' => 'aiml/v1',
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'termId'        => isset( $_GET['tag_ID'] ) ? absint( wp_unslash( $_GET['tag_ID'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'taxonomy'      => (string) $screen->taxonomy,
				'languages'     => $langs,
				'i18n'          => array(
					'title'         => __( 'Localized URL slug', 'ai-multilingual' ),
					'language'      => __( 'Language', 'ai-multilingual' ),
					'candidate'     => __( 'Slug candidate', 'ai-multilingual' ),
					'origin'        => __( 'Origin', 'ai-multilingual' ),
					'effective'     => __( 'Effective localized path', 'ai-multilingual' ),
					'sync'          => __( 'Sync state', 'ai-multilingual' ),
					'generate'      => __( 'Generate', 'ai-multilingual' ),
					'save'          => __( 'Save candidate', 'ai-multilingual' ),
					'clear'         => __( 'Clear', 'ai-multilingual' ),
					'publish'       => __( 'Publish route', 'ai-multilingual' ),
					'refresh'       => __( 'Refresh', 'ai-multilingual' ),
					'collisionHelp' => __( 'That path collides with another route. Edit the candidate, clear it, or try a different slug, then publish again.', 'ai-multilingual' ),
					'loadError'     => __( 'Could not load localized slug state.', 'ai-multilingual' ),
					'blocked'       => __( 'Publication blocked', 'ai-multilingual' ),
				),
			)
		);
	}

	/**
	 * Renders a table row container for the script to hydrate.
	 *
	 * @param \WP_Term $term Term.
	 */
	public function render_row( $term ): void {
		if ( ! current_user_can( Plugin::CAP_TRANSLATE ) ) {
			return;
		}
		echo '<tr class="form-field aiml-term-slug-row"><th scope="row"><label>' . esc_html__( 'Localized URLs', 'ai-multilingual' ) . '</label></th>';
		echo '<td><div id="aiml-term-slug-panel" data-term-id="' . esc_attr( (string) (int) $term->term_id ) . '"></div>';
		echo '<p class="description">' . esc_html__( 'Prepare and publish a localized archive slug for this term without using REST tools.', 'ai-multilingual' ) . '</p></td></tr>';
	}
}
