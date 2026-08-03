<?php
/**
 * OpenAI Chat Completions provider (first ADR-0010 implementation).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI\Providers;

use AIMultilingual\Translation\AI\AIProviderInterface;
use AIMultilingual\Translation\AI\PromptProfileRegistry;
use AIMultilingual\Translation\AI\ProviderCapabilities;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\AI\TranslationBatch;
use WP_Error;

/**
 * Maps domain batches to OpenAI HTTP calls. Vendor shapes stay inside this class.
 */
final class OpenAIProvider implements AIProviderInterface {

	public const ID = 'openai';

	private const API_BASE = 'https://api.openai.com/v1';

	/**
	 * API key (decrypted).
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Default model id.
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * Prompt profile registry.
	 *
	 * @var PromptProfileRegistry
	 */
	private PromptProfileRegistry $profiles;

	/**
	 * Optional HTTP transport for tests: fn(string $method, string $url, array $args): array|WP_Error.
	 *
	 * @var callable|null
	 */
	private $http;

	/**
	 * Builds the provider.
	 *
	 * @param string                     $api_key  Decrypted API key.
	 * @param string                     $model    Model id.
	 * @param PromptProfileRegistry|null $profiles Profile registry.
	 * @param callable|null              $http     Optional HTTP transport.
	 */
	public function __construct(
		string $api_key,
		string $model = 'gpt-4o-mini',
		?PromptProfileRegistry $profiles = null,
		?callable $http = null
	) {
		$this->api_key  = trim( $api_key );
		$this->model    = '' !== trim( $model ) ? trim( $model ) : 'gpt-4o-mini';
		$this->profiles = $profiles ?? new PromptProfileRegistry();
		$this->http     = $http;
	}

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
		if ( '' === $this->api_key ) {
			return ProviderCapabilities::none();
		}

