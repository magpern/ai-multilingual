<?php
/**
 * Code-owned registered meta field catalog (TSC.2).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface\Meta;

use AIMultilingual\Integration\Contract;
use AIMultilingual\Integration\Identity\PluginIdentity;
use AIMultilingual\Translation\Store;
use InvalidArgumentException;

/**
 * Narrow field-definition catalog. Does not admit sources or set policy.
 */
final class RegisteredMetaRegistry {

	private const NAMESPACE_PATTERN = '/^[a-z0-9_-]+$/';

	/**
	 * Definitions keyed by source_type\0meta_key.
	 *
	 * @var array<string, RegisteredMetaDefinition>
	 */
	private array $by_meta = array();

	/**
	 * Definitions keyed by source_type\0native_segment_key.
	 *
	 * @var array<string, RegisteredMetaDefinition>
	 */
	private array $by_native_segment = array();

	/**
	 * Segment mode collision guard (source_type\0native_segment_key => mode).
	 *
	 * @var array<string, string>
	 */
	private array $segment_modes = array();

	/**
	 * Plugin identity helper for external_p segment keys.
	 *
	 * @var PluginIdentity
	 */
	private PluginIdentity $identity;

	/**
	 * Optional Store for host-emitted external_p retain lookup.
	 *
	 * @var Store|null
	 */
	private ?Store $store;

	/**
	 * Construct the catalog.
	 *
	 * @param PluginIdentity|null $identity Optional identity builder for retain keys.
	 * @param Store|null          $store    Optional Store for host-emitted retain keys.
	 */
	public function __construct( ?PluginIdentity $identity = null, ?Store $store = null ) {
		$this->identity = $identity ?? new PluginIdentity();
		$this->store    = $store;
	}

	/**
	 * Register a definition. Failures are deterministic bootstrap rejects.
	 *
	 * @param RegisteredMetaDefinition $definition Definition.
	 * @throws InvalidArgumentException On invalid or colliding registration.
	 */
	public function register( RegisteredMetaDefinition $definition ): void {
		$this->assert_definition( $definition );

		$meta_key_index = $definition->source_type . "\0" . $definition->meta_key;
		if ( isset( $this->by_meta[ $meta_key_index ] ) ) {
			$existing = $this->by_meta[ $meta_key_index ];
			if ( $existing->segment_key_mode !== $definition->segment_key_mode ) {
				throw new InvalidArgumentException( 'Silent native_m/external_p ownership switch is forbidden.' );
			}
			if ( $existing->namespace !== $definition->namespace ) {
				throw new InvalidArgumentException( 'Duplicate meta key with different namespace is forbidden.' );
			}
			throw new InvalidArgumentException( 'Duplicate registered meta key for source type.' );
		}

		if ( RegisteredMetaDefinition::MODE_NATIVE_M === $definition->segment_key_mode ) {
			$seg     = $definition->native_segment_key();
			$seg_idx = $definition->source_type . "\0" . $seg;
			if ( isset( $this->by_native_segment[ $seg_idx ] ) ) {
				throw new InvalidArgumentException( 'Duplicate native segment identity for source family.' );
			}
			if ( isset( $this->segment_modes[ $seg_idx ] ) && $this->segment_modes[ $seg_idx ] !== $definition->segment_key_mode ) {
				throw new InvalidArgumentException( 'Segment identity mode collision.' );
			}
			$this->by_native_segment[ $seg_idx ] = $definition;
			$this->segment_modes[ $seg_idx ]     = $definition->segment_key_mode;
		}

		$this->by_meta[ $meta_key_index ] = $definition;
	}

	/**
	 * Exact meta key lookup.
	 *
	 * @param string $source_type post|term.
	 * @param string $meta_key    Exact key.
	 */
	public function get( string $source_type, string $meta_key ): ?RegisteredMetaDefinition {
		return $this->by_meta[ $source_type . "\0" . $meta_key ] ?? null;
	}

	/**
	 * Whether exact key is registered for source type.
	 *
	 * @param string $source_type post|term.
	 * @param string $meta_key    Exact key.
	 */
	public function has( string $source_type, string $meta_key ): bool {
		return null !== $this->get( $source_type, $meta_key );
	}

