<?php
/**
 * Loads TQ.0 versioned corpus fixtures.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

/**
 * Corpus loader for tests/quality/corpus/{version}.
 */
final class CorpusLoader {

	/**
	 * Absolute path to tests/quality.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * @param string|null $root Optional quality root.
	 */
	public function __construct( ?string $root = null ) {
		$this->root = $root ?? dirname( __DIR__ );
	}

	/**
	 * Loads corpus manifest + cases.
	 *
	 * @param string $version Corpus version id (e.g. C1.0).
	 * @return array{manifest: array<string,mixed>, cases: array<string,array<string,mixed>>, glossary: array<string,mixed>}
	 */
	public function load( string $version = 'C1.0' ): array {
		$dir      = $this->root . '/corpus/' . $version;
		$manifest = $this->read_json( $dir . '/manifest.json' );
		$glossary = $this->read_json( $dir . '/glossary.json' );
		$cases    = array();
		foreach ( (array) ( $manifest['case_ids'] ?? array() ) as $id ) {
			$id  = (string) $id;
			$row = $this->read_json( $dir . '/cases/' . $id . '.json' );
			if ( (string) ( $row['id'] ?? '' ) !== $id ) {
				throw new \RuntimeException( 'Case id mismatch for ' . $id );
			}
			$cases[ $id ] = $row;
		}
		if ( (int) ( $manifest['case_count'] ?? 0 ) !== count( $cases ) ) {
			throw new \RuntimeException( 'Corpus case_count does not match loaded cases.' );
		}
		return array(
			'manifest' => $manifest,
			'cases'    => $cases,
			'glossary' => $glossary,
		);
	}

	/**
	 * Quality root path.
	 */
	public function root(): string {
		return $this->root;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function read_json( string $path ): array {
		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException( 'Missing corpus file: ' . $path );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( 'Invalid JSON: ' . $path );
		}
		return $data;
	}
}
