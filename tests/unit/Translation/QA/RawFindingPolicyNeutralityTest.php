<?php
/**
 * RawFinding stays policy-neutral (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\QA;

use AIMultilingual\Translation\QA\RawFinding;
use PHPUnit\Framework\TestCase;

/**
 * Ensures severity/owner/blocking_class never appear on raw findings.
 */
final class RawFindingPolicyNeutralityTest extends TestCase {

	public function test_to_array_has_no_policy_fields(): void {
		$finding = new RawFinding(
			'qd21_empty_target',
			'1',
			RawFinding::DIMENSION_STRUCTURAL,
			'Empty target.',
			array( 'x' => 1 ),
			array( 'y' => 2 )
		);

		$arr  = $finding->to_array();
		$keys = array_keys( $arr );

		$this->assertSame(
			array( 'check_id', 'check_version', 'dimension', 'message', 'evidence', 'detector_meta' ),
			$keys
		);
		$this->assertArrayNotHasKey( 'severity', $arr );
		$this->assertArrayNotHasKey( 'owner', $arr );
		$this->assertArrayNotHasKey( 'blocking_class', $arr );
	}
}