	/**
	 * All definitions for a source type.
	 *
	 * @param string $source_type post|term.
	 * @return list<RegisteredMetaDefinition>
	 */
	public function for_source_type( string $source_type ): array {
		$out = array();
		foreach ( $this->by_meta as $key => $definition ) {
			if ( str_starts_with( $key, $source_type . "\0" ) ) {
				$out[] = $definition;
			}
		}
		return $out;
	}

	/**
	 * Active registered meta keys for invalidation allowlist.
	 *
	 * @param string $source_type post|term.
	 * @return list<string>
	 */
	public function registered_meta_keys( string $source_type ): array {
		$keys = array();
		foreach ( $this->for_source_type( $source_type ) as $definition ) {
			$keys[] = $definition->meta_key;
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Native_m definitions usable for extract on an admitted owner.
	 *
	 * @param string $source_type Source type.
	 * @param string $subtype     Post type or taxonomy.
	 * @return list<RegisteredMetaDefinition>
	 */
	public function active_native_definitions( string $source_type, string $subtype ): array {
		$out = array();
		foreach ( $this->for_source_type( $source_type ) as $definition ) {
			if ( RegisteredMetaDefinition::MODE_NATIVE_M !== $definition->segment_key_mode ) {
				continue;
			}
			if ( ! $definition->extract_store_capable ) {
				continue;
			}
			if ( ! $definition->is_active() ) {
				continue;
			}
			if ( ! $definition->admits_subtype( $subtype ) ) {
				continue;
			}
			$out[] = $definition;
		}
		return $out;
	}

	/**
	 * Segment keys to retain when definitions are inactive (CASE B).
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Owner id.
	 * @return list<string>
	 */
	public function retain_segment_keys( string $source_type, int $source_id ): array {
		$keys = array();
		foreach ( $this->for_source_type( $source_type ) as $definition ) {
			if ( $definition->is_active() ) {
				continue;
			}
			if ( RegisteredMetaDefinition::MODE_NATIVE_M === $definition->segment_key_mode ) {
				$keys[] = $definition->native_segment_key();
				continue;
			}
			// external_p: deterministic PluginIdentity rebuild for direct owner keys.
			if ( RegisteredMetaDefinition::MODE_EXTERNAL_P === $definition->segment_key_mode ) {
				$field = (string) ( $definition->external_field_token ?? '' );
				if ( '' === $field || $source_id <= 0 ) {
					continue;
				}
				$owner_type = Store::SOURCE_POST === $source_type ? 'post' : 'term';
				try {
					$keys[] = $this->identity->build(
						$definition->namespace,
						$owner_type,
						(string) $source_id,
						$field
					);
				} catch ( \InvalidArgumentException ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
					// Skip malformed identity components.
				}
			}
		}

		// Host-emitted Rank Math term SEO on shop/posts page: retain existing Store family keys.
		$keys = array_merge( $keys, $this->existing_inactive_external_family_keys( $source_type, $source_id ) );

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Whether provider may process this segment key for the source type.
	 *
	 * @param string $source_type  Source type.
	 * @param string $segment_key  Segment key.
	 * @return bool|null Null = not a registered-meta segment (caller uses other rules).
	 */
	public function provider_allowed_for_segment( string $source_type, string $segment_key ): ?bool {
		if ( str_starts_with( $segment_key, RegisteredMetaDefinition::SEGMENT_PREFIX . ':' ) ) {
			$def = $this->by_native_segment[ $source_type . "\0" . $segment_key ] ?? null;
			if ( null === $def ) {
				return false;
			}
			if ( ! $def->is_active() ) {
				return false;
			}
			return $def->provider_allowed;
		}

		if ( str_starts_with( $segment_key, Contract::SEGMENT_KEY_PREFIX . ':' ) ) {
			$parsed = $this->identity->parse( $segment_key );
			if ( ! is_array( $parsed ) ) {
				return null;
			}
			$integration = (string) ( $parsed['integration_id'] ?? '' );
			$field       = (string) ( $parsed['field'] ?? '' );
			foreach ( $this->for_source_type( $source_type ) as $definition ) {
				if ( RegisteredMetaDefinition::MODE_EXTERNAL_P !== $definition->segment_key_mode ) {
					continue;
				}
				if ( $definition->namespace !== $integration ) {
					continue;
				}
				if ( (string) $definition->external_field_token !== $field ) {
					continue;
				}
				if ( ! $definition->is_active() ) {
					return false;
				}
				return $definition->provider_allowed;
			}
		}

		return null;
	}

	/**
	 * Count registered definitions (characterization / tests; no production ceiling).
	 */
	public function count(): int {
		return count( $this->by_meta );
	}

	/**
	 * Validate a definition before registration.
	 *
	 * @param RegisteredMetaDefinition $definition Definition.
	 * @throws InvalidArgumentException On invalid fields.
	 */
	private function assert_definition( RegisteredMetaDefinition $definition ): void {
		if ( ! in_array( $definition->source_type, array( Store::SOURCE_POST, Store::SOURCE_TERM ), true ) ) {
			throw new InvalidArgumentException( 'Registered meta source_type must be post or term.' );
		}
		if ( 1 !== preg_match( self::NAMESPACE_PATTERN, $definition->namespace ) ) {
			throw new InvalidArgumentException( 'Invalid registered meta namespace.' );
		}
		if ( '' === $definition->meta_key || str_contains( $definition->meta_key, '*' ) || str_contains( $definition->meta_key, '%' ) ) {
			throw new InvalidArgumentException( 'Registered meta_key must be exact (no wildcards).' );
		}
		if ( RegisteredMetaDefinition::VALUE_SCALAR !== $definition->value_type ) {
			throw new InvalidArgumentException( 'TSC.2 supports scalar_string only.' );
		}
		if ( ! in_array( $definition->segment_key_mode, array( RegisteredMetaDefinition::MODE_NATIVE_M, RegisteredMetaDefinition::MODE_EXTERNAL_P ), true ) ) {
			throw new InvalidArgumentException( 'Invalid segment_key_mode.' );
		}
		if ( RegisteredMetaDefinition::MODE_NATIVE_M === $definition->segment_key_mode ) {
			$seg = $definition->native_segment_key();
			if ( strlen( $seg ) > Contract::MAX_SEGMENT_KEY_LENGTH ) {
				throw new InvalidArgumentException( 'Native segment key exceeds Store limit.' );
			}
			// Native identity is exactly three colon-separated tokens: m, namespace, meta_key.
			$parts = explode( ':', $seg );
			if ( count( $parts ) !== 3 ) {
				throw new InvalidArgumentException( 'Native segment key must be m:{namespace}:{meta_key}.' );
			}
		}
		if ( RegisteredMetaDefinition::MODE_EXTERNAL_P === $definition->segment_key_mode && ( null === $definition->external_field_token || '' === $definition->external_field_token ) ) {
			throw new InvalidArgumentException( 'external_p definitions require external_field_token.' );
		}
	}

	/**
	 * Existing Store keys for inactive external Rank Math family on this owner.
	 *
	 * @param string $source_type Source type.
	 * @param int    $source_id   Owner id.
	 * @return list<string>
	 */
	private function existing_inactive_external_family_keys( string $source_type, int $source_id ): array {
		$inactive_namespaces = array();
		foreach ( $this->for_source_type( $source_type ) as $definition ) {
			if ( RegisteredMetaDefinition::MODE_EXTERNAL_P !== $definition->segment_key_mode ) {
				continue;
			}
			if ( $definition->is_active() ) {
				continue;
			}
			$inactive_namespaces[ $definition->namespace ] = true;
		}
		if ( array() === $inactive_namespaces || $source_id <= 0 || null === $this->store ) {
			return array();
		}

		$out = array();
		foreach ( $this->store->distinct_segment_keys_for_source( $source_type, $source_id ) as $segment_key ) {
			if ( ! str_starts_with( $segment_key, Contract::SEGMENT_KEY_PREFIX . ':' ) ) {
				continue;
			}
			$parsed = $this->identity->parse( $segment_key );
			if ( ! is_array( $parsed ) ) {
				continue;
			}
			$integration = (string) ( $parsed['integration_id'] ?? '' );
			if ( ! isset( $inactive_namespaces[ $integration ] ) ) {
				continue;
			}
			$out[] = $segment_key;
		}
		return $out;
	}
}
