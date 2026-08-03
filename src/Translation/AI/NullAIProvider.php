<?php
/**
 * Null AI provider for F10 workspace stub behavior.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

use WP_Error;

/**
 * Returns a stable not-configured error without external calls.
 */
final class NullAIProvider implements AIProviderInterface {

	/**
	 * Stable error code surfaced to REST and UI layers.
	 */
	public const ERROR_CODE = 'aiml_ai_not_configured';

	/**
	 * Builds the null provider.
	 */
	public function test_connection() {
		return new WP_Error(
			self::ERROR_CODE,
			__( 'Automatic translation is not configured.', 'ai-multilingual' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * Builds the null provider.
	 *
	 * @return array<int, string>|WP_Error
	 */
	public function list_models() {
		return new WP_Error(
			self::ERROR_CODE,
			__( 'Automatic translation is not configured.', 'ai-multilingual' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * Builds the null provider.
	 *
	 * @param TranslationBatch $batch Domain batch payload.
	 * @return ProviderResult|WP_Error
	 */
	public function translate_batch( TranslationBatch $batch ) {
		unset( $batch );

		return new WP_Error(
			self::ERROR_CODE,
			__( 'Automatic translation is not configured.', 'ai-multilingual' ),
			array( 'status' => 503 )
		);
	}
}
