<?php
/**
 * Frozen evidence integrity guard.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Quality;

/**
 * Detects unauthorized mutation of frozen evidence packs via SHA256 fingerprints.
 */
final class FrozenEvidenceGuard {

	/**
	 * Default files fingerprinted in an official pack.
	 *
	 * @var list<string>
	 */
	private const DEFAULT_FILES = array(
		EvidencePack::FILE_MANIFEST,
		EvidencePack::FILE_GENERATIONS,
	);

	/**
	 * Verifies pack files match expected fingerprints.
	 *
	 * Reads fingerprints.json in the pack when present; otherwise uses
	 * expected_fingerprints embedded in manifest.
	 *
	 * @param EvidencePack $pack Evidence pack.
	 * @return array{ok: bool, violations: list<array{file: string, expected: string, actual: string}>}
	 */
	public function verify( EvidencePack $pack ): array {
		$expected = $this->load_expected_fingerprints( $pack );
		if ( array() === $expected ) {
			return array(
				'ok'         => true,
				'violations' => array(),
			);
		}

		$violations = array();
		foreach ( $expected as $file => $hash ) {
			$path = $pack->path() . '/' . $file;
			if ( ! is_readable( $path ) ) {
				$violations[] = array(
					'file'     => $file,
					'expected' => $hash,
					'actual'   => 'missing',
				);
				continue;
			}
			$actual = hash_file( 'sha256', $path );
			if ( false === $actual || $actual !== $hash ) {
				$violations[] = array(
					'file'     => $file,
					'expected' => $hash,
					'actual'   => false === $actual ? 'unreadable' : $actual,
				);
			}
		}

		return array(
			'ok'         => array() === $violations,
			'violations' => $violations,
		);
	}

	/**
	 * Computes SHA256 fingerprints for pack files.
	 *
	 * @param EvidencePack      $pack  Evidence pack.
	 * @param array<int,string> $files Relative file names to fingerprint.
	 * @return array<string,string> file => sha256 hex.
	 */
	public function compute_fingerprints( EvidencePack $pack, array $files = self::DEFAULT_FILES ): array {
		$out = array();
		foreach ( $files as $file ) {
			$path = $pack->path() . '/' . $file;
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$hash = hash_file( 'sha256', $path );
			if ( false !== $hash ) {
				$out[ $file ] = $hash;
			}
		}
		return $out;
	}

	/**
	 * Writes fingerprints.json into a pack directory.
	 *
	 * @param EvidencePack           $pack  Evidence pack.
	 * @param array<int,string>|null $files Optional file list.
	 */
	public function write_fingerprints( EvidencePack $pack, ?array $files = null ): void {
		$files   = $files ?? self::DEFAULT_FILES;
		$data    = array(
			'algorithm'    => 'sha256',
			'generated_at' => gmdate( 'c' ),
			'files'        => $this->compute_fingerprints( $pack, $files ),
		);
		$path    = $pack->path() . '/' . EvidencePack::FILE_FINGERPRINTS;
		$encoded = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( false === $encoded ) {
			throw new \RuntimeException( 'Failed to encode fingerprints.' );
		}
		file_put_contents( $path, $encoded . "\n" );
	}

	/**
	 * @return array<string,string>
	 */
	private function load_expected_fingerprints( EvidencePack $pack ): array {
		$fingerprint_file = $pack->path() . '/' . EvidencePack::FILE_FINGERPRINTS;
		if ( is_readable( $fingerprint_file ) ) {
			$data = json_decode( (string) file_get_contents( $fingerprint_file ), true );
			if ( is_array( $data ) && isset( $data['files'] ) && is_array( $data['files'] ) ) {
				return array_map( 'strval', $data['files'] );
			}
		}

		try {
			$manifest = $pack->load_manifest();
		} catch ( \RuntimeException $e ) {
			return array();
		}

		$embedded = $manifest['expected_fingerprints'] ?? null;
		if ( is_array( $embedded ) ) {
			return array_map( 'strval', $embedded );
		}

		return array();
	}
}
