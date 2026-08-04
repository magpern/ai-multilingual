<?php
/**
 * Builds configured AI providers from settings.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\Providers\OpenAIProvider;

/**
 * Composition helper for ProviderRegistry registration.
 */
final class ProviderFactory {

	/**
	 * Creates the OpenAI provider from settings when a key is present.
	 *
	 * @param Settings                   $settings Settings.
	 * @param CredentialVault            $vault    Credential vault.
	 * @param PromptProfileRegistry|null $profiles Profiles.
	 */
	public static function openai_from_settings(
		Settings $settings,
		CredentialVault $vault,
		?PromptProfileRegistry $profiles = null
	): OpenAIProvider {
		$data  = $settings->get();
		$key   = $vault->decrypt( (string) ( $data['ai_api_key_encrypted'] ?? '' ) );
		$model = (string) ( $data['ai_model'] ?? '' );

		return new OpenAIProvider( $key, $model, $profiles ?? new PromptProfileRegistry() );
	}
}
