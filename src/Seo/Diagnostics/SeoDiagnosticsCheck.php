<?php
/**
 * One SEO diagnostics check result (A.SEOf SF13).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Seo\Diagnostics;

/**
 * Immutable check row for the shared diagnostics result model.
 */
final class SeoDiagnosticsCheck {

	public const STATUS_PASS = 'pass';

	public const STATUS_WARNING = 'warning';

	public const STATUS_ERROR = 'error';

	public const STATUS_UNAVAILABLE = 'unavailable';

	public const STATUS_SKIPPED = 'skipped';

	/**
	 * Builds one check row.
	 *
	 * @param string               $id        Stable check id (e.g. sf3_hreflang).
	 * @param string               $status    One of STATUS_*.
	 * @param string               $ownership Ownership attribution token.
	 * @param string               $code      Low-cardinality machine code.
	 * @param string               $message   Operator-facing short message (no secrets).
	 * @param array<string, mixed> $evidence  Bounded evidence map.
	 * @throws \InvalidArgumentException When status is outside the frozen vocabulary.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $status,
		public readonly string $ownership,
		public readonly string $code,
		public readonly string $message,
		public readonly array $evidence = array(),
	) {
		if ( ! in_array( $status, self::statuses(), true ) ) {
			throw new \InvalidArgumentException( 'Invalid SEO diagnostics status.' );
		}
	}

	/**
	 * Frozen status vocabulary.
	 *
	 * @return array<int, string>
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_PASS,
			self::STATUS_WARNING,
			self::STATUS_ERROR,
			self::STATUS_UNAVAILABLE,
			self::STATUS_SKIPPED,
		);
	}

	/**
	 * Serializes the check for SF13 consumers.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'        => $this->id,
			'status'    => $this->status,
			'ownership' => $this->ownership,
			'code'      => $this->code,
			'message'   => $this->message,
			'evidence'  => $this->evidence,
		);
	}
}
