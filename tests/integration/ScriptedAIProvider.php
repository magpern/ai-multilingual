<?php
/**
 * Scripted AI provider for TI.1 structural-safety integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\AI\AIProviderInterface;
use AIMultilingual\Translation\AI\ProviderCapabilities;
use AIMultilingual\Translation\AI\ProviderResult;
use AIMultilingual\Translation\AI\TranslationBatch;
use WP_Error;

/**
 * Returns preconfigured ProviderResult or WP_Error for each translate_batch call.
 */
final class ScriptedAIProvider implements AIProviderInterface {

	/**
	 * @var list<ProviderResult|WP_Error|callable>
	 */
	private array $queue;

	/**
	 * @param list<ProviderResult|WP_Error|callable> $queue Scripted outcomes.
	 */
	public function __construct( array $queue = array() ) {
		$this->queue = $queue;
	}

	/**
	 * Enqueue another outcome.
	 *
	 * @param ProviderResult|WP_Error|callable $outcome Next batch outcome.
	 */
	public function enqueue( $outcome ): void {
		$this->queue[] = $outcome;
	}

	public function get_id(): string {
		return 'scripted';
	}

	public function get_capabilities(): ProviderCapabilities {
		return ProviderCapabilities::all();
	}

	public function test_connection() {
		return true;
	}

	public function list_models() {
		return array( 'scripted-1' );
	}

	public function translate_batch( TranslationBatch $batch ) {
		if ( array() === $this->queue ) {
			return new WP_Error( 'scripted_exhausted', 'No scripted provider outcomes left.' );
		}

		$next = array_shift( $this->queue );
		if ( is_callable( $next ) ) {
			return $next( $batch );
		}

		return $next;
	}
}
