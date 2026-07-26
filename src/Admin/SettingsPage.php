<?php
/**
 * Settings and language administration screens.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Admin;

use AIMultilingual\Language\Languages;
use AIMultilingual\Settings;
use WP_Error;

/**
 * The Languages and Settings screens.
 *
 * Conventional WordPress admin: the Settings API for the options form, and
 * `admin-post.php` handlers for language create, update and delete. Milestone 1
 * ships no REST API, because nothing here needs one — a REST layer would exist
 * only to be called by JavaScript this milestone does not ship (ADR-0002 scope
 * note). The segment editor in the next milestone is what actually requires
 * incremental per-segment saves, and REST arrives with it.
 *
 * Every write checks both a nonce and `manage_options`.
 */
final class SettingsPage {

	/**
	 * Top-level menu slug.
	 */
	public const MENU_SLUG = 'ai-multilingual';

	/**
	 * Settings submenu slug.
	 */
	public const SETTINGS_SLUG = 'aiml-settings';

	/**
	 * Settings API group.
	 */
	private const OPTION_GROUP = 'aiml_settings_group';

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
	 * Builds the settings and language screens.
	 *
	 * @param Settings  $settings  Plugin settings.
	 * @param Languages $languages Language configuration.
	 */
	public function __construct( Settings $settings, Languages $languages ) {
		$this->settings  = $settings;
		$this->languages = $languages;
	}

	/**
	 * Registers menus, settings and form handlers.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'admin_post_aiml_save_language', array( $this, 'handle_save_language' ) );
		add_action( 'admin_post_aiml_delete_language', array( $this, 'handle_delete_language' ) );
	}

	/**
	 * Adds the top-level menu and its Settings submenu.
	 */
	public function add_menus(): void {
		add_menu_page(
			__( 'AI Multilingual', 'ai-multilingual' ),
			__( 'Multilingual', 'ai-multilingual' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_languages' ),
			'dashicons-translation',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Languages', 'ai-multilingual' ),
			__( 'Languages', 'ai-multilingual' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_languages' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'ai-multilingual' ),
			__( 'Settings', 'ai-multilingual' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Registers the options form with the Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	// -- Screens --

	/**
	 * Renders the Languages screen.
	 */
	public function render_languages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage languages.', 'ai-multilingual' ) );
		}

		$languages = $this->languages->all();
		$editing   = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
		if ( isset( $_GET['language_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$editing = $this->languages->find( (int) $_GET['language_id'] );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Languages', 'ai-multilingual' ) . '</h1>';

		$this->render_notice();

		echo '<table class="widefat striped" style="margin-bottom:2em;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Code', 'ai-multilingual' ) . '</th>';
		echo '<th>' . esc_html__( 'Locale', 'ai-multilingual' ) . '</th>';
		echo '<th>' . esc_html__( 'Name', 'ai-multilingual' ) . '</th>';
		echo '<th>' . esc_html__( 'State', 'ai-multilingual' ) . '</th>';
		echo '<th>' . esc_html__( 'Default', 'ai-multilingual' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'ai-multilingual' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $languages as $language ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( (string) $language->code ) . '</code></td>';
			echo '<td>' . esc_html( (string) $language->locale ) . '</td>';
			echo '<td>' . esc_html( (string) $language->name ) . '</td>';
			echo '<td>' . esc_html( $this->status_label( (string) $language->status ) ) . '</td>';
			echo '<td>' . ( $language->is_default ? '&#10003;' : '' ) . '</td>';
			echo '<td>';

			printf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'page'        => self::MENU_SLUG,
							'language_id' => (int) $language->language_id,
						),
						admin_url( 'admin.php' )
					)
				),
				esc_html__( 'Edit', 'ai-multilingual' )
			);

			if ( ! $language->is_default ) {
				echo ' | ';
				printf(
					'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
					esc_url(
						wp_nonce_url(
							add_query_arg(
								array(
									'action'      => 'aiml_delete_language',
									'language_id' => (int) $language->language_id,
								),
								admin_url( 'admin-post.php' )
							),
							'aiml_delete_language_' . (int) $language->language_id
						)
					),
					esc_js( __( 'Delete this language? Its translations are kept.', 'ai-multilingual' ) ),
					esc_html__( 'Delete', 'ai-multilingual' )
				);
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';

		$this->render_language_form( $editing );

		echo '</div>';
	}

	/**
	 * Renders the Settings screen.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'ai-multilingual' ) );
		}

		$current = $this->settings->get();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'AI Multilingual Settings', 'ai-multilingual' ) . '</h1>';
		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';

		settings_fields( self::OPTION_GROUP );

		echo '<table class="form-table" role="presentation"><tbody>';

		$this->checkbox_row(
			'switcher_show_native_name',
			__( 'Native language names', 'ai-multilingual' ),
			__( 'Show each language in its own name (Svenska) rather than in English (Swedish).', 'ai-multilingual' ),
			(bool) $current['switcher_show_native_name']
		);

		$this->checkbox_row(
			'switcher_hide_current',
			__( 'Hide current language', 'ai-multilingual' ),
			__( 'Omit the language being viewed from the switcher.', 'ai-multilingual' ),
			(bool) $current['switcher_hide_current']
		);

		$this->checkbox_row(
			'remove_data_on_uninstall',
			__( 'Delete all data on uninstall', 'ai-multilingual' ),
			__( 'When the plugin is deleted, drop its tables and remove every translation. Off by default: deactivating or deleting the plugin keeps all translation work so a reinstall resumes where it left off.', 'ai-multilingual' ),
			(bool) $current['remove_data_on_uninstall']
		);

		echo '</tbody></table>';

		submit_button();

		echo '</form></div>';
	}

	// -- Form handlers --

	/**
	 * Creates or updates a language.
	 */
	public function handle_save_language(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage languages.', 'ai-multilingual' ) );
		}

		check_admin_referer( 'aiml_save_language' );

		$language_id = isset( $_POST['language_id'] ) ? (int) $_POST['language_id'] : 0;

		$data = array(
			'code'        => isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '',
			'locale'      => isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '',
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'native_name' => isset( $_POST['native_name'] ) ? sanitize_text_field( wp_unslash( $_POST['native_name'] ) ) : '',
			'direction'   => isset( $_POST['direction'] ) ? sanitize_text_field( wp_unslash( $_POST['direction'] ) ) : 'ltr',
			'status'      => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : Languages::STATUS_PREVIEW,
			'sort_order'  => isset( $_POST['sort_order'] ) ? (int) $_POST['sort_order'] : 0,
		);

		$result = $language_id > 0
			? $this->languages->update( $language_id, $data )
			: $this->languages->insert( $data );

		$this->redirect_with_result( $result, self::MENU_SLUG );
	}

	/**
	 * Deletes a language, keeping its translations.
	 */
	public function handle_delete_language(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage languages.', 'ai-multilingual' ) );
		}

		$language_id = isset( $_GET['language_id'] ) ? (int) $_GET['language_id'] : 0;

		check_admin_referer( 'aiml_delete_language_' . $language_id );

		$this->redirect_with_result( $this->languages->delete( $language_id ), self::MENU_SLUG );
	}

