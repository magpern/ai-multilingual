<?php
/**
 * TI.1 persist-path safety policy over raw findings (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

use AIMultilingual\Translation\AI\ResponseValidationResult;
use AIMultilingual\Translation\AI\ResponseValidator;

/**
 * Maps admitted blocking check ids to ResponseValidationResult codes.
 *
 * Does not rewrite ResponseValidator — Suite consumers call this adapter.
 */
final class PersistSafetyPolicy {

	/**
	 * Admitted TI.1 check ids that BLOCK persist.
	 *
	 * @var array<string, string>
	 */
	private const BLOCK_MAP = array(
		DeterministicDetectorSuite::CHECK_EMPTY_TARGET     => ResponseValidator::CODE_EMPTY_TARGET,
		DeterministicDetectorSuite::CHECK_PLACEHOLDER_LOSS => ResponseValidator::CODE_PLACEHOLDER_MISMATCH,
		DeterministicDetectorSuite::CHECK_HTML_TAG_LOSS    => ResponseValidator::CODE_HTML_MISMATCH,
		DeterministicDetectorSuite::CHECK_FORBIDDEN_MARKUP => ResponseValidator::CODE_FORBIDDEN_MARKUP,
		DeterministicDetectorSuite::CHECK_URL_LOSS         => ResponseValidator::CODE_URL_MISMATCH,
	);

	/**
	 * Evaluates raw findings for persist safety.
	 *
	 * Always returns a ResponseValidationResult: valid when no admitted blockers.
	 *
	 * @param array<int, RawFinding> $findings Raw findings.
	 */
	public function evaluate( array $findings ): ResponseValidationResult {
		foreach ( $findings as $finding ) {
			if ( ! $finding instanceof RawFinding ) {
				continue;
			}
			if ( ! isset( self::BLOCK_MAP[ $finding->check_id ] ) ) {
				continue;
			}

			$code = self::BLOCK_MAP[ $finding->check_id ];

			return ResponseValidationResult::fail(
				$code,
				$finding->message,
				array_merge(
					$finding->evidence,
					array(
						'check_id'      => $finding->check_id,
						'check_version' => $finding->check_version,
					)
				)
			);
		}

		return ResponseValidationResult::ok();
	}
}
