<?php
/**
 * Registered meta field definition (TSC.2 field catalog entry).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Surface\Meta;

/**
 * Declarative field-definition facts. Does not decide source admission,
 * edit authorization, Jobs policy, publication, or OTL mutation.
 */
final class RegisteredMetaDefinition {

	public const MODE_NATIVE_M   = 'native_m';
	public const MODE_EXTERNAL_P = 'external_p';
	public const VALUE_SCALAR    = 'scalar_string';
	public const FIELD_KEY       = '_meta';
	public const SEGMENT_PREFIX  = 'm';

	public const OVERLAY_NONE               = 'none';
	public const OVERLAY_INTEGRATION_PREFIX = 'integration:';
	public const OVERLAY_REFERENCE_PREFIX   = 'reference_adapter:';

	/**
	 * @param string                $namespace                 Code-owned namespace.
	 * @param string                $source_type               post|term.
	 * @param string                $meta_key                  Exact WP meta key.
	 * @param string                $segment_key_mode          native_m|external_p.
	 * @param string                $label                     OTL label.
	 * @param list<string>|null     $admitted_subtypes         Null = all Surface-admitted subtypes.
	 * @param bool                  $extract_store_capable     May extract/store.
	 * @param bool                  $provider_allowed          May send to AI provider (default false).
	 * @param bool                  $overlay_capable           May overlay when eligible.
	 * @param string                $overlay_resolver_ownership Ownership token.
	 * @param callable():bool|null  $activation                Null = always active.
	 * @param string                $value_type                Scalar only in TSC.2.
	 * @param string                $text_format               Store format.
	 * @param string|null           $external_field_token      For external_p Rank Math field token.
	 */
	public function __construct(
		public readonly string $namespace,
		public readonly string $source_type,
		public readonly string $meta_key,
		public readonly string $segment_key_mode,
		public readonly string $label,
		public readonly ?array $admitted_subtypes = null,
		public readonly bool $extract_store_capable = true,
		public readonly bool $provider_allowed = false,
		public readonly bool $overlay_capable = false,
		public readonly string $overlay_resolver_ownership = self::OVERLAY_NONE,
		public readonly mixed $activation = null,
		public readonly string $value_type = self::VALUE_SCALAR,
		public readonly string $text_format = 'plain',
		public readonly ?string $external_field_token = null,
	) {
	}

	/**
	 * Whether the definition is currently activated.
	 */
	public function is_active(): bool {
		if ( null === $this->activation ) {
			return true;
		}
		if ( ! is_callable( $this->activation ) ) {
			return false;
		}
		return (bool) ( $this->activation )();
	}

	/**
	 * Native segment key (only valid for native_m).
	 */
	public function native_segment_key(): string {
		return self::SEGMENT_PREFIX . ':' . $this->namespace . ':' . $this->meta_key;
	}

	/**
	 * Whether subtype is allowed by this definition's refine list.
	 *
	 * @param string $subtype Post type or taxonomy.
	 */
	public function admits_subtype( string $subtype ): bool {
		if ( null === $this->admitted_subtypes ) {
			return true;
		}
		return in_array( $subtype, $this->admitted_subtypes, true );
	}
}