	// -- Rendering helpers --

	/**
	 * Renders the add/edit language form.
	 *
	 * @param object|null $editing Language being edited, or null to add.
	 */
	private function render_language_form( ?object $editing ): void {
		$is_edit    = null !== $editing;
		$is_default = $is_edit && ! empty( $editing->is_default );

		echo '<h2>' . esc_html( $is_edit ? __( 'Edit language', 'ai-multilingual' ) : __( 'Add a language', 'ai-multilingual' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="aiml_save_language" />';

		wp_nonce_field( 'aiml_save_language' );

		if ( $is_edit ) {
			echo '<input type="hidden" name="language_id" value="' . esc_attr( (string) (int) $editing->language_id ) . '" />';
		}

		echo '<table class="form-table" role="presentation"><tbody>';

		$this->text_row( 'code', __( 'URL code', 'ai-multilingual' ), $is_edit ? (string) $editing->code : '', __( 'Two lowercase letters, optionally with a region: sv, pt-br. Appears in the URL as /sv/.', 'ai-multilingual' ) );
		$this->text_row( 'locale', __( 'Locale', 'ai-multilingual' ), $is_edit ? (string) $editing->locale : '', __( 'WordPress locale, for example sv_SE.', 'ai-multilingual' ) );
		$this->text_row( 'name', __( 'Name', 'ai-multilingual' ), $is_edit ? (string) $editing->name : '', __( 'English name, for example Swedish.', 'ai-multilingual' ) );
		$this->text_row( 'native_name', __( 'Native name', 'ai-multilingual' ), $is_edit ? (string) $editing->native_name : '', __( 'The language in its own words, for example Svenska.', 'ai-multilingual' ) );

		// Direction.
		echo '<tr><th scope="row"><label for="aiml-direction">' . esc_html__( 'Text direction', 'ai-multilingual' ) . '</label></th><td>';
		echo '<select name="direction" id="aiml-direction">';
		foreach ( Languages::DIRECTIONS as $direction ) {
			printf(
				'<option value="%1$s"%2$s>%1$s</option>',
				esc_attr( $direction ),
				selected( $is_edit ? (string) $editing->direction : 'ltr', $direction, false )
			);
		}
		echo '</select></td></tr>';

		// State.
		echo '<tr><th scope="row"><label for="aiml-status">' . esc_html__( 'State', 'ai-multilingual' ) . '</label></th><td>';

		if ( $is_default ) {
			echo '<p><strong>' . esc_html__( 'Published', 'ai-multilingual' ) . '</strong><br />';
			echo '<span class="description">' . esc_html__( 'The default language is the source content, so it is always published and always unprefixed.', 'ai-multilingual' ) . '</span></p>';
		} else {
			echo '<select name="status" id="aiml-status">';
			foreach ( Languages::statuses() as $status ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $status ),
					selected( $is_edit ? (string) $editing->status : Languages::STATUS_PREVIEW, $status, false ),
					esc_html( $this->status_label( $status ) )
				);
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Preview: visible only to users who can translate. Published: visible to everyone. Disabled: not routed at all. A disabled language returns through preview before it can be published again.', 'ai-multilingual' ) . '</p>';
		}

		echo '</td></tr>';

		$this->text_row( 'sort_order', __( 'Sort order', 'ai-multilingual' ), $is_edit ? (string) (int) $editing->sort_order : '0', __( 'Order in the language switcher.', 'ai-multilingual' ) );

		echo '</tbody></table>';

		submit_button( $is_edit ? __( 'Save language', 'ai-multilingual' ) : __( 'Add language', 'ai-multilingual' ) );

		echo '</form>';
	}

	/**
	 * Renders a labelled text input row.
	 *
	 * @param string $name        Field name.
	 * @param string $label       Field label.
	 * @param string $value       Current value.
	 * @param string $description Help text.
	 */
	private function text_row( string $name, string $label, string $value, string $description ): void {
		printf(
			'<tr><th scope="row"><label for="aiml-%1$s">%2$s</label></th><td>'
			. '<input type="text" class="regular-text" id="aiml-%1$s" name="%1$s" value="%3$s" />'
			. '<p class="description">%4$s</p></td></tr>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $value ),
			esc_html( $description )
		);
	}

	/**
	 * Renders a settings checkbox row.
	 *
	 * @param string $key         Settings key.
	 * @param string $label       Field label.
	 * @param string $description Help text.
	 * @param bool   $checked     Current value.
	 */
	private function checkbox_row( string $key, string $label, string $description, bool $checked ): void {
		printf(
			'<tr><th scope="row">%1$s</th><td><label>'
			. '<input type="checkbox" name="%2$s[%3$s]" value="1"%4$s /> %5$s'
			. '</label></td></tr>',
			esc_html( $label ),
			esc_attr( Settings::OPTION ),
			esc_attr( $key ),
			checked( $checked, true, false ),
			esc_html( $description )
		);
	}

	/**
	 * Human-readable label for a language state.
	 *
	 * @param string $status Status value.
	 */
	private function status_label( string $status ): string {
		switch ( $status ) {
			case Languages::STATUS_PUBLISHED:
				return __( 'Published', 'ai-multilingual' );

			case Languages::STATUS_DISABLED:
				return __( 'Disabled', 'ai-multilingual' );

			case Languages::STATUS_PREVIEW:
			default:
				return __( 'Preview', 'ai-multilingual' );
		}
	}

	/**
	 * Redirects back to a screen carrying the outcome of a write.
	 *
	 * @param true|int|WP_Error $result Outcome from the languages store.
	 * @param string            $page   Admin page slug to return to.
	 */
	private function redirect_with_result( $result, string $page ): void {
		$args = array( 'page' => $page );

		if ( $result instanceof WP_Error ) {
			$args['aiml_error'] = rawurlencode( $result->get_error_message() );
		} else {
			$args['aiml_updated'] = '1';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Prints the success or error notice carried on the query string.
	 */
	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only feedback.
		if ( isset( $_GET['aiml_error'] ) ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['aiml_error'] ) ) ) )
			);

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['aiml_updated'] ) ) {
			printf(
				'<div class="notice notice-success"><p>%s</p></div>',
				esc_html__( 'Saved.', 'ai-multilingual' )
			);
		}
	}
}
