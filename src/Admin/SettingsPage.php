<?php
/**
 * Settings and language administration screens.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Admin;

use AIMultilingual\Block\FeatureFlags;
use AIMultilingual\Language\Languages;
use AIMultilingual\Routing\LocalizedUrlsActivationService;
use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\CredentialVault;
use AIMultilingual\Translation\Publication\PublicationMode;
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
	 * Transient key prefix for Strategy F dependency rejection notices.
	 */
	public const FLAG_NOTICE_TRANSIENT = 'aiml_strategy_f_flag_combo_rejected';

	/**
	 * Admin notice identifier for rejected flag combinations.
	 */
	public const FLAG_NOTICE_ID = 'aiml_strategy_f_flag_combo_rejected';

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
	 * Credential vault for AI API keys.
	 *
	 * @var CredentialVault
	 */
	private CredentialVault $vault;

	/**
	 * Localized URL activation control.
	 *
	 * @var LocalizedUrlsActivationService|null
	 */
	private ?LocalizedUrlsActivationService $localized_urls;

	/**
	 * Builds the settings and language screens.
	 *
	 * @param Settings                            $settings       Plugin settings.
	 * @param Languages                           $languages      Language configuration.
	 * @param CredentialVault|null                $vault          Credential vault.
	 * @param LocalizedUrlsActivationService|null $localized_urls Localized URL activation.
	 */
	public function __construct(
		Settings $settings,
		Languages $languages,
		?CredentialVault $vault = null,
		?LocalizedUrlsActivationService $localized_urls = null
	) {
		$this->settings       = $settings;
		$this->languages      = $languages;
		$this->vault          = $vault ?? new CredentialVault();
		$this->localized_urls = $localized_urls;
	}

	/**
	 * Registers menus, settings and form handlers.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'render_strategy_f_admin_notices' ) );

		add_action( 'admin_post_aiml_save_language', array( $this, 'handle_save_language' ) );
		add_action( 'admin_post_aiml_delete_language', array( $this, 'handle_delete_language' ) );
		add_action( 'admin_post_aiml_localized_urls_enable', array( $this, 'handle_localized_urls_enable' ) );
		add_action( 'admin_post_aiml_localized_urls_disable', array( $this, 'handle_localized_urls_disable' ) );
		add_action( 'admin_post_aiml_localized_urls_retry', array( $this, 'handle_localized_urls_retry' ) );
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
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Sanitizes settings submitted from the admin form and records Strategy F audit events.
	 *
	 * @param mixed $input Raw option value from the Settings API.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ): array {
		$raw      = is_array( $input ) ? $input : array();
		$previous = Settings::sanitize( get_option( Settings::OPTION, Settings::defaults() ) );

		// Map plaintext API key field into encrypted storage before sanitize.
		if ( isset( $raw['ai_api_key'] ) ) {
			$submitted = trim( (string) wp_unslash( $raw['ai_api_key'] ) );
			if ( '********' === $submitted ) {
				$raw['ai_api_key_encrypted'] = (string) ( $previous['ai_api_key_encrypted'] ?? '' );
			} elseif ( '' === $submitted ) {
				$raw['ai_api_key_encrypted'] = '';
			} else {
				$raw['ai_api_key_encrypted'] = $this->vault->encrypt( $submitted );
			}
			unset( $raw['ai_api_key'] );
		} elseif ( ! isset( $raw['ai_api_key_encrypted'] ) ) {
			$raw['ai_api_key_encrypted'] = (string) ( $previous['ai_api_key_encrypted'] ?? '' );
		}

		$clean = Settings::sanitize( $raw );

		if ( ! is_array( $input ) ) {
			return $clean;
		}

		Settings::emit_flag_change_audit( $previous, $clean, 'admin_settings' );

		$this->handle_strategy_f_submission( $raw, $clean, $previous );

		// Localized URL state machine / capability admission are machine-owned.
		$clean['localized_urls_state']                     = $previous['localized_urls_state'] ?? 'off';
		$clean['localized_urls_activation_checkpoint']     = $previous['localized_urls_activation_checkpoint'] ?? null;
		$clean['localized_urls_activation_error']          = $previous['localized_urls_activation_error'] ?? '';
		$clean['localized_urls_verified_capability_epoch'] = $previous['localized_urls_verified_capability_epoch'] ?? 0;
		$clean['localized_urls_admitted_capabilities']     = $previous['localized_urls_admitted_capabilities'] ?? array();
		$clean['localized_urls_capability_checkpoint']     = $previous['localized_urls_capability_checkpoint'] ?? null;
		$clean['localized_urls_woo_product_fingerprint']   = $previous['localized_urls_woo_product_fingerprint'] ?? '';

		return $clean;
	}

	/**
	 * Records rejected Strategy F flag combinations and queues the admin notice.
	 *
	 * @param array<string, mixed> $raw      Raw submitted settings.
	 * @param array<string, mixed> $clean    Sanitized settings.
	 * @param array<string, mixed> $previous Sanitized settings before save.
	 */
	private function handle_strategy_f_submission( array $raw, array $clean, array $previous ): void {
		if ( ! FeatureFlags::production_flags_submission_changed( $raw, $previous ) ) {
			$this->clear_flag_rejection_notice();

			return;
		}

		$payload = FeatureFlags::flag_rejection_payload( $raw, $clean );
		if ( null === $payload ) {
			$this->clear_flag_rejection_notice();

			return;
		}

		Settings::emit_flag_combo_rejected( $payload );
		$this->queue_flag_rejection_notice( $payload );
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
		$this->render_notice();
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

		$this->checkbox_row(
			'qa_block_on_error',
			__( 'Block saves on QA errors', 'ai-multilingual' ),
			__( 'When enabled, workspace saves that fail structural quality checks (placeholders, empty translation, HTML tags) are rejected. Warnings never block saves.', 'ai-multilingual' ),
			(bool) ( $current['qa_block_on_error'] ?? true )
		);

		echo '</tbody></table>';

		$this->render_strategy_f_settings( $current );

		if ( null !== $this->localized_urls ) {
			$this->render_localized_urls_settings( $current );
		}

		$this->render_ai_provider_settings( $current );

		submit_button();

		echo '</form></div>';
	}

	/**
	 * Renders AI provider configuration (server-side secrets only).
	 *
	 * @param array<string, mixed> $current Current settings.
	 */
	private function render_ai_provider_settings( array $current ): void {
		$has_key = '' !== (string) ( $current['ai_api_key_encrypted'] ?? '' );

		echo '<h2>' . esc_html__( 'Automatic translation', 'ai-multilingual' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Configure a provider for automatic translation and AI suggestions. API keys are encrypted at rest and never sent to the browser or translator workspace JavaScript.', 'ai-multilingual' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->checkbox_row(
			'ai_enabled',
			__( 'Enable AI provider', 'ai-multilingual' ),
			__( 'When enabled and a provider is selected with a valid API key, workspace auto-translate and AI suggest use that provider.', 'ai-multilingual' ),
			(bool) ( $current['ai_enabled'] ?? false )
		);

		$provider = (string) ( $current['ai_provider'] ?? '' );
		echo '<tr><th scope="row"><label for="aiml_ai_provider">' . esc_html__( 'Provider', 'ai-multilingual' ) . '</label></th><td>';
		echo '<select name="' . esc_attr( Settings::OPTION . '[ai_provider]' ) . '" id="aiml_ai_provider">';
		echo '<option value=""' . selected( $provider, '', false ) . '>' . esc_html__( 'None', 'ai-multilingual' ) . '</option>';
		echo '<option value="openai"' . selected( $provider, 'openai', false ) . '>' . esc_html__( 'OpenAI', 'ai-multilingual' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Additional providers can be registered without changing workspace services.', 'ai-multilingual' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="aiml_ai_model">' . esc_html__( 'Model', 'ai-multilingual' ) . '</label></th><td>';
		echo '<input name="' . esc_attr( Settings::OPTION . '[ai_model]' ) . '" type="text" id="aiml_ai_model" value="' . esc_attr( (string) ( $current['ai_model'] ?? '' ) ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Optional model id (for example gpt-4o-mini). Leave blank for the provider default.', 'ai-multilingual' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="aiml_ai_api_key">' . esc_html__( 'API key', 'ai-multilingual' ) . '</label></th><td>';
		echo '<input name="' . esc_attr( Settings::OPTION . '[ai_api_key]' ) . '" type="password" id="aiml_ai_api_key" value="' . esc_attr( $has_key ? '********' : '' ) . '" class="regular-text" autocomplete="new-password" />';
		echo '<p class="description">' . esc_html__( 'Leave as dots to keep the existing key. Clear and save to remove. Never exposed to JavaScript.', 'ai-multilingual' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
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

	/**
	 * Enables localized public URLs (activating → verification job).
	 */
	public function handle_localized_urls_enable(): void {
		if ( null === $this->localized_urls || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'ai-multilingual' ) );
		}

		check_admin_referer( 'aiml_localized_urls_enable' );

		$this->localized_urls->request_enable();
		$this->redirect_localized_urls_settings();
	}

	/**
	 * Disables localized public URLs.
	 */
	public function handle_localized_urls_disable(): void {
		if ( null === $this->localized_urls || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'ai-multilingual' ) );
		}

		check_admin_referer( 'aiml_localized_urls_disable' );

		$this->localized_urls->request_disable();
		$this->redirect_localized_urls_settings();
	}

	/**
	 * Retries activation after failure.
	 */
	public function handle_localized_urls_retry(): void {
		if ( null === $this->localized_urls || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'ai-multilingual' ) );
		}

		check_admin_referer( 'aiml_localized_urls_retry' );

		$this->localized_urls->request_retry();
		$this->redirect_localized_urls_settings();
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
	 * Renders the Strategy F production flag controls and settings-state diagnostics.
	 *
	 * @param array<string, mixed> $current Saved settings.
	 */
	private function render_strategy_f_settings( array $current ): void {
		$effective = FeatureFlags::validate_dependencies( $current );
		$valid     = ! FeatureFlags::has_prohibited_combination( $current );

		echo '<h2>' . esc_html__( 'Strategy F — Gutenberg block translation', 'ai-multilingual' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Pre-rollout controls for persistent block identity and gated frontend rendering. All flags default to off.', 'ai-multilingual' ) . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';

		$this->strategy_f_checkbox_row(
			FeatureFlags::REGISTRATION,
			__( 'Attribute registration', 'ai-multilingual' ),
			__( 'Registers aimlBlockId in block metadata so Gutenberg preserves UUIDs on edit. Required before any other Strategy F behavior.', 'ai-multilingual' ),
			(bool) $current[ FeatureFlags::REGISTRATION ],
			true,
			''
		);

		$this->strategy_f_checkbox_row(
			FeatureFlags::INJECTION,
			__( 'UUID injection', 'ai-multilingual' ),
			__( 'Assigns and repairs block UUIDs on canonical post saves.', 'ai-multilingual' ),
			(bool) $current[ FeatureFlags::INJECTION ],
			! empty( $effective[ FeatureFlags::REGISTRATION ] ),
			FeatureFlags::REGISTRATION
		);

		$this->strategy_f_checkbox_row(
			FeatureFlags::EXTRACTION,
			__( 'Block extraction', 'ai-multilingual' ),
			__( 'Extracts block segments and reconciles the translation store on canonical saves.', 'ai-multilingual' ),
			(bool) $current[ FeatureFlags::EXTRACTION ],
			! empty( $effective[ FeatureFlags::REGISTRATION ] ) && ! empty( $effective[ FeatureFlags::INJECTION ] ),
			FeatureFlags::INJECTION
		);

		$this->strategy_f_checkbox_row(
			FeatureFlags::FRONTEND_RENDER,
			__( 'Frontend rendering', 'ai-multilingual' ),
			__( 'Overlays translated block content on public pages. Disabling this flag is the immediate kill switch.', 'ai-multilingual' ),
			(bool) $current[ FeatureFlags::FRONTEND_RENDER ],
			! empty( $effective[ FeatureFlags::REGISTRATION ] )
				&& ! empty( $effective[ FeatureFlags::INJECTION ] )
				&& ! empty( $effective[ FeatureFlags::EXTRACTION ] ),
			FeatureFlags::EXTRACTION,
			true
		);

		echo '</tbody></table>';

		$this->render_strategy_f_diagnostics( $current, $effective, $valid );

		echo '<h2>' . esc_html__( 'Segment publication', 'ai-multilingual' ) . '</h2>';
		echo '<p class="description">' . esc_html__(
			'Controls whether published status gates frontend overlay eligibility, and whether successful auto-translate may attempt auto-publication.',
			'ai-multilingual'
		) . '</p>';

		echo '<table class="form-table" role="presentation"><tbody>';

		$this->checkbox_row(
			'segment_publication_gate_enabled',
			__( 'Segment publication gate', 'ai-multilingual' ),
			__( 'When enabled, only segments with publish_status published are overlay-eligible. Enabling does not delete data or mass-unpublish.', 'ai-multilingual' ),
			! empty( $current['segment_publication_gate_enabled'] )
		);

		$mode = (string) ( $current['auto_publication_mode'] ?? PublicationMode::MANUAL );
		if ( ! PublicationMode::is_valid( $mode ) ) {
			$mode = PublicationMode::MANUAL;
		}

		echo '<tr><th scope="row"><label for="aiml-auto_publication_mode">' . esc_html__( 'Auto-publication mode', 'ai-multilingual' ) . '</label></th><td>';
		printf(
			'<select id="aiml-auto_publication_mode" name="%1$s[auto_publication_mode]" data-aiml-publication-mode-confirm="1">',
			esc_attr( Settings::OPTION )
		);
		$mode_labels = array(
			PublicationMode::MANUAL          => __( 'Manual', 'ai-multilingual' ),
			PublicationMode::APPROVED_ONLY   => __( 'Approved only', 'ai-multilingual' ),
			PublicationMode::CONTROLLED_AUTO => __( 'Controlled auto', 'ai-multilingual' ),
		);
		foreach ( $mode_labels as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $mode, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__(
			'Affects future maybe_auto_publish after auto-translate only. Changing mode does not reconcile existing rows; manual does not mass-unpublish.',
			'ai-multilingual'
		) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';

		$this->render_publication_diagnostics( $current );
		$this->render_publication_confirmation_script();

		$this->render_strategy_f_confirmation_script();
	}

	/**
	 * Renders localized URL activation controls (MSEO.2.5).
	 *
	 * @param array<string, mixed> $current Saved settings.
	 */
	private function render_localized_urls_settings( array $current ): void {
		$state = (string) ( $current['localized_urls_state'] ?? LocalizedUrlsActivationService::STATE_OFF );
		$error = (string) ( $current['localized_urls_activation_error'] ?? '' );

		echo '<h2>' . esc_html__( 'Localized URLs', 'ai-multilingual' ) . '</h2>';
		echo '<p class="description">' . esc_html__(
			'Enabling verifies prepared active routes in the background before public localized URLs are advertised. Disabling is immediate.',
			'ai-multilingual'
		) . '</p>';

		echo '<div class="aiml-localized-urls-diagnostics" style="margin:1.5em 0;padding:1em;border:1px solid #ccd0d4;background:#fff;">';
		echo '<p><strong>' . esc_html__( 'State:', 'ai-multilingual' ) . '</strong> ';
		echo esc_html( $this->localized_urls_state_label( $state ) ) . '</p>';

		if ( LocalizedUrlsActivationService::STATE_FAILED === $state && '' !== $error ) {
			printf(
				'<p><strong>%1$s</strong> %2$s</p>',
				esc_html__( 'Error:', 'ai-multilingual' ),
				esc_html( $error )
			);
		}

		echo '<p class="description">' . esc_html__(
			'While Activating, inbound localized paths are recognized but visitors are redirected to source-slug URLs until verification completes.',
			'ai-multilingual'
		) . '</p>';
		echo '</div>';

		if ( in_array( $state, array( LocalizedUrlsActivationService::STATE_OFF, LocalizedUrlsActivationService::STATE_FAILED ), true ) ) {
			$enable_label  = LocalizedUrlsActivationService::STATE_FAILED === $state
				? __( 'Retry activation', 'ai-multilingual' )
				: __( 'Enable localized URLs', 'ai-multilingual' );
			$enable_action = LocalizedUrlsActivationService::STATE_FAILED === $state
				? 'aiml_localized_urls_retry'
				: 'aiml_localized_urls_enable';
			$enable_nonce  = LocalizedUrlsActivationService::STATE_FAILED === $state
				? 'aiml_localized_urls_retry'
				: 'aiml_localized_urls_enable';

			printf(
				'<p><a class="button button-primary" href="%1$s" data-aiml-localized-urls-enable="1">%2$s</a></p>',
				esc_url(
					wp_nonce_url(
						add_query_arg( 'action', $enable_action, admin_url( 'admin-post.php' ) ),
						$enable_nonce
					)
				),
				esc_html( $enable_label )
			);
		}

		if ( in_array(
			$state,
			array(
				LocalizedUrlsActivationService::STATE_ON,
				LocalizedUrlsActivationService::STATE_ACTIVATING,
				LocalizedUrlsActivationService::STATE_FAILED,
			),
			true
		) ) {
			printf(
				'<p><a class="button" href="%1$s">%2$s</a></p>',
				esc_url(
					wp_nonce_url(
						add_query_arg( 'action', 'aiml_localized_urls_disable', admin_url( 'admin-post.php' ) ),
						'aiml_localized_urls_disable'
					)
				),
				esc_html__( 'Disable localized URLs', 'ai-multilingual' )
			);
		}

		$this->render_localized_urls_confirmation_script();

		echo '<hr />';
	}

	/**
	 * Human-readable localized URL activation state.
	 *
	 * @param string $state Persisted state.
	 */
	private function localized_urls_state_label( string $state ): string {
		switch ( $state ) {
			case LocalizedUrlsActivationService::STATE_ON:
				return __( 'On', 'ai-multilingual' );
			case LocalizedUrlsActivationService::STATE_ACTIVATING:
				return __( 'Activating', 'ai-multilingual' );
			case LocalizedUrlsActivationService::STATE_FAILED:
				return __( 'Failed', 'ai-multilingual' );
			default:
				return __( 'Off', 'ai-multilingual' );
		}
	}

	/**
	 * Confirmation prompt before enabling localized URLs.
	 */
	private function render_localized_urls_confirmation_script(): void {
		$message = __(
			'Enabling localized URLs starts a background verification of all prepared active routes. Visitors may see source-slug URLs until activation completes. Continue?',
			'ai-multilingual'
		);

		printf(
			'<script>
			(function () {
				var link = document.querySelector("[data-aiml-localized-urls-enable]");
				if (!link) { return; }
				link.addEventListener("click", function (event) {
					if (!window.confirm(%1$s)) {
						event.preventDefault();
					}
				});
			}());
			</script>',
			wp_json_encode( $message )
		);
	}

	/**
	 * Redirects back to the settings screen after localized URL actions.
	 */
	private function redirect_localized_urls_settings(): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::SETTINGS_SLUG,
					'aiml_updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Renders one Strategy F flag checkbox with dependency metadata.
	 *
	 * @param string $key                 Settings flag key.
	 * @param string $label               Field label.
	 * @param string $description         Help text.
	 * @param bool   $checked             Saved state.
	 * @param bool   $enabled             Whether the control is interactive.
	 * @param string $missing_prerequisite Prerequisite flag key when disabled.
	 * @param bool   $requires_confirm    Whether enabling requires explicit confirmation.
	 */
	private function strategy_f_checkbox_row(
		string $key,
		string $label,
		string $description,
		bool $checked,
		bool $enabled,
		string $missing_prerequisite = '',
		bool $requires_confirm = false
	): void {
		$input_id = 'aiml-' . $key;
		$deps     = FeatureFlags::prerequisite_label( $key );

		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo '<label for="' . esc_attr( $input_id ) . '">';
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1" class="aiml-strategy-f-flag"%4$s%5$s%6$s />',
			esc_attr( $input_id ),
			esc_attr( Settings::OPTION ),
			esc_attr( $key ),
			checked( $checked, true, false ),
			$enabled ? '' : ' disabled="disabled"',
			$requires_confirm ? ' data-aiml-requires-confirm="1"' : ''
		);
		echo ' ' . esc_html( $description ) . '</label>';

		if ( '' !== $deps ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: prerequisite flag key(s) */
					__( 'Requires: %s', 'ai-multilingual' ),
					$deps
				)
			) . '</p>';
		}

		if ( ! $enabled && '' !== $missing_prerequisite ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: prerequisite flag key */
					__( 'Enable %s first.', 'ai-multilingual' ),
					$missing_prerequisite
				)
			) . '</p>';
		}

		echo '</td></tr>';
	}

	/**
	 * Renders saved/effective Strategy F flag state without health queries.
	 *
	 * @param array<string, mixed> $saved     Persisted settings.
	 * @param array<string, mixed> $effective Dependency-validated settings.
	 * @param bool                 $valid     Whether the saved combination is valid.
	 */
	private function render_strategy_f_diagnostics( array $saved, array $effective, bool $valid ): void {
		echo '<div class="aiml-strategy-f-diagnostics" style="margin:1.5em 0;padding:1em;border:1px solid #ccd0d4;background:#fff;">';
		echo '<h3 style="margin-top:0;">' . esc_html__( 'Strategy F diagnostics (settings state only)', 'ai-multilingual' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Flag', 'ai-multilingual' ) . '</th>';
		echo '<th>' . esc_html__( 'Saved', 'ai-multilingual' ) . '</th>';
		echo '<th>' . esc_html__( 'Effective', 'ai-multilingual' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( FeatureFlags::PRODUCTION_FLAGS as $flag ) {
			printf(
				'<tr><td><code>%1$s</code></td><td>%2$s</td><td>%3$s</td></tr>',
				esc_html( $flag ),
				esc_html( $this->flag_state_label( ! empty( $saved[ $flag ] ) ) ),
				esc_html( $this->flag_state_label( ! empty( $effective[ $flag ] ) ) )
			);
		}

		echo '</tbody></table>';
		printf(
			'<p><strong>%1$s</strong> %2$s<br /><strong>%3$s</strong> %4$s</p>',
			esc_html__( 'Combination valid:', 'ai-multilingual' ),
			esc_html( $valid ? __( 'Yes', 'ai-multilingual' ) : __( 'No', 'ai-multilingual' ) ),
			esc_html__( 'Frontend rendering active:', 'ai-multilingual' ),
			esc_html( $this->flag_state_label( ! empty( $effective[ FeatureFlags::FRONTEND_RENDER ] ) ) )
		);
		echo '</div>';
	}

	/**
	 * Renders TI.7 publication gate/mode Saved vs Effective diagnostics.
	 *
	 * @param array<string, mixed> $saved Persisted settings.
	 */
	private function render_publication_diagnostics( array $saved ): void {
		$sanitized      = Settings::sanitize( $saved );
		$gate_saved     = ! empty( $saved['segment_publication_gate_enabled'] );
		$gate_effective = ! empty( $sanitized['segment_publication_gate_enabled'] );
		$mode_saved     = (string) ( $saved['auto_publication_mode'] ?? PublicationMode::MANUAL );
		$mode_effective = (string) ( $sanitized['auto_publication_mode'] ?? PublicationMode::MANUAL );
		if ( ! PublicationMode::is_valid( $mode_effective ) ) {
			$mode_effective = PublicationMode::MANUAL;
		}

		echo '<div class="aiml-publication-diagnostics" style="margin:1.5em 0;padding:1em;border:1px solid #ccd0d4;background:#fff;">';
		echo '<h3 style="margin-top:0;">' . esc_html__( 'Publication diagnostics (settings state only)', 'ai-multilingual' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Setting', 'ai-multilingual' ) . '</th>';
		echo '<th>' . esc_html__( 'Saved', 'ai-multilingual' ) . '</th>';
		echo '<th>' . esc_html__( 'Effective', 'ai-multilingual' ) . '</th>';
		echo '</tr></thead><tbody>';

		printf(
			'<tr><td><code>%1$s</code></td><td>%2$s</td><td>%3$s</td></tr>',
			esc_html( 'segment_publication_gate_enabled' ),
			esc_html( $this->flag_state_label( $gate_saved ) ),
			esc_html( $this->flag_state_label( $gate_effective ) )
		);
		printf(
			'<tr><td><code>%1$s</code></td><td>%2$s</td><td>%3$s</td></tr>',
			esc_html( 'auto_publication_mode' ),
			esc_html( $mode_saved ),
			esc_html( $mode_effective )
		);

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Gate defaults off; mode defaults to manual. Setting mode to manual disables future automation without mass-unpublish.', 'ai-multilingual' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Confirmation prompts for gate enable and auto-publication mode changes.
	 */
	private function render_publication_confirmation_script(): void {
		$gate_message = __(
			'Enabling the publication gate immediately enforces overlay eligibility based on each segment\'s existing publish_status. It does not delete translations or mass-unpublish. Continue?',
			'ai-multilingual'
		);
		$mode_message = __(
			'Changing auto-publication mode only affects future maybe_auto_publish attempts after auto-translate. It does not reconcile inventory or mass-publish. Returning to manual stops future automation and does not mass-unpublish. Continue?',
			'ai-multilingual'
		);

		printf(
			'<script>
			(function () {
				var gate = document.querySelector(%1$s);
				if (gate) {
					gate.addEventListener("change", function () {
						if (!gate.checked) { return; }
						if (!window.confirm(%2$s)) { gate.checked = false; }
					});
				}
				var mode = document.getElementById("aiml-auto_publication_mode");
				if (mode) {
					var previous = mode.value;
					mode.addEventListener("change", function () {
						if (mode.value === previous) { return; }
						if (!window.confirm(%3$s)) {
							mode.value = previous;
							return;
						}
						previous = mode.value;
					});
				}
			}());
			</script>',
			wp_json_encode( 'input[name="' . Settings::OPTION . '[segment_publication_gate_enabled]"]' ),
			wp_json_encode( $gate_message ),
			wp_json_encode( $mode_message )
		);
	}

	/**
	 * Inline confirmation guard for enabling frontend rendering.
	 */
	private function render_strategy_f_confirmation_script(): void {
		$message = __(
			'Enabling frontend rendering may show translated block content to site visitors. Prerequisites must already be enabled. Disabling this flag is the immediate kill switch. Continue?',
			'ai-multilingual'
		);

		printf(
			'<script>
			(function () {
				var box = document.getElementById(%1$s);
				if (!box) { return; }
				box.addEventListener("change", function () {
					if (!box.checked || !box.hasAttribute("data-aiml-requires-confirm")) { return; }
					if (!window.confirm(%2$s)) { box.checked = false; }
				});
			}());
			</script>',
			wp_json_encode( 'aiml-' . FeatureFlags::FRONTEND_RENDER ),
			wp_json_encode( $message )
		);
	}

	/**
	 * Human-readable on/off label for diagnostics output.
	 *
	 * @param bool $enabled Flag state.
	 */
	private function flag_state_label( bool $enabled ): string {
		return $enabled ? __( 'On', 'ai-multilingual' ) : __( 'Off', 'ai-multilingual' );
	}

	/**
	 * Clears any queued Strategy F rejection notice for the current user.
	 */
	private function clear_flag_rejection_notice(): void {
		if ( ! function_exists( 'get_current_user_id' ) || ! function_exists( 'delete_transient' ) ) {
			return;
		}

		$user_id = (int) get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		delete_transient( self::FLAG_NOTICE_TRANSIENT . '_' . $user_id );
	}

	/**
	 * Queues an admin notice when submitted production flags were normalized away.
	 *
	 * @param array<string, mixed> $payload Shared rejection audit payload.
	 */
	private function queue_flag_rejection_notice( array $payload ): void {
		if ( ! function_exists( 'get_current_user_id' ) || ! function_exists( 'set_transient' ) ) {
			return;
		}

		$user_id = (int) get_current_user_id();
		if ( $user_id <= 0 || empty( $payload['dropped_flags'] ) ) {
			return;
		}

		set_transient(
			self::FLAG_NOTICE_TRANSIENT . '_' . $user_id,
			array(
				'id'        => self::FLAG_NOTICE_ID,
				'dropped'   => $payload['dropped_flags'],
				'submitted' => $payload['submitted'],
				'effective' => $payload['effective'],
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Prints Strategy F dependency rejection notices after settings save.
	 */
	public function render_strategy_f_admin_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( self::SETTINGS_SLUG !== $page ) {
			return;
		}

		if ( ! function_exists( 'get_current_user_id' ) || ! function_exists( 'get_transient' ) ) {
			return;
		}

		$user_id = (int) get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$payload = get_transient( self::FLAG_NOTICE_TRANSIENT . '_' . $user_id );
		if ( ! is_array( $payload ) || empty( $payload['dropped'] ) ) {
			return;
		}

		delete_transient( self::FLAG_NOTICE_TRANSIENT . '_' . $user_id );

		$messages = array();
		foreach ( (array) $payload['dropped'] as $flag ) {
			if ( ! is_string( $flag ) ) {
				continue;
			}

			$prerequisite = FeatureFlags::prerequisite_label( $flag );
			$messages[]   = sprintf(
				/* translators: 1: flag key, 2: prerequisite flag key(s) */
				__( '%1$s could not be enabled because prerequisite %2$s is off.', 'ai-multilingual' ),
				$flag,
				$prerequisite
			);
		}

		printf(
			'<div class="notice notice-warning is-dismissible" data-notice-id="%1$s"><p><strong>%2$s</strong> %3$s</p></div>',
			esc_attr( self::FLAG_NOTICE_ID ),
			esc_html__( 'Strategy F flag combination adjusted.', 'ai-multilingual' ),
			esc_html( implode( ' ', $messages ) )
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
