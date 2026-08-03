<?php
/**
 * Null AI provider for unconfigured sites.
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
	 * Provider id.
	 */
	public const ID = 'null';

	/**
	 * Stable error code surfaced to REST and UI layers.
	 */
	public const ERROR_CODE = 'aiml_ai_not_configured';

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_capabilities(): ProviderCapabilities {
		return ProviderCapabilities::none();
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {
		return new WP_Error(
			self::ERROR_CODE,
			__( 'Automatic translation is not configured.', 'ai-multilingual' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * {@inheritdoc}
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
	 * {@inheritdoc}
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
