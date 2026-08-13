<?php
/**
 * Public extension registration facade (Extension API v1).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Extension;

use AIMultilingual\Block\AdapterRegistry;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Extension\Block\ExtensionBlockAdapter;
use AIMultilingual\Extension\Block\ExtensionBlockAdapterBridge;
use AIMultilingual\Surface\Meta\RegisteredMetaRegistry;
use AIMultilingual\Translation\Store;
use InvalidArgumentException;

/**
 * Root registrar: extension ownership, nested registrations, registry sealing.
 */
final class ExtensionRegistrar {

	/**
	 * @param RegisteredMetaRegistry $meta_registry   Internal meta catalog.
	 * @param AdapterRegistry        $adapter_registry Internal block adapter catalog.
	 * @param ExtensionRegistry      $registry        Extension catalog.
	 * @param ExtensionDiagnostics   $diagnostics     Diagnostics sink.
	 */
	public function __construct(
		private RegisteredMetaRegistry $meta_registry,
		private AdapterRegistry $adapter_registry,
		private ExtensionRegistry $registry,
		private ExtensionDiagnostics $diagnostics,
	) {
	}

	/**
	 * Registers an extension and returns its nested registration handle.
	 *
	 * @param ExtensionManifest $manifest Extension manifest.
	 * @throws InvalidArgumentException On invalid or duplicate extension (Tier A).
	 */
	public function register_extension( ExtensionManifest $manifest ): RegisteredExtension {
		$this->registry->assert_open();
		$this->assert_manifest( $manifest );

		try {
			$record = $this->registry->add_extension( $manifest );
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_EXTENSION_REGISTERED );
			return new RegisteredExtension( $this, $record );
		} catch ( InvalidArgumentException $e ) {
			$this->diagnostics->record_failure( $e->getMessage() );
			throw $e;
		}
	}

	/**
	 * @param ExtensionRecord         $record     Extension record.
	 * @param ExtensionMetaDefinition $definition Meta definition.
	 * @throws InvalidArgumentException Tier A validation failure.
	 */
	public function register_meta_for( ExtensionRecord $record, ExtensionMetaDefinition $definition ): void {
		$this->registry->assert_open();
		$this->assert_meta_definition( $record, $definition );

		foreach ( $record->pending_meta as $pending ) {
			if ( $pending->definition->source_type === $definition->source_type
				&& $pending->definition->meta_key === $definition->meta_key ) {
				throw new InvalidArgumentException( 'Duplicate meta key in extension registration.' );
			}
		}

		$record->pending_meta[] = new PendingExtensionMeta( $definition );
		++$record->meta_count;
		if ( $definition->provider_allowed ) {
			++$record->provider_allowed_count;
		} else {
			++$record->provider_denied_count;
		}
		$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_META_REGISTERED );
	}

	/**
	 * @param ExtensionRecord       $record  Extension record.
	 * @param ExtensionBlockAdapter $adapter Block adapter.
	 * @throws InvalidArgumentException Tier A validation failure.
	 */
	public function register_block_for( ExtensionRecord $record, ExtensionBlockAdapter $adapter ): void {
		$this->registry->assert_open();
		$this->assert_block_adapter( $adapter );

		$bridge = new ExtensionBlockAdapterBridge( $adapter );
		try {
			$this->adapter_registry->register( $bridge );
		} catch ( InvalidArgumentException $e ) {
			$this->diagnostics->record_failure( $e->getMessage() );
			throw $e;
		}

		$record->block_adapters[] = $adapter;
		++$record->block_count;
		$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_BLOCK_REGISTERED );
	}

	/**
	 * Evaluates activation once, registers pending meta into internal catalog, seals registries.
	 */
	public function seal(): void {
		if ( $this->registry->is_sealed() ) {
			return;
		}

		foreach ( $this->registry->all() as $record ) {
			$this->finalize_extension( $record );
		}

		$this->registry->seal();
	}

	public function is_sealed(): bool {
		return $this->registry->is_sealed();
	}

	/**
	 * Internal catalog for diagnostics — not public API.
	 */
	public function internal_registry(): ExtensionRegistry {
		return $this->registry;
	}

	/**
	 * @param ExtensionManifest $manifest Manifest.
	 * @throws InvalidArgumentException On invalid manifest.
	 */
	private function assert_manifest( ExtensionManifest $manifest ): void {
		$id = $manifest->extension_id;
		if ( '' === $id || strlen( $id ) > Contract::MAX_EXTENSION_ID_LENGTH ) {
			throw new InvalidArgumentException( 'Invalid extension ID length.' );
		}
		if ( 1 !== preg_match( Contract::EXTENSION_ID_PATTERN, $id ) ) {
			throw new InvalidArgumentException( 'Invalid extension ID format.' );
		}
		if ( '' === trim( $manifest->version ) ) {
			throw new InvalidArgumentException( 'Extension version is required.' );
		}
		if ( array() === $manifest->owned_namespaces ) {
			throw new InvalidArgumentException( 'Extension must declare at least one owned namespace.' );
		}
		foreach ( $manifest->owned_namespaces as $namespace ) {
			$this->assert_namespace_token( $namespace );
			if ( in_array( $namespace, Contract::RESERVED_NAMESPACES, true ) ) {
				throw new InvalidArgumentException( 'Reserved namespace cannot be owned by extensions.' );
			}
		}
	}

	/**
	 * @param ExtensionRecord         $record     Extension record.
	 * @param ExtensionMetaDefinition $definition Definition.
	 * @throws InvalidArgumentException On invalid definition.
	 */
	private function assert_meta_definition( ExtensionRecord $record, ExtensionMetaDefinition $definition ): void {
		$this->assert_namespace_ownership( $record, $definition->namespace );

		if ( ! in_array( $definition->source_type, array( Store::SOURCE_POST, Store::SOURCE_TERM ), true ) ) {
			throw new InvalidArgumentException( 'Meta source_type must be post or term.' );
		}
		if ( '' === $definition->meta_key || str_contains( $definition->meta_key, '*' ) || str_contains( $definition->meta_key, '%' ) ) {
			throw new InvalidArgumentException( 'Meta key must be exact (no wildcards).' );
		}
		if ( str_starts_with( $definition->meta_key, '_' ) && str_starts_with( $definition->meta_key, '_wc_' ) ) {
			// Economic keys caught by internal registry; allow explicit rejection early.
		}
		if ( ! in_array( $definition->text_format, array( 'plain', 'html' ), true ) ) {
			throw new InvalidArgumentException( 'Meta text_format must be plain or html.' );
		}
		if ( null !== $definition->activation && ! is_callable( $definition->activation ) ) {
			throw new InvalidArgumentException( 'Meta activation must be callable when provided.' );
		}

		// Fail fast if internal registry would reject (Tier A — no partial registration).
		$probe = ExtensionMetaBridge::to_internal( $definition, true );
		if ( $this->meta_registry->has( $definition->source_type, $definition->meta_key ) ) {
			throw new InvalidArgumentException( 'Duplicate registered meta key for source type.' );
		}
		// Touch validation via register attempt in finalize; probe native key collision.
		$native = $probe->native_segment_key();
		unset( $probe );
		if ( str_starts_with( $native, 'm:rankmath:' ) || str_starts_with( $native, 'm:woocommerce:' ) ) {
			throw new InvalidArgumentException( 'Reserved meta identity collision.' );
		}
	}

	/**
	 * @param ExtensionBlockAdapter $adapter Adapter.
	 * @throws InvalidArgumentException On invalid adapter.
	 */
	private function assert_block_adapter( ExtensionBlockAdapter $adapter ): void {
		$names = $adapter->get_block_names();
		if ( array() === $names ) {
			throw new InvalidArgumentException( 'Block adapter must declare at least one block name.' );
		}
		foreach ( $names as $block_name ) {
			if ( '' === $block_name ) {
				throw new InvalidArgumentException( 'Block name cannot be empty.' );
			}
			if ( in_array( $block_name, BlockRegistry::SUPPORTED_BLOCKS, true ) ) {
				throw new InvalidArgumentException( 'Core block collision: ' . $block_name );
			}
			if ( in_array( $block_name, BlockRegistry::DYNAMIC_BLOCK_NAMES, true ) ) {
				throw new InvalidArgumentException( 'Dynamic block registration forbidden: ' . $block_name );
			}
		}
		$fields = $adapter->get_supported_fields();
		if ( array() === $fields ) {
			throw new InvalidArgumentException( 'Block adapter must declare supported fields.' );
		}
	}

	/**
	 * @param ExtensionRecord $record Extension record.
	 */
	private function finalize_extension( ExtensionRecord $record ): void {
		$extension_active = true;

		foreach ( $record->pending_meta as $pending ) {
			$active = $this->evaluate_activation( $pending->definition->activation, $record->manifest->extension_id );
			$pending->active = $active;
			if ( ! $active ) {
				$extension_active = false;
			}

			try {
				$internal = ExtensionMetaBridge::to_internal( $pending->definition, $active );
				$this->meta_registry->register( $internal );
			} catch ( InvalidArgumentException $e ) {
				$this->diagnostics->record_failure( $e->getMessage() );
				$record->active = false;
				return;
			}
		}

		$record->active = $extension_active || array() === $record->pending_meta;
	}

	/**
	 * Tier B: AIML-invoked activation callback isolation.
	 *
	 * @param callable():bool|null $activation   Optional activation.
	 * @param string               $extension_id Extension id for diagnostics.
	 */
	private function evaluate_activation( mixed $activation, string $extension_id ): bool {
		if ( null === $activation ) {
			return true;
		}
		if ( ! is_callable( $activation ) ) {
			return false;
		}
		try {
			return (bool) $activation();
		} catch ( \Throwable ) {
			$this->diagnostics->increment( ExtensionDiagnostics::COUNTER_CALLBACK_FAILURE );
			$this->diagnostics->record_failure( 'Activation callback failed for extension: ' . $extension_id );
			return false;
		}
	}

	/**
	 * @param ExtensionRecord $record    Extension record.
	 * @param string          $namespace Namespace token.
	 * @throws InvalidArgumentException When namespace not owned.
	 */
	private function assert_namespace_ownership( ExtensionRecord $record, string $namespace ): void {
		$this->assert_namespace_token( $namespace );
		if ( ! in_array( $namespace, $record->manifest->owned_namespaces, true ) ) {
			throw new InvalidArgumentException( 'Namespace not owned by extension manifest.' );
		}
	}

	/**
	 * @param string $namespace Namespace token.
	 * @throws InvalidArgumentException On invalid namespace.
	 */
	private function assert_namespace_token( string $namespace ): void {
		if ( '' === $namespace || strlen( $namespace ) > Contract::MAX_NAMESPACE_LENGTH ) {
			throw new InvalidArgumentException( 'Invalid namespace length.' );
		}
		if ( 1 !== preg_match( Contract::NAMESPACE_PATTERN, $namespace ) ) {
			throw new InvalidArgumentException( 'Invalid namespace format.' );
		}
	}
}
