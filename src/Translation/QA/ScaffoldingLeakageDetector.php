<?php
/**
 * Request-scoped scaffolding leakage detector (QD3/QD4/QD18/QD19) (TI.4).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Translation\QA;

/**
 * Marks present in target but not source — policy decides N/A vs severity.
 */
final class ScaffoldingLeakageDetector implements Detector {

	public const VERSION = '1';

	public const ID = 'scaffolding_leakage';

	/**
	 * Minimum marker length to avoid short-token false positives.
	 */
	private const MIN_MARKER_LENGTH = 8;

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return self::ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function version(): string {
		return self::VERSION;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param DetectionInput $input Detection input.
	 * @return list<RawFinding>
	 */
	public function detect( DetectionInput $input ): array {
		// Policy layer owns N/A when markers are not applicable.
		if ( ! $input->markers_applicable ) {
			return array();
		}

		if ( array() === $input->scaffolding_markers ) {
			return array();
		}

		$findings = array();
		foreach ( $input->scaffolding_markers as $marker ) {
			if ( ! is_string( $marker ) ) {
				continue;
			}
			$marker = trim( $marker );
			if ( mb_strlen( $marker ) < self::MIN_MARKER_LENGTH ) {
				continue;
			}
			if ( str_contains( $input->source_text, $marker ) ) {
				continue;
			}
			if ( ! str_contains( $input->target_text, $marker ) ) {
				continue;
			}

			$findings[] = new RawFinding(
				'qd3_scaffolding_leakage',
				self::VERSION,
				RawFinding::DIMENSION_LEAKAGE,
				'Request scaffolding marker leaked into the translation target.',
				array(
					'marker' => mb_substr( $marker, 0, 120 ),
				),
				array(
					'detector' => self::ID,
				)
			);
		}

		return $findings;
	}
}
