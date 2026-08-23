<?php
/**
 * Sealed chrome-owned surface declaration value object.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

/**
 * Narrow declaration of one private CPT chrome surface owned by an integration.
 */
final class ChromeOwnedSurfaceDeclaration {

	/**
	 * Extraction mode: only integration-owned `p:` units (no native/block/Elementor/meta).
	 */
	public const EXTRACTION_INTEGRATION_UNITS_ONLY = 'integration_units_only';

	/**
	 * Builds a sealed chrome surface declaration.
	 *
	 * @param string       $post_type   Private/admin CPT slug.
	 * @param list<string> $owner_types Allowed `p:` owner_type tokens.
	 * @param list<string> $fields      Allowed field tokens (first field path component).
	 * @param string       $extraction  Extraction mode (M5-A: integration_units_only only).
	 */
	public function __construct( // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- list<string> documents owner_types/fields shape.
		public readonly string $post_type,
		public readonly array $owner_types,
		public readonly array $fields,
		public readonly string $extraction = self::EXTRACTION_INTEGRATION_UNITS_ONLY,
	) {
	}

	/**
	 * Whether an owner_type token is declared.
	 *
	 * @param string $owner_type Owner type token.
	 */
	public function allows_owner_type( string $owner_type ): bool {
		return in_array( $owner_type, $this->owner_types, true );
	}

	/**
	 * Whether a field token is declared (nested components inherit the field allowlist).
	 *
	 * @param string $field Field token.
	 */
	public function allows_field( string $field ): bool {
		return in_array( $field, $this->fields, true );
	}
}
