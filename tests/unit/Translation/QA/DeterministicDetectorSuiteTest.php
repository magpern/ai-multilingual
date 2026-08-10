<?php
/**
 * DeterministicDetectorSuite unit tests (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\QA;

use AIMultilingual\Translation\QA\DetectionInput;
use AIMultilingual\Translation\QA\DeterministicDetectorSuite;
use AIMultilingual\Translation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Core structural detector coverage.
 */
final class DeterministicDetectorSuiteTest extends TestCase {

	private DeterministicDetectorSuite $suite;

	protected function setUp(): void {
		$this->suite = new DeterministicDetectorSuite();
	}

	/**
	 * Collects check ids from findings.
	 *
	 * @param array<int, \AIMultilingual\Translation\QA\RawFinding> $findings Findings.
	 * @return array<int, string>
	 */
	private function ids( array $findings ): array {
		return array_values(
			array_map(
				static fn( $f ): string => $f->check_id,
				$findings
			)
		);
	}

	public function test_empty_target(): void {
		$ids = $this->ids(
			$this->suite->detect( new DetectionInput( 'Hello world', '', Store::FORMAT_PLAIN ) )
		);

		$this->assertContains( DeterministicDetectorSuite::CHECK_EMPTY_TARGET, $ids );
	}

	public function test_placeholder_loss(): void {
		$ids = $this->ids(
			$this->suite->detect( new DetectionInput( 'Hello {name}', 'Hej', Store::FORMAT_PLAIN ) )
		);

		$this->assertContains( DeterministicDetectorSuite::CHECK_PLACEHOLDER_LOSS, $ids );
	}

	public function test_html_tag_loss(): void {
		$ids = $this->ids(
			$this->suite->detect(
				new DetectionInput(
					'<p>Hello <strong>world</strong></p>',
					'<p>Hej värld</p>',
					Store::FORMAT_HTML
				)
			)
		);

		$this->assertContains( DeterministicDetectorSuite::CHECK_HTML_TAG_LOSS, $ids );
	}

	public function test_url_loss(): void {
		$ids = $this->ids(
			$this->suite->detect(
				new DetectionInput(
					'See https://example.com/docs for help',
					'Se hjälpen',
					Store::FORMAT_PLAIN
				)
			)
		);

		$this->assertContains( DeterministicDetectorSuite::CHECK_URL_LOSS, $ids );
	}

	public function test_forbidden_markup(): void {
		$ids = $this->ids(
			$this->suite->detect(
				new DetectionInput(
					'<p>Safe</p>',
					'<p>Safe</p><script>alert(1)</script>',
					Store::FORMAT_HTML
				)
			)
		);

		$this->assertContains( DeterministicDetectorSuite::CHECK_FORBIDDEN_MARKUP, $ids );
	}

	public function test_length_ratio(): void {
		$source   = str_repeat( 'abcdefghij ', 5 ); // >20 chars.
		$target   = 'x';
		$findings = $this->suite->detect( new DetectionInput( $source, $target, Store::FORMAT_PLAIN ) );
		$ids      = $this->ids( $findings );

		$this->assertContains( DeterministicDetectorSuite::CHECK_LENGTH_RATIO, $ids );
		foreach ( $findings as $f ) {
			if ( DeterministicDetectorSuite::CHECK_LENGTH_RATIO === $f->check_id ) {
				$this->assertArrayHasKey( 'threshold_min', $f->detector_meta );
				$this->assertArrayHasKey( 'threshold_max', $f->detector_meta );
			}
		}
	}
}
