<?php
/**
 * Modular QA orchestrator (ADR-F11-008).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA;

use AIMultilingual\Workspace\QA\Checks\EmptyTranslationCheck;
use AIMultilingual\Workspace\QA\Checks\HTMLCheck;
use AIMultilingual\Workspace\QA\Checks\LengthRatioCheck;
use AIMultilingual\Workspace\QA\Checks\NumberCheck;
use AIMultilingual\Workspace\QA\Checks\PlaceholderCheck;
use AIMultilingual\Workspace\QA\Checks\PunctuationCheck;
use AIMultilingual\Workspace\QA\Checks\UnsupportedMarkupCheck;
use AIMultilingual\Workspace\QA\Checks\VariableCheck;
use AIMultilingual\Workspace\QA\Checks\WhitespaceCheck;

/**
 * Runs registered checks; never inspects origin/status/provider.
 */
final class QAEngine {

	/**
	 * Registered checks.
	 *
	 * @var list<QACheck>
	 */
	private array $checks;

	/**
	 * Whether error severity blocks save.
	 *
	 * @var bool
	 */
	private bool $block_on_error;

	/**
	 * Builds the engine.
	 *
	 * @param array<int, QACheck>|null $checks         Optional check list.
	 * @param bool                     $block_on_error Blocking policy.
	 */
	public function __construct( ?array $checks = null, bool $block_on_error = true ) {
		$this->checks         = array_values( $checks ?? self::default_checks() );
		$this->block_on_error = $block_on_error;
	}

	/**
	 * Default F11 check set.
	 *
	 * @return list<QACheck>
	 */
	public static function default_checks(): array {
		return array(
			new PlaceholderCheck(),
			new HTMLCheck(),
			new EmptyTranslationCheck(),
			new VariableCheck(),
			new WhitespaceCheck(),
			new NumberCheck(),
			new PunctuationCheck(),
			new UnsupportedMarkupCheck(),
			new LengthRatioCheck(),
		);
	}

	/**
	 * Registers an additional check without modifying existing ones.
	 *
	 * @param QACheck $check Check instance.
	 */
	public function register( QACheck $check ): void {
		$this->checks[] = $check;
	}

	/**
	 * Whether saves should be blocked when errors exist.
	 */
	public function blocks_on_error(): bool {
		return $this->block_on_error;
	}

	/**
	 * Evaluates one segment (content only).
	 *
	 * @param string $source_text Source text.
	 * @param string $target_text Target text.
	 * @param string $text_format Text format.
	 */
	public function evaluate( string $source_text, string $target_text, string $text_format ): QAResult {
		$issues = array();

		foreach ( $this->checks as $check ) {
			foreach ( $check->check( $source_text, $target_text, $text_format ) as $issue ) {
				$issues[] = $issue;
			}
		}

		return new QAResult( $issues );
	}

	/**
	 * Whether a result should block save under current policy.
	 *
	 * @param QAResult $result Evaluation result.
	 */
	public function should_block_save( QAResult $result ): bool {
		return $this->block_on_error && $result->has_errors();
	}
}
