<?php
/**
 * OpenAI Chat Completions provider (first ADR-0010 implementation).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\AI\Providers;

use AIMultilingual\Translation\AI\AIProviderInterface;
use AIMultilingual\Translation\AI\ContextItem;
use AIMultilingual\Translation\AI\PromptProfileRegistry;
use AIMultilingual\Translation\AI\ProviderCapabilities;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\AI\TranslationBatch;
use AIMultilingual\Translation\QA\ScaffoldingMarkerSource;
use WP_Error;

/**
 * Maps domain batches to OpenAI HTTP calls. Vendor shapes stay inside this class.
 */
final class OpenAIProvider implements AIProviderInterface, ScaffoldingMarkerSource {

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
				__( 'API key is not configured.', 'universal-multilingual' ),
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
				__( 'API key is not configured.', 'universal-multilingual' ),
				array( 'status' => 503 )
			);
		}

		$response = $this->request( 'GET', self::API_BASE . '/models', array() );
		if ( $response instanceof WP_Error ) {
			$error_data                          = $response->get_error_data();
			$error_data                          = is_array( $error_data ) ? $error_data : array();
			$error_data['provider_request_made'] = true;
			$error_data['provider_requests']     = 1;
			$response->add_data( $error_data );
			return $response;
		}

		$data = $response['data'] ?? null;
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'aiml_ai_invalid_response',
				__( 'Provider returned an unexpected model list.', 'universal-multilingual' ),
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
				__( 'API key is not configured.', 'universal-multilingual' ),
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
				'model'    => $this->model,
				'messages' => array(
					array(
						'role'    => 'system',
						'content' => $system,
					),
					array(
						'role'    => 'user',
						'content' => $user,
					),
				),
			);

			if ( $this->supports_temperature( $this->model ) ) {
				$body['temperature'] = 0.2;
			}

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
					__( 'Provider returned an unexpected completion.', 'universal-multilingual' ),
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
	 * Bounded framing prefixes actually included for this batch (TI.4).
	 *
	 * Returns short marker strings — not the full prompt body.
	 *
	 * @param TranslationBatch $batch Provider batch.
	 * @return list<string>
	 */
	public function scaffolding_markers_for_batch( TranslationBatch $batch ): array {
		$markers = array(
			'Source locale: ',
			'Target locale: ',
			'---',
			'Translate only the following source text. Return only the translation of this source text:',
		);

		$context = $batch->context;
		if ( null !== $context ) {
			$markers[] = 'Field role: ';
			if ( '' !== $context->content_purpose() ) {
				$markers[] = 'Content purpose: ';
			}
			if ( '' !== $context->object_type ) {
				$markers[] = 'Object type: ';
			}
			if ( '' !== $context->object_title ) {
				$markers[] = 'Object title: ';
			}
			foreach ( $context->items as $item ) {
				if ( ContextItem::TYPE_TM_EXAMPLE === $item->type ) {
					$markers[] = 'Prior approved translation example (instruction only — do not copy blindly; subordinate to current source and glossary): ';
					continue;
				}
				$markers[] = 'Context [';
			}
		}

		if ( '' !== trim( $batch->glossary_fragment ) ) {
			$markers[] = 'Glossary instructions (terminology guidance only — do not copy into the translation output):';
		}

		$has_existing = false;
		foreach ( $batch->segments as $segment ) {
			if ( '' !== $segment->existing_target ) {
				$has_existing = true;
				break;
			}
		}
		if ( $has_existing ) {
			$markers[] = 'Existing target text:';
		}

		if ( array() !== $batch->constraints ) {
			$markers[] = 'Constraints: ';
		}

		return array_values( array_unique( $markers ) );
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
		);

		$context = $batch->context;
		if ( null !== $context ) {
			$parts[] = 'Field role: ' . $context->field_semantic;
			$purpose = $context->content_purpose();
			if ( '' !== $purpose ) {
				$parts[] = 'Content purpose: ' . $purpose;
			}
			if ( '' !== $context->object_type ) {
				$parts[] = 'Object type: ' . $context->object_type;
			}
			if ( '' !== $context->object_title ) {
				$parts[] = 'Object title: ' . $context->object_title;
			}
			foreach ( $context->items as $item ) {
				if ( ContextItem::TYPE_TM_EXAMPLE === $item->type ) {
					$label   = ( null !== $item->label && '' !== $item->label ) ? ( $item->label . ': ' ) : '';
					$parts[] = 'Prior approved translation example (instruction only — do not copy blindly; subordinate to current source and glossary): ' . $label . $item->value;
					continue;
				}
				$label   = ( null !== $item->label && '' !== $item->label ) ? ( $item->label . '=' ) : '';
				$parts[] = 'Context [' . $item->type . ']: ' . $label . $item->value;
			}
		}

		if ( '' !== trim( $batch->glossary_fragment ) ) {
			$parts[] = 'Glossary instructions (terminology guidance only — do not copy into the translation output):';
			$parts[] = $batch->glossary_fragment;
		}

		if ( '' !== $existing_target ) {
			$parts[] = 'Existing target text:';
			$parts[] = $existing_target;
		}

		if ( array() !== $batch->constraints ) {
			$parts[] = 'Constraints: ' . implode( ', ', $batch->constraints );
		}

		$parts[] = '---';
		$parts[] = 'Translate only the following source text. Return only the translation of this source text:';
		$parts[] = $source_text;

		return implode( "\n", $parts );
	}

	/**
	 * Whether the model accepts a non-default temperature parameter.
	 *
	 * GPT-5* and o-series chat models reject custom temperature values.
	 *
	 * @param string $model Model id.
	 */
	private function supports_temperature( string $model ): bool {
		$id = strtolower( trim( $model ) );
		if ( '' === $id ) {
			return true;
		}

		if ( str_starts_with( $id, 'gpt-5' ) || preg_match( '/^o[0-9]/', $id ) ) {
			return false;
		}

		return true;
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
				__( 'Provider HTTP request failed.', 'universal-multilingual' ),
				array(
					'status'                => 502,
					'provider_request_made' => true,
					'provider_requests'     => 1,
				)
			);
		}

		$code = (int) ( $response['response']['code'] ?? 0 );
		$raw  = (string) ( $response['body'] ?? '' );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message     = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: __( 'Provider request failed.', 'universal-multilingual' );
			$error_data  = array(
				'status'                => $code > 0 ? $code : 502,
				'provider_request_made' => true,
				'provider_requests'     => 1,
			);
			$retry_after = $this->retry_after_seconds( $response );
			if ( $retry_after > 0 ) {
				$error_data['retry_after'] = $retry_after;
			}

			return new WP_Error(
				429 === $code ? 'aiml_rate_limited' : 'aiml_ai_http_error',
				$message,
				$error_data
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'aiml_ai_invalid_response',
				__( 'Provider returned non-JSON content.', 'universal-multilingual' ),
				array(
					'status'                => 502,
					'provider_request_made' => true,
					'provider_requests'     => 1,
				)
			);
		}

		return $data;
	}

	/**
	 * Parse provider backoff headers into bounded whole seconds.
	 *
	 * @param array<string, mixed> $response WordPress HTTP response.
	 */
	private function retry_after_seconds( array $response ): int {
		$retry_after = $this->response_header( $response, 'retry-after' );
		if ( '' !== $retry_after ) {
			if ( ctype_digit( $retry_after ) ) {
				return max( 0, (int) $retry_after );
			}

			$at = strtotime( $retry_after );
			if ( false !== $at ) {
				return max( 0, $at - time() );
			}
		}

		$milliseconds = $this->response_header( $response, 'retry-after-ms' );
		if ( is_numeric( $milliseconds ) ) {
			return max( 0, (int) ceil( (float) $milliseconds / 1000 ) );
		}

		$reset = strtolower( $this->response_header( $response, 'x-ratelimit-reset-requests' ) );
		if ( preg_match( '/^([0-9]+(?:\.[0-9]+)?)(ms|s)$/', $reset, $matches ) ) {
			$seconds = 'ms' === $matches[2] ? (float) $matches[1] / 1000 : (float) $matches[1];
			return max( 0, (int) ceil( $seconds ) );
		}

		return 0;
	}

	/**
	 * Read one response header from array or WP_HTTP_Headers shapes.
	 *
	 * @param array<string, mixed> $response WordPress HTTP response.
	 * @param string               $name     Lowercase header name.
	 */
	private function response_header( array $response, string $name ): string {
		$headers = $response['headers'] ?? array();
		if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
			$value = $headers->offsetGet( $name );
			return is_scalar( $value ) ? trim( (string) $value ) : '';
		}
		if ( ! is_array( $headers ) ) {
			return '';
		}

		foreach ( $headers as $key => $value ) {
			if ( strtolower( (string) $key ) === $name && is_scalar( $value ) ) {
				return trim( (string) $value );
			}
		}

		return '';
	}
}
