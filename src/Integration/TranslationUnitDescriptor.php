<?php
/**
 * Translation unit descriptor for Integration API v1.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration;

use AIMultilingual\Translation\Store;

/**
 * One visitor-facing translation unit emitted by a plugin integration.
 */
final class TranslationUnitDescriptor {

	/**
	 * Builds a descriptor from source text using AIML's canonical internal hash path.
	 *
	 * @param string $segment_key     Framework-built `p:` key.
	 * @param string $source_text     Source value.
	 * @param string $text_format     Public Integration format value.
	 * @param string $ownership_class Contract ownership vocabulary.
	 * @param string $owner_type      Owner type token.
	 * @param string $owner_id        Owner identifier.
	 * @param string $field           Field identifier.
	 * @param string $field_label     Human-readable label.
	 * @param string $integration_id  Integration ID.
	 * @param string $parent_context  Optional parent context label.
	 * @return self
	 * @throws \InvalidArgumentException When required arguments are invalid.
	 */
	public static function from_source(
		string $segment_key,
		string $source_text,
		string $text_format,
		string $ownership_class,
		string $owner_type,
		string $owner_id,
		string $field,
		string $field_label,
		string $integration_id,
		string $parent_context = ''
	): self {
		self::assert_non_empty( $segment_key );
		self::assert_non_empty( $ownership_class );
		self::assert_non_empty( $owner_type );
		self::assert_non_empty( $owner_id );
		self::assert_non_empty( $field );
		self::assert_non_empty( $field_label );
		self::assert_non_empty( $integration_id );
		self::assert_supported_format( $text_format );
		self::assert_contract_token( $integration_id, Contract::MAX_INTEGRATION_ID_LENGTH, Contract::INTEGRATION_ID_PATTERN );
		self::assert_contract_token( $owner_type, Contract::MAX_TOKEN_LENGTH, Contract::TOKEN_PATTERN );
		self::assert_contract_token( $field, Contract::MAX_TOKEN_LENGTH, Contract::TOKEN_PATTERN );
		if ( strlen( $owner_id ) > Contract::MAX_OWNER_ID_LENGTH ) {
			throw new \InvalidArgumentException( 'owner_id exceeds max length.' );
		}
		if ( ! in_array( $ownership_class, Contract::ownership_classes(), true ) ) {
			throw new \InvalidArgumentException( 'ownership_class is invalid.' );
		}

		return new self(
			$segment_key,
			$source_text,
			Store::source_hash( $source_text, self::map_to_store_format( $text_format ) ),
			$text_format,
			$ownership_class,
			$owner_type,
			$owner_id,
			$field,
			$field_label,
			$integration_id,
			$parent_context
		);
	}

	/**
	 * Builds a translation unit descriptor.
	 *
	 * @param string $segment_key     Framework-built `p:` key.
	 * @param string $source_text     Source value.
	 * @param string $source_hash     Freshness hash.
	 * @param string $text_format     Store format value.
	 * @param string $ownership_class Contract ownership vocabulary.
	 * @param string $owner_type      Owner type token.
	 * @param string $owner_id        Owner identifier.
	 * @param string $field           Field identifier.
	 * @param string $field_label     Human-readable label.
	 * @param string $integration_id  Integration ID.
	 * @param string $parent_context  Optional parent context label.
	 */
	public function __construct(
		public readonly string $segment_key,
		public readonly string $source_text,
		public readonly string $source_hash,
		public readonly string $text_format,
		public readonly string $ownership_class,
		public readonly string $owner_type,
		public readonly string $owner_id,
		public readonly string $field,
		public readonly string $field_label,
		public readonly string $integration_id,
		public readonly string $parent_context = '',
	) {
	}

	/**
	 * Shape compatible with Extractor / SegmentAssembler segment arrays.
	 *
	 * @param int $segment_order Stable ordering hint for Workspace.
	 * @return array<string, mixed>
	 */
	public function to_segment_array( int $segment_order ): array {
		return array(
			'field_key'       => Contract::FIELD_KEY,
			'segment_key'     => $this->segment_key,
			'source_text'     => $this->source_text,
			'source_hash'     => $this->source_hash,
			'text_format'     => $this->text_format,
			'segment_order'   => $segment_order,
			'segment_kind'    => 'field',
			'surface'         => 'plugin_integration',
			'integration_id'  => $this->integration_id,
			'owner_type'      => $this->owner_type,
			'owner_id'        => $this->owner_id,
			'field_label'     => $this->field_label,
			'ownership_class' => $this->ownership_class,
			'parent_context'  => $this->parent_context,
			'block_name'      => '',
		);
	}

	/**
	 * Ensures a required string argument is not blank.
	 *
	 * @param string $value Value to validate.
	 *
	 * @throws \InvalidArgumentException When a required argument is blank.
	 */
	private static function assert_non_empty( string $value ): void {
		if ( '' === trim( $value ) ) {
			throw new \InvalidArgumentException( 'A required descriptor argument is missing.' );
		}
	}

	/**
	 * Validates a public Integration contract token.
	 *
	 * @param string $value      Token value.
	 * @param int    $max_length Maximum allowed length.
	 * @param string $pattern    Validation pattern.
	 *
	 * @throws \InvalidArgumentException When a token violates the public contract.
	 */
	private static function assert_contract_token( string $value, int $max_length, string $pattern ): void {
		if ( strlen( $value ) > $max_length ) {
			throw new \InvalidArgumentException( 'A descriptor argument exceeds the supported length.' );
		}
		if ( ! preg_match( $pattern, $value ) ) {
			throw new \InvalidArgumentException( 'A descriptor argument is invalid.' );
		}
	}

	/**
	 * Validates that the requested public format is supported.
	 *
	 * @param string $text_format Public Integration format value.
	 *
	 * @throws \InvalidArgumentException When format is unsupported.
	 */
	private static function assert_supported_format( string $text_format ): void {
		if ( ! in_array( $text_format, Contract::text_formats(), true ) ) {
			throw new \InvalidArgumentException( 'text_format is invalid.' );
		}
	}

	/**
	 * Maps public Integration formats to the canonical internal hash path.
	 *
	 * @param string $text_format Public Integration format value.
	 * @return string
	 */
	private static function map_to_store_format( string $text_format ): string {
		return Contract::FORMAT_HTML === $text_format ? Store::FORMAT_HTML : Store::FORMAT_PLAIN;
	}
}
