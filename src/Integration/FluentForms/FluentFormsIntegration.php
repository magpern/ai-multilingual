<?php
/**
 * Fluent Forms Contact Form #5 Integration API v1 consumer (A.8).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration\FluentForms;

use AIMultilingual\Elementor\ElementorDocumentDetector;
use AIMultilingual\Integration\CompatibilityStatus;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\IntegrationSecurity;
use AIMultilingual\Integration\PluginIntegrationInterface;
use AIMultilingual\Integration\TranslationUnitDescriptor;
use AIMultilingual\Translation\Store;
use WP_Post;

/**
 * Record-owned Fluent Forms bridge for Contact Form #5 only.
 *
 * Surfaces: full_name label, email label, submit_text.
 * Overlays via verified Fluent Forms 6.2.9 field-data filters only.
 */
final class FluentFormsIntegration implements PluginIntegrationInterface {

	public const ID = 'fluentform';

	public const FORM_ID = 5;

	public const CONTACT_PAGE_ID = 3410;

	public const MIN_VERSION = '6.2.0';

	public const PLUGIN_BASENAME = 'fluentform/fluentform.php';

	public const HOOK_INPUT_TEXT = 'fluentform/rendering_field_data_input_text';

	public const HOOK_INPUT_EMAIL = 'fluentform/rendering_field_data_input_email';

	public const HOOK_BUTTON = 'fluentform/rendering_field_data_button';

	public const FIELD_FULL_NAME = 'full_name';

	public const FIELD_EMAIL = 'email';

	public const FIELD_SUBMIT = 'submit_text';

	/**
	 * Builds the Fluent Forms integration.
	 *
	 * @param PluginIdentity             $identity       Serializer.
	 * @param FluentFormsEmbedDetector   $embed          Embed detector.
	 * @param FluentFormDefinitionReader $reader         Form definition reader.
	 * @param bool|null                  $installed      Test override.
	 * @param bool|null                  $active         Test override.
	 * @param string|null                $version        Test override.
	 * @param bool|null                  $disabled       Test override.
	 * @param bool|null                  $hooks_present  Test override.
	 * @param bool|null                  $embed_override Test override for embed detection.
	 */
	public function __construct(
		private PluginIdentity $identity,
		private FluentFormsEmbedDetector $embed,
		private FluentFormDefinitionReader $reader,
		private ?bool $installed = null,
		private ?bool $active = null,
		private ?string $version = null,
		private ?bool $disabled = null,
		private ?bool $hooks_present = null,
		private ?bool $embed_override = null,
	) {
	}

