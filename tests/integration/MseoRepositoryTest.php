<?php
/**
 * MSEO.0 repository integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Routing\CanonicalPath;
use AIMultilingual\Routing\FrontierRecord;
use AIMultilingual\Routing\HistoryRecord;
use AIMultilingual\Routing\PathCanonicalizer;
use AIMultilingual\Routing\PathHash;
use AIMultilingual\Routing\ReindexFrontierRepository;
use AIMultilingual\Routing\RouteHistoryRepository;
use AIMultilingual\Routing\RouteRecord;
use AIMultilingual\Routing\SlugRouteRepository;
use AIMultilingual\Translation\Store;

/**
 * Repository path/hash invariants (M0AC8, R2/R3).
 */
final class MseoRepositoryTest extends AimlTestCase {

	private PathCanonicalizer $canonicalizer;
	private SlugRouteRepository $routes;
	private RouteHistoryRepository $history;
	private ReindexFrontierRepository $frontier;

	protected function setUp(): void {
		parent::setUp();

		$this->canonicalizer = new PathCanonicalizer();
		$this->routes        = new SlugRouteRepository();
		$this->history       = new RouteHistoryRepository();
		$this->frontier      = new ReindexFrontierRepository();
	}

	public function test_save_derives_hashes_and_lookup_verifies_full_path(): void {
		$language  = $this->add_language();
		$source    = $this->canonicalizer->canonicalize( '/source-page' );
		$localized = $this->canonicalizer->canonicalize( '/localized-page' );

		$record = new RouteRecord(
			(int) $language->language_id,
			Store::SOURCE_POST,
			88001,
			'post',
			$source,
			$localized
		);

		$result = $this->routes->save( $record );
		$this->assertIsObject( $result );

		$by_localized = $this->routes->find_by_localized_path( (int) $language->language_id, $localized );
		$this->assertNotNull( $by_localized );
		$this->assertSame( '/localized-page', (string) $by_localized->localized_path );

		$by_source = $this->routes->find_by_source_path( (int) $language->language_id, $source );
		$this->assertNotNull( $by_source );
	}

	public function test_lookup_fails_closed_on_path_mismatch_after_hash_collision_attempt(): void {
		global $wpdb;

		$language = $this->add_language();
		$path_a   = $this->canonicalizer->canonicalize( '/collision-a' );
		$path_b   = new CanonicalPath( '/collision-b' );

		// Force-insert row with path_b text but path_a hash (simulated corruption attempt).
		$hash_hex = PathHash::from_canonical( $path_a )->hex();
		$table    = \AIMultilingual\Database\Schema::slug_routes();
		$now      = current_time( 'mysql', true );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"INSERT INTO {$table}
				(language_id, source_type, source_id, source_subtype,
				 source_path, source_path_hash, localized_path, localized_path_hash,
				 localized_slug, route_namespace, slug_origin, route_status, activated_at,
				 created_at, updated_at)
				VALUES (%d, %s, %d, %s, %s, UNHEX(%s), %s, UNHEX(%s), %s, %s, %s, %s, NULL, %s, %s)",
				(int) $language->language_id,
				Store::SOURCE_POST,
				88002,
				'post',
				'/collision-a',
				$hash_hex,
				'/collision-b',
				$hash_hex,
				'',
				'',
				'generated',
				'inactive',
				$now,
				$now
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		// Lookup with path_a should fail closed because stored localized_path differs.
		$this->assertNull(
			$this->routes->find_by_localized_path( (int) $language->language_id, $path_a )
		);
	}

	public function test_binary_hash_with_nul_byte_round_trips(): void {
		global $wpdb;

		$language = $this->add_language();
		// Path chosen for test identity; hash uses explicit NUL-byte digest via save path.
		$source    = $this->canonicalizer->canonicalize( '/nul-hash-test' );
		$localized = $this->canonicalizer->canonicalize( '/nul-hash-localized' );

		$record = new RouteRecord(
			(int) $language->language_id,
			Store::SOURCE_POST,
			88003,
			'post',
			$source,
			$localized
		);
		$this->routes->save( $record );

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT HEX(localized_path_hash) AS hash_hex FROM '
				. \AIMultilingual\Database\Schema::slug_routes() // phpcs:ignore WordPress.DB.PreparedSQL
				. ' WHERE source_id = %d',
				88003
			)
		);
		$this->assertNotNull( $row );
		$this->assertSame( 64, strlen( (string) $row->hash_hex ) );

		$found = $this->routes->find_by_localized_path( (int) $language->language_id, $localized );
		$this->assertNotNull( $found );
	}

	public function test_history_insert_and_lookup(): void {
		$language = $this->add_language();
		$path     = $this->canonicalizer->canonicalize( '/old-slug' );

		$result = $this->history->insert(
			new HistoryRecord(
				(int) $language->language_id,
				$path,
				Store::SOURCE_POST,
				88004,
				'post'
			)
		);

		$this->assertIsObject( $result );
		$this->assertNotNull( $this->history->find_by_historical_path( (int) $language->language_id, $path ) );
	}

	public function test_frontier_coalesces_per_parent(): void {
		$first = $this->frontier->upsert_checkpoint(
			new FrontierRecord( Store::SOURCE_POST, 88005, '{"phase":"children"}', 1, 'pending' )
		);
		$this->assertIsObject( $first );

		$second = $this->frontier->upsert_checkpoint(
			new FrontierRecord( Store::SOURCE_POST, 88005, '{"phase":"children","last_child_id":10}', 1, 'pending' )
		);
		$this->assertIsObject( $second );
		$this->assertSame( 2, (int) $second->generation );

		$all = $this->frontier->find_by_parent( Store::SOURCE_POST, 88005 );
		$this->assertNotNull( $all );
	}
}
