<?php
/**
 * Glossary audit event name catalog.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Glossary;

/**
 * Frozen glossary audit event identifiers.
 */
final class GlossaryAuditEvents {

	public const TERM_CREATED     = 'glossary_term_created';
	public const TERM_UPDATED     = 'glossary_term_updated';
	public const TERM_ACTIVATED   = 'glossary_term_activated';
	public const TERM_DEACTIVATED = 'glossary_term_deactivated';
	public const TERM_DELETED     = 'glossary_term_deleted';
	public const BULK_CHANGED     = 'glossary_bulk_changed';
}