	/**
	 * Convenience factory for production wiring.
	 *
	 * @param PluginIdentity $identity Serializer.
	 */
	public static function create_default( PluginIdentity $identity ): self {
		return new self(
			$identity,
			new FluentFormsEmbedDetector( new ElementorDocumentDetector() ),
			new WpFluentFormDefinitionReader()
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_api_version(): string {
		return Contract::API_VERSION;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_compatibility(): CompatibilityStatus {
		if ( ! $this->is_installed() ) {
			return new CompatibilityStatus( Contract::STATE_UNAVAILABLE, 'plugin_missing' );
		}
		if ( $this->is_disabled() ) {
			return new CompatibilityStatus( Contract::STATE_DISABLED, 'integration_disabled' );
		}
		if ( ! $this->is_active() ) {
			return new CompatibilityStatus( Contract::STATE_UNAVAILABLE, 'plugin_inactive' );
		}
		if ( version_compare( $this->resolved_version(), self::MIN_VERSION, '<' ) ) {
			return new CompatibilityStatus( Contract::STATE_UNSUPPORTED_VERSION, 'version_too_low' );
		}
		if ( ! $this->required_hooks_present() ) {
			return new CompatibilityStatus( Contract::STATE_MISSING_REQUIRED_HOOK, 'hooks_missing' );
		}
		return new CompatibilityStatus( Contract::STATE_COMPATIBLE, 'ok' );
	}

	/**
	 * Extract allowlisted Form #5 units when the post embeds the form.
	 *
	 * @param WP_Post $post Canonical post.
	 * @return list<TranslationUnitDescriptor>
	 * @throws \RuntimeException When duplicate segment keys are produced.
	 */
	public function extract_for_post( WP_Post $post ): array {
		if ( ! $this->get_compatibility()->allows_operation() ) {
			return array();
		}
		if ( ! $this->post_embeds_form( $post ) ) {
			return array();
		}

		$fields = $this->reader->get_decoded_fields( self::FORM_ID );
		if ( null === $fields ) {
			return array();
		}

		$owner_id = (string) self::FORM_ID;
		$units    = array();
		$seen     = array();

		foreach ( $this->allowlisted_sources( $fields ) as $item ) {
			$text = IntegrationSecurity::sanitize_plain( $item['source'] );
			if ( '' === $text ) {
				continue;
			}
			try {
				$key = $this->build_key( $item['field'], $item['nested'] );
			} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				continue;
			}
			if ( isset( $seen[ $key ] ) ) {
				throw new \RuntimeException( 'Duplicate Fluent Forms segment key.' );
			}
			$seen[ $key ] = true;
			$units[]      = new TranslationUnitDescriptor(
				$key,
				$text,
				Store::source_hash( $text, Store::FORMAT_PLAIN ),
				Store::FORMAT_PLAIN,
				Contract::OWNERSHIP_RECORD,
				'form',
				$owner_id,
				$item['field'],
				$item['label'],
				self::ID,
				'Contact Form #' . self::FORM_ID
			);
		}

		return $units;
	}

	/**
	 * Register Fluent Forms field-data overlay filters.
	 *
	 * @param callable(string): (?string) $resolve Segment key resolver.
	 */
	public function register_output_hooks( callable $resolve ): void {
		if ( ! $this->get_compatibility()->allows_overlay() ) {
			return;
		}

		$identity = $this->identity;

		add_filter(
			self::HOOK_INPUT_TEXT,
			function ( $data, $form ) use ( $resolve, $identity ) {
				return $this->overlay_field_label(
					$data,
					$form,
					self::FIELD_FULL_NAME,
					$resolve,
					$identity,
					true
				);
			},
			10,
			2
		);

		add_filter(
			self::HOOK_INPUT_EMAIL,
			function ( $data, $form ) use ( $resolve, $identity ) {
				return $this->overlay_field_label(
					$data,
					$form,
					self::FIELD_EMAIL,
					$resolve,
					$identity,
					true
				);
			},
			10,
			2
		);

		add_filter(
			self::HOOK_BUTTON,
			function ( $data, $form ) use ( $resolve, $identity ) {
				return $this->overlay_submit_text( $data, $form, $resolve, $identity );
			},
			10,
			2
		);
	}

	/**
	 * Test helper: mutate simulated plugin state.
	 *
	 * @param bool|null   $installed     Installed.
	 * @param bool|null   $active        Active.
	 * @param string|null $version       Version.
	 * @param bool|null   $disabled      Disabled.
	 * @param bool|null   $hooks_present Hooks present.
	 */
	public function configure(
		?bool $installed = null,
		?bool $active = null,
		?string $version = null,
		?bool $disabled = null,
		?bool $hooks_present = null
	): void {
		if ( null !== $installed ) {
			$this->installed = $installed;
		}
		if ( null !== $active ) {
			$this->active = $active;
		}
		if ( null !== $version ) {
			$this->version = $version;
		}
		if ( null !== $disabled ) {
			$this->disabled = $disabled;
		}
		if ( null !== $hooks_present ) {
			$this->hooks_present = $hooks_present;
		}
	}

	/**
	 * Overlay a field label when the field name matches the allowlist.
	 *
	 * @param array<string, mixed>        $data       Field data.
	 * @param mixed                       $form       Fluent Forms form object.
	 * @param string                      $field      Field name token.
	 * @param callable(string): (?string) $resolve    Resolver.
	 * @param PluginIdentity              $identity   Identity.
	 * @param bool                        $use_nested Whether nested label component is used.
	 * @return array<string, mixed>|mixed
	 */
	private function overlay_field_label( $data, $form, string $field, callable $resolve, PluginIdentity $identity, bool $use_nested ) {
		if ( ! is_array( $data ) || ! $this->is_target_form( $form ) ) {
			return $data;
		}
		$name = isset( $data['attributes']['name'] ) ? (string) $data['attributes']['name'] : '';
		if ( $field !== $name ) {
			return $data;
		}
		try {
			$key = $use_nested
				? $identity->build( self::ID, 'form', (string) self::FORM_ID, $field, 'label' )
				: $identity->build( self::ID, 'form', (string) self::FORM_ID, $field );
		} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return $data;
		}
		$translated = $resolve( $key );
		if ( ! is_string( $translated ) ) {
			return $data;
		}
		$plain = IntegrationSecurity::sanitize_plain( $translated );
		if ( '' === $plain ) {
			return $data;
		}
		if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			$data['settings'] = array();
		}
		$data['settings']['label'] = $plain;
		return $data;
	}

