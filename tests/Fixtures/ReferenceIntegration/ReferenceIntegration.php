<?php
/**
 * Test/acceptance-only reference integration for Integration API v1.
 *
 * Contained under tests/fixtures — never registered from production Plugin.php.
 * Release ZIP copies src/ only, so this path is excluded from production packages.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Fixtures\ReferenceIntegration;

use AIMultilingual\Integration\CompatibilityStatus;
use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Integration\PluginIntegrationInterface;
use AIMultilingual\Integration\TranslationUnitDescriptor;
use WP_Post;

/**
 * Deterministic record-owned reference surface for framework proof.
 */
final class ReferenceIntegration implements PluginIntegrationInterface {

	public const ID = 'aiml_reference';

	public const META_TITLE  = '_aiml_ref_title';
	public const META_NESTED = '_aiml_ref_nested_label';

	public const FILTER_TITLE  = 'aiml_reference_integration_title';
	public const FILTER_NESTED = 'aiml_reference_integration_nested_label';

	/**
	 * @param PluginIdentity $identity   Serializer.
	 * @param bool           $installed  Simulated plugin installed.
	 * @param bool           $active     Simulated plugin active.
	 * @param string         $version    Simulated plugin version.
	 * @param string         $min_version Minimum supported version.
	 * @param bool           $disabled   Integration disabled switch.
	 */
	public function __construct(
		private PluginIdentity $identity,
		private bool $installed = true,
		private bool $active = true,
		private string $version = '1.0.0',
		private string $min_version = '1.0.0',
		private bool $disabled = false,
	) {
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
		if ( version_compare( $this->version, $this->min_version, '<' ) ) {
			return new CompatibilityStatus( Contract::STATE_UNSUPPORTED_VERSION, 'version_too_low' );
		}
		return new CompatibilityStatus( Contract::STATE_COMPATIBLE, 'ok' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function extract_for_post( WP_Post $post ): array {
		if ( ! $this->get_compatibility()->allows_operation() ) {
			return array();
		}

		$owner_id = (string) (int) $post->ID;
		$units    = array();

		$title = (string) get_post_meta( (int) $post->ID, self::META_TITLE, true );
		if ( '' !== trim( $title ) ) {
			$key     = $this->identity->build( self::ID, 'record', $owner_id, 'title' );
			$units[] = TranslationUnitDescriptor::from_source(
				$key,
				$title,
				Contract::FORMAT_PLAIN,
				Contract::OWNERSHIP_RECORD,
				'record',
				$owner_id,
				'title',
				'Reference title',
				self::ID,
				''
			);
		}

		$nested = (string) get_post_meta( (int) $post->ID, self::META_NESTED, true );
		if ( '' !== trim( $nested ) ) {
			$key     = $this->identity->build( self::ID, 'record', $owner_id, 'label', 'primary' );
			$units[] = TranslationUnitDescriptor::from_source(
				$key,
				$nested,
				Contract::FORMAT_PLAIN,
				Contract::OWNERSHIP_RECORD,
				'record',
				$owner_id,
				'label',
				'Reference nested label',
				self::ID,
				'title'
			);
		}

		return $units;
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_output_hooks( callable $resolve ): void {
		$identity = $this->identity;
		$post_id  = (int) get_queried_object_id();

		add_filter(
			self::FILTER_TITLE,
			static function ( string $source ) use ( $resolve, $identity, $post_id ): string {
				if ( $post_id <= 0 ) {
					return $source;
				}
				try {
					$key = $identity->build( self::ID, 'record', (string) $post_id, 'title' );
				} catch ( \InvalidArgumentException $e ) {
					return $source;
				}
				$translated = $resolve( $key );
				return is_string( $translated ) && '' !== $translated ? $translated : $source;
			},
			10,
			1
		);

		add_filter(
			self::FILTER_NESTED,
			static function ( string $source ) use ( $resolve, $identity, $post_id ): string {
				if ( $post_id <= 0 ) {
					return $source;
				}
				try {
					$key = $identity->build( self::ID, 'record', (string) $post_id, 'label', 'primary' );
				} catch ( \InvalidArgumentException $e ) {
					return $source;
				}
				$translated = $resolve( $key );
				return is_string( $translated ) && '' !== $translated ? $translated : $source;
			},
			10,
			1
		);
	}

	/**
	 * Test helper: mutate simulated plugin state.
	 *
	 * @param bool|null   $installed Installed.
	 * @param bool|null   $active    Active.
	 * @param string|null $version   Version.
	 * @param bool|null   $disabled  Disabled.
	 */
	public function configure(
		?bool $installed = null,
		?bool $active = null,
		?string $version = null,
		?bool $disabled = null
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
	}
}