		return ProviderCapabilities::all();
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {
		if ( '' === $this->api_key ) {
			return new WP_Error(
				'aiml_ai_missing_key',
				__( 'API key is not configured.', 'ai-multilingual' ),
				array( 'status' => 503 )
			);
		}

		$models = $this->list_models();
		if ( $models instanceof WP_Error ) {
			return $models;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return array<int, string>|WP_Error
	 */
	public function list_models() {
		if ( '' === $this->api_key ) {
			return new WP_Error(
				'aiml_ai_missing_key',
				__( 'API key is not configured.', 'ai-multilingual' ),
				array( 'status' => 503 )
			);
		}

		$response = $this->request( 'GET', self::API_BASE . '/models', array() );
		if ( $response instanceof WP_Error ) {
			return $response;
		}

		$data = $response['data'] ?? null;
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'aiml_ai_invalid_response',
				__( 'Provider returned an unexpected model list.', 'ai-multilingual' ),
				array( 'status' => 502 )
			);
		}

		$ids = array();
		foreach ( $data as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) && is_string( $row['id'] ) ) {
				$ids[] = $row['id'];
			}
		}

		sort( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param TranslationBatch $batch Domain batch payload.
	 * @return ProviderResult|WP_Error
	 */
	public function translate_batch( TranslationBatch $batch ) {
		if ( '' === $this->api_key ) {
			return new WP_Error(
				'aiml_ai_missing_key',
				__( 'API key is not configured.', 'ai-multilingual' ),
				array( 'status' => 503 )
			);
		}

		$profile = $this->profiles->get( $batch->prompt_profile );
		$system  = null !== $profile
			? $profile->system_instructions
			: 'Translate the source text. Return only the translation.';

		$out_segments  = array();
		$input_tokens  = 0;
		$output_tokens = 0;

		foreach ( $batch->segments as $segment ) {
			$user = $this->build_user_prompt( $batch, $segment->source_text, $segment->existing_target );
			$body = array(
				'model'       => $this->model,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => $system,
					),
					array(
						'role'    => 'user',
						'content' => $user,
					),
				),
				'temperature' => 0.2,
			);

			$response = $this->request(
				'POST',
				self::API_BASE . '/chat/completions',
				$body
			);
			if ( $response instanceof WP_Error ) {
				return $response;
			}

			$text = $this->extract_message_text( $response );
			if ( null === $text ) {
				return new WP_Error(
					'aiml_ai_invalid_response',
					__( 'Provider returned an unexpected completion.', 'ai-multilingual' ),
					array( 'status' => 502 )
				);
			}

			$out_segments[] = array(
				'segment_key'     => $segment->segment_key,
				'translated_text' => $text,
			);

			$usage          = is_array( $response['usage'] ?? null ) ? $response['usage'] : array();
			$input_tokens  += (int) ( $usage['prompt_tokens'] ?? 0 );
			$output_tokens += (int) ( $usage['completion_tokens'] ?? 0 );
		}

		return new ProviderResult(
			$out_segments,
			$input_tokens,
			$output_tokens,
			$this->model
		);
	}

	/**
	 * Builds the user prompt for one segment.
	 *
	 * @param TranslationBatch $batch          Batch context.
	 * @param string           $source_text    Source text.
	 * @param string           $existing_target Existing target.
	 */
	private function build_user_prompt( TranslationBatch $batch, string $source_text, string $existing_target ): string {
		$parts = array(
			'Source locale: ' . $batch->source_locale,
			'Target locale: ' . $batch->target_locale,
			'Source text:',
			$source_text,
		);

		if ( '' !== $existing_target ) {
			$parts[] = 'Existing target text:';
			$parts[] = $existing_target;
		}

		if ( array() !== $batch->constraints ) {
			$parts[] = 'Constraints: ' . implode( ', ', $batch->constraints );
		}

		return implode( "\n", $parts );
	}

	/**
	 * Extracts assistant message text from a chat completion payload.
	 *
	 * @param array<string, mixed> $response Decoded JSON.
	 */
	private function extract_message_text( array $response ): ?string {
		$choices = $response['choices'] ?? null;
		if ( ! is_array( $choices ) || array() === $choices ) {
			return null;
		}

		$first = $choices[0];
		if ( ! is_array( $first ) ) {
			return null;
		}

		$message = $first['message'] ?? null;
		if ( ! is_array( $message ) ) {
			return null;
		}

		$content = $message['content'] ?? null;

		return is_string( $content ) ? trim( $content ) : null;
	}

	/**
	 * Performs an authenticated OpenAI HTTP call.
	 *
	 * @param string               $method GET|POST.
	 * @param string               $url    Absolute URL.
	 * @param array<string, mixed> $body   JSON body for POST.
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $method, string $url, array $body ) {
		$args = array(
			'method'  => $method,
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
		);

		if ( 'POST' === $method ) {
			$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $body ) : false;
			if ( false === $encoded || null === $encoded ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Fallback when WP helpers are unavailable (unit tests).
				$encoded = (string) json_encode( $body );
			}
			$args['body'] = $encoded;
		}

		if ( null !== $this->http ) {
			$response = ( $this->http )( $method, $url, $args );
		} else {
			$response = wp_remote_request( $url, $args );
		}

		if ( $response instanceof WP_Error ) {
			return $response;
		}

		if ( ! is_array( $response ) ) {
			return new WP_Error(
				'aiml_ai_http_error',
				__( 'Provider HTTP request failed.', 'ai-multilingual' ),
				array( 'status' => 502 )
			);
		}

		$code = (int) ( $response['response']['code'] ?? 0 );
		$raw  = (string) ( $response['body'] ?? '' );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: __( 'Provider request failed.', 'ai-multilingual' );

			return new WP_Error(
				'aiml_ai_http_error',
				$message,
				array(
					'status' => $code > 0 ? $code : 502,
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'aiml_ai_invalid_response',
				__( 'Provider returned non-JSON content.', 'ai-multilingual' ),
				array( 'status' => 502 )
			);
		}

		return $data;
	}
}
