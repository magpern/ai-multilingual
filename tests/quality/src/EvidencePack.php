<?php
/**
 * TQ.0 evidence pack loader and writer.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

/**
 * Loads and saves immutable evidence pack directories.
 */
final class EvidencePack {

	public const FILE_MANIFEST      = 'manifest.json';
	public const FILE_GENERATIONS   = 'generations.jsonl';
	public const FILE_SCORES_PREFIX = 'scores.';
	public const FILE_HUMAN_PREFIX  = 'human.';
	public const FILE_REPORT        = 'REPORT.md';
	public const FILE_FINGERPRINTS  = 'fingerprints.json';

	/**
	 * @var string
	 */
	private string $path;

	/**
	 * @param string $path Absolute pack directory path.
	 */
	public function __construct( string $path ) {
		$this->path = rtrim( $path, '/' );
	}

	/**
	 * Pack directory path.
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * Loads manifest.json.
	 *
	 * @return array<string,mixed>
	 */
	public function load_manifest(): array {
		return $this->read_json( $this->path . '/' . self::FILE_MANIFEST );
	}

	/**
	 * Loads generations.jsonl as list of decoded rows.
	 *
	 * @return list<array<string,mixed>>
	 */
	public function load_generations(): array {
		$file = $this->path . '/' . self::FILE_GENERATIONS;
		if ( ! is_readable( $file ) ) {
			throw new \RuntimeException( 'Missing generations file: ' . $file );
		}
		$rows = array();
		$fh   = fopen( $file, 'rb' );
		if ( false === $fh ) {
			throw new \RuntimeException( 'Cannot read generations file: ' . $file );
		}
		while ( ( $line = fgets( $fh ) ) !== false ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$row = json_decode( $line, true );
			if ( ! is_array( $row ) ) {
				fclose( $fh );
				throw new \RuntimeException( 'Invalid JSONL line in ' . $file );
			}
			$rows[] = $row;
		}
		fclose( $fh );
		return $rows;
	}

	/**
	 * Loads scores file for a scorer version (e.g. H1.0).
	 *
	 * @param string $scorer_version Scorer version id.
	 * @return array<string,mixed>
	 */
	public function load_scores( string $scorer_version = DeterministicScorer::VERSION ): array {
		return $this->read_json( $this->path . '/' . self::FILE_SCORES_PREFIX . $scorer_version . '.json' );
	}

	/**
	 * Loads human review file (e.g. B1.0) when present.
	 *
	 * @param string $review_version Human review version id.
	 * @return array<string,mixed>|null
	 */
	public function load_human( string $review_version = 'B1.0' ): ?array {
		$file = $this->path . '/' . self::FILE_HUMAN_PREFIX . $review_version . '.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		return $this->read_json( $file );
	}

	/**
	 * Loads REPORT.md when present.
	 */
	public function load_report(): ?string {
		$file = $this->path . '/' . self::FILE_REPORT;
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$content = file_get_contents( $file );
		return false === $content ? null : $content;
	}

	/**
	 * Saves a complete evidence pack.
	 *
	 * @param array<string,mixed>       $manifest    Pack manifest.
	 * @param list<array<string,mixed>> $generations Generation rows.
	 * @param array<string,mixed>       $scores      Scores payload.
	 * @param array<string,mixed>|null  $human       Optional human reviews.
	 * @param string|null               $report      Optional REPORT.md body.
	 * @param string                    $scorer_version Scorer version for filename.
	 * @param string                    $human_version  Human review version for filename.
	 */
	public function save(
		array $manifest,
		array $generations,
		array $scores,
		?array $human = null,
		?string $report = null,
		string $scorer_version = DeterministicScorer::VERSION,
		string $human_version = 'B1.0'
	): void {
		if ( ! is_dir( $this->path ) && ! mkdir( $this->path, 0755, true ) && ! is_dir( $this->path ) ) {
			throw new \RuntimeException( 'Cannot create pack directory: ' . $this->path );
		}

		$this->write_json( $this->path . '/' . self::FILE_MANIFEST, $manifest );
		$this->write_jsonl( $this->path . '/' . self::FILE_GENERATIONS, $generations );
		$this->write_json( $this->path . '/' . self::FILE_SCORES_PREFIX . $scorer_version . '.json', $scores );

		if ( null !== $human ) {
			$this->write_json( $this->path . '/' . self::FILE_HUMAN_PREFIX . $human_version . '.json', $human );
		}
		if ( null !== $report ) {
			file_put_contents( $this->path . '/' . self::FILE_REPORT, $report );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function read_json( string $path ): array {
		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException( 'Missing pack file: ' . $path );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( 'Invalid JSON: ' . $path );
		}
		return $data;
	}

	/**
	 * @param string              $path JSON file path.
	 * @param array<string,mixed> $data JSON payload.
	 */
	private function write_json( string $path, array $data ): void {
		$encoded = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			$encoded = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		}
		if ( false === $encoded ) {
			throw new \RuntimeException( 'Failed to encode JSON: ' . $path );
		}
		file_put_contents( $path, $encoded . "\n" );
	}

	/**
	 * @param string                    $path JSONL file path.
	 * @param list<array<string,mixed>> $rows JSONL rows.
	 */
	private function write_jsonl( string $path, array $rows ): void {
		$lines = array();
		foreach ( $rows as $row ) {
			$encoded = wp_json_encode( $row, JSON_UNESCAPED_UNICODE );
			if ( false === $encoded ) {
				$encoded = json_encode( $row, JSON_UNESCAPED_UNICODE );
			}
			if ( false === $encoded ) {
				throw new \RuntimeException( 'Failed to encode JSONL row.' );
			}
			$lines[] = $encoded;
		}
		file_put_contents( $path, implode( "\n", $lines ) . ( array() !== $lines ? "\n" : '' ) );
	}
}
