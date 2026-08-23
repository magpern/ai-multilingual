<?php
/**
 * Public Extension API v1 global helpers.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

use AIMultilingual\Extension\ExtensionServices;
use AIMultilingual\Extension\VisitorLanguageContext;

/**
 * Marks a source identity dirty for coalesced request-local sync.
 *
 * Validates admitted source only; never syncs immediately.
 * M5-A: also accepts activated chrome CPT sources under ownership/admission checks.
 *
 * @param string $source_type post|term.
 * @param int    $source_id   Source object id.
 */
function aiml_mark_source_dirty( string $source_type, int $source_id ): bool {
	return ExtensionServices::mark_source_dirty( $source_type, $source_id );
}

/**
 * Returns AIML’s current request language from URL/host resolution.
 *
 * Valid only after AIML request routing has established language context
 * (same window as visitor overlays — typically after late plugins_loaded
 * routing / request bootstrap). Returns null when AIML is inactive, too
 * early, or no language was resolved. Does not read cookies, geo, or
 * Accept-Language.
 *
 * @since 1.7.0
 */
function aiml_visitor_language(): ?VisitorLanguageContext {
	return ExtensionServices::visitor_language();
}
