<?php
/**
 * Workspace QA check backed by shared deterministic detectors (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace\QA\Checks;

use AIMultilingual\Translation\QA\DetectionInput;
use AIMultilingual\Translation\QA\DeterministicQA;
use AIMultilingual\Translation\QA\WorkspaceQAPolicy;
use AIMultilingual\Workspace\QA\QACheck;
use AIMultilingual\Workspace\QA\QAIssue;

/**
 * Save-path check: markers_applicable=false so leakage stays empty at detector layer.
 */
final class SharedDetectorCheck implements QACheck {

	/**
	 * Shared detector orchestrator.
	 *
	 * @var DeterministicQA
	 */
	private DeterministicQA $qa;

	/**
	 * Workspace severity policy.
	 *
	 * @var WorkspaceQAPolicy
	 */
	private WorkspaceQAPolicy $policy;

	/**
	 * Builds the check.
	 *
	 * @param DeterministicQA|null   $qa     Optional orchestrator.
	 * @param WorkspaceQAPolicy|null $policy Optional policy.
	 */
	public function __construct( ?DeterministicQA $qa = null, ?WorkspaceQAPolicy $policy = null ) {
		$this->qa     = $qa ?? new DeterministicQA();
		$this->policy = $policy ?? new WorkspaceQAPolicy();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'shared_deterministic_qa';
	}

	/**
	 * {@inheritdoc}
	 */
	public function default_severity(): string {
		return QAIssue::SEVERITY_ERROR;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $source_text Source text.
	 * @param string $target_text Target text.
	 * @param string $text_format Text format.
	 * @return list<QAIssue>
	 */
	public function check( string $source_text, string $target_text, string $text_format ): array {
		$input = new DetectionInput(
			$source_text,
			$target_text,
			$text_format,
			array(),
			false
		);

		return $this->policy->apply( $this->qa->detect( $input ) );
	}
}
