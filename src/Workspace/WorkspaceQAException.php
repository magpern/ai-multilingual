<?php
/**
 * Raised when QA blocks a workspace save.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Workspace;

use AIMultilingual\Workspace\QA\QAResult;
use Exception;

/**
 * Carries QA result for REST 422 responses.
 */
final class WorkspaceQAException extends Exception {

	/**
	 * QA evaluation that blocked the save.
	 *
	 * @var QAResult
	 */
	private QAResult $qa;

	/**
	 * Builds the exception.
	 *
	 * @param QAResult $qa QA result.
	 */
	public function __construct( QAResult $qa ) {
		parent::__construct( 'Translation failed quality checks.', 422 );
		$this->qa = $qa;
	}

	/**
	 * Returns the QA result.
	 */
	public function qa(): QAResult {
		return $this->qa;
	}
}
