<?php
/**
 * Builds configured AI providers from settings.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI;

use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\Providers\DeepSeekProvider;
use AIMultilingual\Translation\AI\Providers\OpenAIProvider;

/**
 * Composition helper for ProviderRegistry registration.
 */
final class ProviderFactory {

	/**
	 * Creates the OpenAI provider from per-provider settings.
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
		$row = self::provider_row( $settings, OpenAIProvider::ID );

		return new OpenAIProvider(
			$vault->decrypt( (string) ( $row['api_key_encrypted'] ?? '' ) ),
			(string) ( $row['model'] ?? '' ),
			$profiles ?? new PromptProfileRegistry(),
			null,
			(float) ( $row['temperature'] ?? 0.2 ),
			(int) ( $row['max_tokens'] ?? 0 )
		);
	}

	/**
	 * Creates the DeepSeek provider from per-provider settings.
	 *
	 * @param Settings                   $settings Settings.
	 * @param CredentialVault            $vault    Credential vault.
	 * @param PromptProfileRegistry|null $profiles Profiles.
	 */
	public static function deepseek_from_settings(
		Settings $settings,
		CredentialVault $vault,
		?PromptProfileRegistry $profiles = null
	): DeepSeekProvider {
		$row = self::provider_row( $settings, DeepSeekProvider::ID );

		return new DeepSeekProvider(
			$vault->decrypt( (string) ( $row['api_key_encrypted'] ?? '' ) ),
			(string) ( $row['model'] ?? '' ),
			$profiles ?? new PromptProfileRegistry(),
			null,
			(float) ( $row['temperature'] ?? 1.0 ),
			(int) ( $row['max_tokens'] ?? 0 )
		);
	}

	/**
	 * Reads one sanitized provider settings row.
	 *
	 * @param Settings $settings    Settings.
	 * @param string   $provider_id Provider id.
	 * @return array{model?: string, api_key_encrypted?: string, temperature?: float, max_tokens?: int}
	 */
	private static function provider_row( Settings $settings, string $provider_id ): array {
		$data      = $settings->get();
		$providers = $data['ai_providers'] ?? array();
		if ( ! is_array( $providers ) ) {
			return Settings::default_provider_settings( $provider_id );
		}

		$row = $providers[ $provider_id ] ?? null;
		if ( ! is_array( $row ) ) {
			return Settings::default_provider_settings( $provider_id );
		}

		return $row;
	}
}