	/**
	 * Overlay submit button text for Form #5.
	 *
	 * @param array<string, mixed>        $data     Button data.
	 * @param mixed                       $form     Form object.
	 * @param callable(string): (?string) $resolve  Resolver.
	 * @param PluginIdentity              $identity Identity.
	 * @return array<string, mixed>|mixed
	 */
	private function overlay_submit_text( $data, $form, callable $resolve, PluginIdentity $identity ) {
		if ( ! is_array( $data ) || ! $this->is_target_form( $form ) ) {
			return $data;
		}
		try {
			$key = $identity->build( self::ID, 'form', (string) self::FORM_ID, self::FIELD_SUBMIT );
		} catch ( \InvalidArgumentException $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return $data;
		}
		$translated = $resolve( $key );
		if ( ! is_string( $translated ) ) {
			return $data;
		}
		$plain = IntegrationSecurity::sanitize_plain( $translated );
		if ( '' === $plain ) {
			return $data;
		}
		if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			$data['settings'] = array();
		}
		if ( ! isset( $data['settings']['button_ui'] ) || ! is_array( $data['settings']['button_ui'] ) ) {
			$data['settings']['button_ui'] = array( 'type' => 'default' );
		}
		$data['settings']['button_ui']['text'] = $plain;
		return $data;
	}

	/**
	 * Whether the form object is Contact Form #5.
	 *
	 * @param mixed $form Form object.
	 */
	private function is_target_form( $form ): bool {
		if ( ! is_object( $form ) ) {
			return false;
		}
		$id = 0;
		if ( isset( $form->id ) ) {
			$id = (int) $form->id;
		}
		return self::FORM_ID === $id;
	}

	/**
	 * Build a frozen p: identity for an allowlisted field.
	 *
	 * @param string      $field  Field token.
	 * @param string|null $nested Nested token or null.
	 */
	private function build_key( string $field, ?string $nested ): string {
		if ( null === $nested || '' === $nested ) {
			return $this->identity->build( self::ID, 'form', (string) self::FORM_ID, $field );
		}
		return $this->identity->build( self::ID, 'form', (string) self::FORM_ID, $field, $nested );
	}

	/**
	 * Collect allowlisted source strings from decoded form_fields.
	 *
	 * @param array<string, mixed> $fields Decoded form_fields.
	 * @return list<array{field:string,nested:?string,source:string,label:string}>
	 */
	private function allowlisted_sources( array $fields ): array {
		$out    = array();
		$nodes  = isset( $fields['fields'] ) && is_array( $fields['fields'] ) ? $fields['fields'] : array();
		$wanted = array(
			self::FIELD_FULL_NAME => 'Name label',
			self::FIELD_EMAIL     => 'Email label',
		);

		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$name = isset( $node['attributes']['name'] ) ? (string) $node['attributes']['name'] : '';
			if ( ! isset( $wanted[ $name ] ) ) {
				continue;
			}
			$label = isset( $node['settings']['label'] ) ? (string) $node['settings']['label'] : '';
			$out[] = array(
				'field'  => $name,
				'nested' => 'label',
				'source' => $label,
				'label'  => $wanted[ $name ],
			);
		}

		$submit = '';
		if ( isset( $fields['submitButton']['settings']['button_ui']['text'] )
			&& is_scalar( $fields['submitButton']['settings']['button_ui']['text'] ) ) {
			$submit = (string) $fields['submitButton']['settings']['button_ui']['text'];
		}
		$out[] = array(
			'field'  => self::FIELD_SUBMIT,
			'nested' => null,
			'source' => $submit,
			'label'  => 'Submit button text',
		);

		return $out;
	}

	/**
	 * Whether the post embeds Form #5.
	 *
	 * @param WP_Post $post Post.
	 */
	private function post_embeds_form( WP_Post $post ): bool {
		if ( null !== $this->embed_override ) {
			return $this->embed_override;
		}
		return $this->embed->embeds_form( $post, self::FORM_ID );
	}

	/**
	 * Whether Fluent Forms appears installed.
	 */
	private function is_installed(): bool {
		if ( null !== $this->installed ) {
			return $this->installed;
		}
		if ( defined( 'FLUENTFORM' ) || defined( 'FLUENTFORM_VERSION' ) ) {
			return true;
		}
		if ( function_exists( 'wpFluent' ) ) {
			return true;
		}
		return file_exists( WP_PLUGIN_DIR . '/' . self::PLUGIN_BASENAME );
	}

	/**
	 * Whether Fluent Forms is active.
	 */
	private function is_active(): bool {
		if ( null !== $this->active ) {
			return $this->active;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return function_exists( 'is_plugin_active' ) && is_plugin_active( self::PLUGIN_BASENAME );
	}

	/**
	 * Whether the AIML integration is disabled.
	 */
	private function is_disabled(): bool {
		if ( null !== $this->disabled ) {
			return $this->disabled;
		}
		/**
		 * Disable the Fluent Forms Contact Form #5 integration.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $disabled Whether the integration is disabled.
		 */
		return (bool) apply_filters( 'aiml_fluentform_integration_disabled', false );
	}

	/**
	 * Resolved Fluent Forms version string.
	 */
	private function resolved_version(): string {
		if ( null !== $this->version ) {
			return $this->version;
		}
		if ( defined( 'FLUENTFORM_VERSION' ) ) {
			return (string) FLUENTFORM_VERSION;
		}
		return '0.0.0';
	}

	/**
	 * Whether required Fluent Forms render components exist.
	 */
	private function required_hooks_present(): bool {
		if ( null !== $this->hooks_present ) {
			return $this->hooks_present;
		}
		return class_exists( '\\FluentForm\\App\\Services\\FormBuilder\\Components\\Text' )
			&& class_exists( '\\FluentForm\\App\\Services\\FormBuilder\\Components\\SubmitButton' );
	}
}
