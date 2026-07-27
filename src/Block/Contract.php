<?php
/**
 * Strategy F attribute and segment-key contract.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Block;

/**
 * Frozen Strategy F identity contract (ADR-0013 planning baseline).
 *
 * Segment keys use grammar {@see self::SEGMENT_KEY_GRAMMAR}: `b:<uuid>:<field>`.
 * Only {@see self::FIELD_CONTENT} is supported in the initial rollout; additional
 * field identifiers are reserved for future adapters without schema change.
 */
final class Contract {

	/**
	 * Gutenberg block attribute storing persistent block identity.
	 */
	public const ATTR_NAME = 'aimlBlockId';

	/**
	 * Segment key prefix (Strategy F).
	 */
	public const SEGMENT_KEY_PREFIX = 'b';

	/**
	 * Human-readable grammar documentation constant.
	 */
	public const SEGMENT_KEY_GRAMMAR = 'b:<uuid>:<field>';

	/**
	 * Initial supported translatable field identifier.
	 */
	public const FIELD_CONTENT = 'content';

	/**
	 * RFC 4122 version-4 UUID maximum serialized length.
	 */
	public const UUID_MAX_LENGTH = 36;

	/**
	 * RFC 4122 version-4 UUID pattern (lowercase hex with hyphens).
	 */
	public const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	/** Duplicate repair policy for same-document UUID collisions (F3). */
	public const REPAIR_POLICY_FIRST_WINS = 'first_wins';

	/**
	 * Fields supported by the initial Strategy F rollout.
	 *
	 * @var list<string>
	 */
	public const SUPPORTED_FIELDS = array(
		self::FIELD_CONTENT,
	);

	/**
	 * Block-editor attribute schema shared by PHP and JS registration.
	 *
	 * @return array{type: string}
	 */
	public static function attribute_definition(): array {
		return array(
			'type' => 'string',
		);
	}

	/**
	 * Whether a field identifier is supported in the current rollout.
	 *
	 * @param string $field Field identifier.
	 */
	public static function is_supported_field( string $field ): bool {
		return in_array( $field, self::SUPPORTED_FIELDS, true );
	}
}
