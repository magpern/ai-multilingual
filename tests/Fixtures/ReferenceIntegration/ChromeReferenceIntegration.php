<?php
/**
 * Test/acceptance-only chrome CPT integration for M5-A.
 *
 * Contained under tests/Fixtures — never registered from production Plugin.php.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Fixtures\ReferenceIntegration;

use AIMultilingual\Integration\ChromeOwnedSurfaceDeclaration;
use AIMultilingual\Integration\CompatibilityStatus;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\DeclaresChromeOwnedSurfaces;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\PluginIntegrationInterface;
use AIMultilingual\Integration\TranslationUnitDescriptor;
use WP_Post;

/**
 * Generic private-CPT chrome surface for host-independent resolve proof.
 */
final class ChromeReferenceIntegration implements PluginIntegrationInterface, DeclaresChromeOwnedSurfaces {

	public const ID = 'aiml_chrome_ref';

	public const CPT = 'aiml_chrome_item';

	public const META_BODY = '_aiml_chrome_body';

	public const OWNER_TYPE = 'chrome_item';

	public const FIELD_BODY = 'body';

	/**
	 * @param PluginIdentity $identity  Serializer.
	 * @param bool           $installed Simulated plugin installed.
	 * @param bool           $active    Simulated plugin active.
	 * @param bool           $disabled  Integration disabled switch.
	 */
	public function __construct(
		private PluginIdentity $identity,
		private bool $installed = true,
		private bool $active = true,
		private bool $disabled = false,
	) {
	}

	/**
	 * Registers the private chrome CPT used by this fixture (tests only).
	 */
	public static function register_post_type(): void {
		if ( post_type_exists( self::CPT ) ) {
			return;
		}
		register_post_type(
			self::CPT,
			array(
				'label'        => 'AIML Chrome Item',
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'supports'     => array( 'title' ),
			)
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
		if ( ! $this->installed ) {
			return new CompatibilityStatus( Contract::STATE_UNAVAILABLE, 'plugin_missing' );
		}
		if ( $this->disabled ) {
			return new CompatibilityStatus( Contract::STATE_DISABLED, 'integration_disabled' );
		}
		if ( ! $this->active ) {
			return new CompatibilityStatus( Contract::STATE_UNAVAILABLE, 'plugin_inactive' );
		}
		return new CompatibilityStatus( Contract::STATE_COMPATIBLE, 'ok' );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return list<ChromeOwnedSurfaceDeclaration>
	 */
	public function get_chrome_owned_surfaces(): array {
		return array(
			new ChromeOwnedSurfaceDeclaration(
				self::CPT,
				array( self::OWNER_TYPE ),
				array( self::FIELD_BODY ),
				ChromeOwnedSurfaceDeclaration::EXTRACTION_INTEGRATION_UNITS_ONLY
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function extract_for_post( WP_Post $post ): array {
		if ( self::CPT !== $post->post_type ) {
			return array();
		}
		if ( ! $this->get_compatibility()->allows_operation() ) {
			return array();
		}

		$body = (string) get_post_meta( (int) $post->ID, self::META_BODY, true );
		if ( '' === trim( $body ) ) {
			return array();
		}

		$owner_id = (string) (int) $post->ID;
		$key      = $this->identity->build( self::ID, self::OWNER_TYPE, $owner_id, self::FIELD_BODY );

		return array(
			TranslationUnitDescriptor::from_source(
				$key,
				$body,
				Contract::FORMAT_HTML,
				Contract::OWNERSHIP_RECORD,
				self::OWNER_TYPE,
				$owner_id,
				self::FIELD_BODY,
				'Chrome body',
				self::ID,
				''
			),
		);
	}

	/**
	 * Chrome consumers must not use host-bound register_output_hooks resolve.
	 *
	 * {@inheritdoc}
	 */
	public function register_output_hooks( callable $resolve ): void {
		// Intentionally empty — site-wide chrome uses Extension resolver.
	}
}
