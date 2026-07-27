<?php
/**
 * Strategy F block status CLI integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\BlockHealthScanOptions;
use AIMultilingual\Block\BlockHealthService;
use AIMultilingual\Block\BlockHealthSnapshot;
use AIMultilingual\Block\BlockIdentityAnalyzer;
use AIMultilingual\Block\BlockRegistry;
use AIMultilingual\Block\Contract;
use AIMultilingual\Cli;
use AIMultilingual\Tests\Integration\Support\WpCliTestDouble;
use ReflectionMethod;

require_once __DIR__ . '/support/WpCliStubs.php';

/**
 * Exercises CLI option parsing and output paths against live WordPress data.
 */
final class BlockStatusCliIntegrationTest extends AimlTestCase {

	private BlockHealthService $health;

	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( 1 );

		$this->health = new BlockHealthService(
			$this->store,
			$this->extractor,
			new BlockIdentityAnalyzer( new BlockRegistry() )
		);

		WpCliTestDouble::reset();
	}

	public function test_default_invocation_produces_sample_snapshot(): void {
		$this->create_compliant_page( 'CLI default' );

		$snapshot = $this->invoke_status( array() );

		$this->assertSame( BlockHealthSnapshot::SCAN_MODE_SAMPLE, $snapshot->scan_mode );
		$this->assertSame( BlockHealthScanOptions::DEFAULT_SAMPLE_SIZE, $snapshot->requested_sample_size );
		$this->assertGreaterThanOrEqual( 0, $snapshot->elapsed_ms );
	}

	public function test_sample_size_parsing(): void {
		$snapshot = $this->invoke_status( array( 'sample-size' => '250' ) );

		$this->assertSame( 250, $snapshot->requested_sample_size );
	}

	public function test_invalid_sample_size_exits_with_error(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Invalid --sample-size value.' );

		$this->invoke_status( array( 'sample-size' => '5000' ) );
	}

	public function test_full_scan_option(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->create_compliant_page( 'CLI full ' . $i );
		}

		$snapshot = $this->invoke_status(
			array(
				'full-scan'   => true,
				'source-type' => 'page',
			)
		);

		$this->assertSame( BlockHealthSnapshot::SCAN_MODE_FULL, $snapshot->scan_mode );
		$this->assertGreaterThanOrEqual( 3, $snapshot->scanned_post_count );
	}

	public function test_json_output_serializes_snapshot_array(): void {
		$this->create_compliant_page( 'CLI json' );

		$this->invoke_status(
			array(
				'format'      => 'json',
				'source-type' => 'page',
			)
		);

		$printed = WpCliTestDouble::$printed;
		$this->assertIsString( $printed );
		$decoded = json_decode( $printed, true );
		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'generated_at', $decoded );
		$this->assertArrayHasKey( 'scanned_post_count', $decoded );
		$this->assertArrayNotHasKey( 'post_results', $decoded );
	}

	public function test_table_output_includes_sections(): void {
		$this->create_compliant_page( 'CLI table' );

		$this->invoke_status( array( 'source-type' => 'page' ) );

		$output = implode( "\n", WpCliTestDouble::$logs );
		$this->assertStringContainsString( 'Health', $output );
		$this->assertStringContainsString( 'UUID', $output );
		$this->assertStringContainsString( 'Segments', $output );
		$this->assertStringContainsString( 'Status', $output );
		$this->assertStringContainsString( 'N/A (UNIQUE constraint)', $output );
	}

	public function test_source_id_filtering(): void {
		$post = $this->create_compliant_page( 'CLI scoped' );

		$snapshot = $this->invoke_status( array( 'source-id' => (string) $post->ID ) );

		$this->assertSame( 1, $snapshot->scanned_post_count );
		$this->assertSame( 1, $snapshot->eligible_post_count );
	}

	public function test_source_type_filtering(): void {
		$this->create_compliant_page( 'CLI page type' );

		$snapshot = $this->invoke_status(
			array(
				'source-type' => 'page',
				'full-scan'   => true,
			)
		);

		$this->assertGreaterThanOrEqual( 1, $snapshot->scanned_post_count );
	}

	public function test_unsupported_format_exits_with_error(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Unsupported --format value.' );

		$this->invoke_status( array( 'format' => 'yaml' ) );
	}

	public function test_service_invocation_performs_no_writes(): void {
		global $wpdb;

		$this->create_compliant_page( 'CLI no writes' );
		$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . \AIMultilingual\Database\Schema::translations() ); // phpcs:ignore WordPress.DB

		$this->invoke_status(
			array(
				'full-scan'   => true,
				'source-type' => 'page',
			)
		);

		$after = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . \AIMultilingual\Database\Schema::translations() ); // phpcs:ignore WordPress.DB

		$this->assertSame( $before, $after );
	}

	public function test_recoverable_health_issues_do_not_raise_cli_error(): void {
		$snapshot = $this->invoke_status( array( 'source-type' => 'page' ) );

		$this->assertNull( WpCliTestDouble::$error );
		$this->assertInstanceOf( BlockHealthSnapshot::class, $snapshot );
	}

	public function test_block_status_options_match_service_defaults(): void {
		$options = $this->parse_status_options( array() );

		$this->assertSame( BlockHealthScanOptions::DEFAULT_SAMPLE_SIZE, $options->sample_size );
		$this->assertFalse( $options->full_scan );
	}

	/**
	 * Invokes the private CLI handler and returns the produced snapshot.
	 *
	 * @param array<string, mixed> $assoc CLI associative arguments.
	 * @throws \RuntimeException When CLI validation fails.
	 */
	private function invoke_status( array $assoc ): BlockHealthSnapshot {
		$options = $this->parse_status_options( $assoc );

		try {
			$method = new ReflectionMethod( Cli::class, 'block_status' );
			$method->setAccessible( true );
			$method->invoke( null, $this->health, $assoc );
		} catch ( \RuntimeException $exception ) {
			throw $exception;
		}

		return $this->health->scan( $options );
	}

	/**
	 * @param array<string, mixed> $assoc CLI associative arguments.
	 */
	private function parse_status_options( array $assoc ): BlockHealthScanOptions {
		$method = new ReflectionMethod( Cli::class, 'block_status_options' );
		$method->setAccessible( true );

		/** @var BlockHealthScanOptions */
		return $method->invoke( null, $assoc );
	}

	private function create_compliant_page( string $label ): \WP_Post {
		static $index = 0;
		++$index;

		$uuids = array(
			'550e8400-e29b-41d4-a716-446655440000',
			'6ba7b810-9dad-41d1-80b4-00c04fd430c8',
			'7c9e6679-7425-40de-944b-e07fc1f90ae7',
		);

		return $this->create_page(
			$label,
			sprintf(
				'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
				Contract::ATTR_NAME,
				$uuids[ ( $index - 1 ) % count( $uuids ) ],
				$label
			)
		);
	}
}
