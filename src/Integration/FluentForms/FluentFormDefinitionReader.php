<?php
/**
 * Read-only Fluent Forms form definition access.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Integration\FluentForms;

/**
 * Loads decoded form_fields JSON without mutating Fluent Forms persistence.
 */
interface FluentFormDefinitionReader {

	/**
	 * Decoded form_fields array for a form ID, or null when missing/unreadable.
	 *
	 * @param int $form_id Fluent Forms form ID.
	 * @return array<string, mixed>|null
	 */
	public function get_decoded_fields( int $form_id ): ?array;
}
