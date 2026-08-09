<?php
/**
 * SEO diagnostics snapshot (A.SEOf SF13).
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Seo\Diagnostics;

/**
 * Immutable read-only SEO health result shared by CLI/admin consumers.
 */
final class SeoDiagnosticsSnapshot {

	/**
	 * Builds a diagnostics snapshot.
	 *
	 * @param string             $generated_at ISO-8601 timestamp.
	 * @param string             $scope_path   Unprefixed path evaluated.
	 * @param string             $scope_url    Absolute URL when known.
	 * @param array              $checks       Check rows (SeoDiagnosticsCheck).
	 * @param array<string, int> $summary      Status tallies.
	 * @param array              $limitations  Honesty / environment flags.
	 * @param int                $elapsed_ms   Elapsed milliseconds.
	 * @param int                $http_fetches HTTP fetches performed.
	 */
	public function __construct(
		public readonly string $generated_at,
		public readonly string $scope_path,
		public readonly string $scope_url,
		public readonly array $checks,
		public readonly array $summary,
		public readonly array $limitations,
		public readonly int $elapsed_ms,
		public readonly int $http_fetches,
	) {
	}

	/**
	 * Serializes the snapshot for CLI/admin consumers.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$checks = array();
		foreach ( $this->checks as $check ) {
			$checks[] = $check->to_array();
		}

		return array(
			'generated_at' => $this->generated_at,
			'scope_path'   => $this->scope_path,
			'scope_url'    => $this->scope_url,
			'checks'       => $checks,
			'summary'      => $this->summary,
			'limitations'  => $this->limitations,
			'elapsed_ms'   => $this->elapsed_ms,
			'http_fetches' => $this->http_fetches,
			'model'        => 'aiml.seo_diagnostics.v1',
		);
	}
}
