<?php
/**
 * Deterministic AI provider for Jobs integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\AI\AIProviderInterface;
use AIMultilingual\Translation\AI\ProviderCapabilities;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\AI\TranslationBatch;

/**
 * Echoes prefixed source text as machine translation.
 */
final class EchoAIProvider implements AIProviderInterface {

	public function get_id(): string {
		return 'echo';
	}

	public function get_capabilities(): ProviderCapabilities {
		return ProviderCapabilities::all();
	}

	public function test_connection() {
		return true;
	}

	public function list_models() {
		return array( 'echo-1' );
	}

	public function translate_batch( TranslationBatch $batch ) {
		$segments = array();
		foreach ( $batch->segments as $segment ) {
			$segments[] = array(
				'segment_key'     => $segment->segment_key,
				'translated_text' => 'SV: ' . $segment->source_text,
			);
		}

		return new ProviderResult( $segments, 5, 3, 'echo-1' );
	}
}
