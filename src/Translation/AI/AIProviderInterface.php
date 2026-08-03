<?php
/**
 * Domain contract for AI translation providers (ADR-0010).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

use WP_Error;

/**
 * Vendor-agnostic AI translation boundary.
 */
interface AIProviderInterface {

	/**
	 * Verifies provider credentials and connectivity.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection();

	/**
	 * Lists models available to the configured provider account.
	 *
	 * @return array<int, string>|WP_Error
	 */
	public function list_models();

	/**
	 * Translates a batch of workspace segments.
	 *
	 * @param TranslationBatch $batch Domain batch payload.
	 * @return ProviderResult|WP_Error
	 */
	public function translate_batch( TranslationBatch $batch );
}
