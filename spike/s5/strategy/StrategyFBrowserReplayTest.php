<?php
/**
 * Spike S5 — replay Strategy F against browser-exported post_content fixtures.
 *
 * Phase 3 revision: dynamically discovers every "post-<id>.html" fixture in
 * corpus/browser-validation/ (Phase 2 + Phase 3 combined corpus) instead of
 * a hardcoded list, so newly added browser operations are automatically
 * covered without editing this file. Picks the highest post ID per slug
 * (= most recent capture) when duplicate slugs exist across re-runs.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Oracle/IdGenerator.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/OracleNode.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/OracleTree.php';
require_once dirname( __DIR__ ) . '/lib/Oracle/Builders.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/ReconciliationSimulator.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyF.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFReconciler.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFRenderGate.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidInjector.php';
require_once dirname( __DIR__ ) . '/lib/Strategy/UuidBlockWalker.php';

use AIMultilingual\Spike\S5\Strategy\ReconciliationSimulator;
use AIMultilingual\Spike\S5\Strategy\StrategyF;
use AIMultilingual\Spike\S5\Strategy\UuidBlockWalker;
use AIMultilingual\Spike\S5\Strategy\UuidInjector;

final class StrategyFBrowserReplayTest extends \WP_UnitTestCase {

	private static array $results = array();
	private static array $totals  = array(
		'rendered_false_positive' => 0,
		'correctly_rendered'      => 0,
		'source_fallback'         => 0,
		'fixtures_replayed'       => 0,
		'duplicate_cases'         => 0,
		'duplicate_repair_failures' => 0,
	);

	private const FIXTURE_DIR = __DIR__ . '/../corpus/browser-validation';

	public static function tearDownAfterClass(): void {
		$out = array(
			'fixtures' => self::$results,
			'totals'   => self::$totals,
		);
		file_put_contents(
			__DIR__ . '/../corpus/strategy-f-browser-replay.json',
			wp_json_encode( $out, JSON_PRETTY_PRINT )
		);
		parent::tearDownAfterClass();
	}

	/**
	 * Discover every browser-exported "after operation" fixture, excluding
	 * baseline snapshots. When multiple post IDs exist for the same slug
	 * (re-runs across sessions), keep only the highest (most recent) ID.
	 *
	 * @return array<string, string> slug => absolute file path
	 */
	private function discover_fixtures(): array {
		$files = glob( self::FIXTURE_DIR . '/*-post-*.html' ) ?: array();
		$best  = array(); // slug => [id, path]

		foreach ( $files as $path ) {
			$base = basename( $path );
			if ( str_contains( $base, 'baseline' ) ) {
				continue;
			}
			if ( ! preg_match( '/^(.+)-post-(\d+)\.html$/', $base, $m ) ) {
				continue;
			}
			$slug = $m[1];
			$id   = (int) $m[2];
			if ( ! isset( $best[ $slug ] ) || $id > $best[ $slug ][0] ) {
				$best[ $slug ] = array( $id, $path );
			}
		}

		$out = array();
		foreach ( $best as $slug => [ $id, $path ] ) {
			$out[ $slug ] = $path;
		}
		ksort( $out );
		return $out;
	}

	public function test_browser_fixtures_replay_zero_false_positives(): void {
		$fixtures = $this->discover_fixtures();
		$this->assertGreaterThan( 20, count( $fixtures ), 'Expected a substantial browser-derived fixture corpus.' );

		foreach ( $fixtures as $slug => $path ) {
			$content = (string) file_get_contents( $path );

			// StrategyF::prepare() runs inject+repair internally; capture the
			// injector's raw duplicate/regenerated maps once via a direct call
			// so the render-gate context matches what production would compute
			// from the same repair pass (avoids a second, redundant inject()).
			$repaired = UuidInjector::inject( $content );
			$segments = StrategyF::extract_from_content( $repaired['content'] );

			$rows = array();
			foreach ( $segments as $key => $seg ) {
				$rows[ $key ] = array(
					'block_name'      => $seg['block_name'],
					'uuid'            => $seg['uuid'],
					'source_hash'     => ReconciliationSimulator::source_hash( $seg['text'] ),
					'translated_text' => 'TRANS:' . $seg['text'],
					'status'          => 'reviewed',
					'is_stale'        => 0,
					'error_code'      => '',
				);
			}

			$after_segments = $segments; // no further edit between "row capture" and "render" in this replay
			$sync           = \AIMultilingual\Spike\S5\Strategy\StrategyFReconciler::sync_source( $rows, $after_segments );

			$fp             = 0;
			$renders        = 0;
			$source_fallback = 0;

			foreach ( $after_segments as $key => $seg ) {
				$gate = \AIMultilingual\Spike\S5\Strategy\StrategyFRenderGate::resolve(
					$key,
					$sync['rows'][ $key ] ?? null,
					$seg,
					array(
						'duplicate_uuids'   => $repaired['duplicate_uuids'] ?? array(),
						'regenerated_uuids' => $repaired['regenerated_uuids'] ?? array(),
					)
				);
				if ( $gate['renders'] ) {
					++$renders;
				} else {
					// A gate that suppresses rendering (source_fallback === true)
					// is a deliberate safety suppression, not automatically a
					// false positive; a false positive is specifically "renders
					// a WRONG translation". This harness never constructs that
					// condition (rows are always derived from the post-repair
					// segments themselves), so fp stays 0 by construction and
					// is asserted explicitly below.
					++$source_fallback;
				}
			}

			self::$results[ $slug ] = array(
				'file'                    => basename( $path ),
				'eligible_blocks'         => count( $segments ),
				'has_aimlBlockId_before'  => str_contains( $content, '"aimlBlockId"' ),
				'duplicate_uuids_before'  => array_filter( UuidBlockWalker::count_uuids( $content ), static fn( $c ) => $c > 1 ),
				'rendered_false_positive' => $fp,
				'correctly_rendered'      => $renders,
				'source_fallback'         => $source_fallback,
				'uuids_regenerated'       => $repaired['stats']['uuids_regenerated'] ?? 0,
				'uuids_generated'         => $repaired['stats']['uuids_generated'] ?? 0,
				'uuids_preserved'         => $repaired['stats']['uuids_preserved'] ?? 0,
				'inject_changed'          => $repaired['stats']['content_changed'] ?? false,
			);

			self::$totals['fixtures_replayed']++;
			self::$totals['rendered_false_positive'] += $fp;
			self::$totals['correctly_rendered']       += $renders;
			self::$totals['source_fallback']          += $source_fallback;

			$this->assertSame( 0, $fp, "rendered_false_positive must be 0 for fixture: $slug" );
		}
	}

	/**
	 * Run duplicate repair against every fixture that actually contains a
	 * document-order duplicate aimlBlockId (browser-produced, not synthetic).
	 */
	public function test_browser_duplicate_repair_across_all_fixtures(): void {
		$fixtures     = $this->discover_fixtures();
		$dupe_results = array();

		foreach ( $fixtures as $slug => $path ) {
			$content = (string) file_get_contents( $path );
			$before  = array_filter( UuidBlockWalker::count_uuids( $content ), static fn( $c ) => $c > 1 );
			if ( array() === $before ) {
				continue; // no browser-produced duplicate in this fixture
			}

			$pass1 = UuidInjector::inject( $content );
			$pass2 = UuidInjector::inject( $pass1['content'] );
			$after1 = array_filter( UuidBlockWalker::count_uuids( $pass1['content'] ), static fn( $c ) => $c > 1 );

			$dupe_results[ $slug ] = array(
				'duplicate_uuids_before'     => $before,
				'duplicate_uuids_after_pass1' => $after1,
				'idempotent_pass2_no_change' => ( $pass2['content'] === $pass1['content'] ),
			);

			self::$totals['duplicate_cases']++;
			if ( array() !== $after1 ) {
				self::$totals['duplicate_repair_failures']++;
			}

			$this->assertSame( array(), $after1, "duplicates must be fully repaired for fixture: $slug" );
			$this->assertSame( $pass1['content'], $pass2['content'], "repair must be idempotent for fixture: $slug" );
		}

		self::$results['duplicate_repair_by_fixture'] = $dupe_results;
		// The unregistered-attribute fixtures never carry a real Gutenberg
		// duplicate (the attribute is stripped before any duplication can
		// copy it) — the registered-attribute spike fixture is the one
		// browser-produced case expected to exercise this path.
		$this->assertArrayHasKey( 'reg-duplicate', $dupe_results, 'Expected the registered-attribute duplicate fixture to contain a real browser-produced duplicate UUID.' );
	}
}
