<?php
/**
 * TI.7 publication audit events (ADR-0020).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\Publication;

/**
 * Closed publication audit event catalog.
 */
final class PublicationAuditEvents {

	public const MANUAL           = 'publication_manual';
	public const AUTO             = 'publication_auto';
	public const SKIPPED          = 'publication_skipped';
	public const FAILED           = 'publication_failed';
	public const UNPUBLISH_MANUAL = 'unpublish_manual';
	public const INVALIDATED_BY_EDIT = 'publication_invalidated_by_edit';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::MANUAL,
			self::AUTO,
			self::SKIPPED,
			self::FAILED,
			self::UNPUBLISH_MANUAL,
			self::INVALIDATED_BY_EDIT,
		);
	}
}
