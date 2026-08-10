<?php
/**
 * Strategy F block status CLI guard tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;

/**
 * Ensures block status CLI is a thin presentation layer.
 */
final class BlockStatusCliTest extends TestCase {

	private function cli_source(): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/Cli.php'
		);
	}

	private function plugin_source(): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/Plugin.php'
		);
	}

	public function test_command_is_registered(): void {
		$source = $this->cli_source();

		$this->assertStringContainsString( "'aiml block status'", $source );
		$this->assertStringContainsString( 'Reports Strategy F block health (read-only).', $source );
	}

	public function test_help_text_documents_examples(): void {
		$source = $this->cli_source();

		$this->assertStringContainsString( 'wp aiml block status', $source );
		$this->assertStringContainsString( '--full-scan', $source );
		$this->assertStringContainsString( '--format=json', $source );
		$this->assertStringContainsString( '--sample-size=250', $source );
	}

	public function test_cli_delegates_to_block_health_service(): void {
		$source = $this->cli_source();

		$this->assertStringContainsString( '$health->scan( $options )', $source );
		$this->assertStringContainsString( 'BlockHealthScanOptions', $source );
		$this->assertStringNotContainsString( 'BlockMigrationEligibility::evaluate', $source );
		$this->assertStringNotContainsString( 'BlockIdentityAnalyzer', $source );
	}

	public function test_invalid_sample_size_is_rejected_in_cli(): void {
		$source = $this->cli_source();

		$this->assertStringContainsString( 'Invalid --sample-size value.', $source );
		$this->assertStringContainsString( 'BlockHealthScanOptions::MAX_SAMPLE_SIZE', $source );
	}

	public function test_unsupported_format_is_rejected_in_cli(): void {
		$source = $this->cli_source();

		$this->assertStringContainsString( 'Unsupported --format value. Use table or json.', $source );
	}

	public function test_json_output_uses_snapshot_to_array(): void {
		$source = $this->cli_source();

		$this->assertStringContainsString( '$snapshot->to_array()', $source );
		$this->assertStringContainsString( "'metrics' => \$metrics_snapshot->to_array()", $source );
	}

	public function test_table_output_includes_metrics_section(): void {
		$source = $this->cli_source();

		$this->assertStringContainsString( "\$sections['Metrics']", $source );
		$this->assertStringContainsString( 'metrics completeness', $source );
	}

	public function test_cli_delegates_metrics_to_aggregator_snapshot(): void {
		$source = $this->cli_source();

		$this->assertStringContainsString( '$metrics->snapshot()', $source );
	}

	public function test_duplicate_rows_display_when_not_detectable(): void {
		$source = $this->cli_source();

		$this->assertStringContainsString( 'N/A (UNIQUE constraint)', $source );
		$this->assertStringContainsString( 'duplicate_segment_rows_detectable', $source );
	}

	public function test_plugin_wires_block_health_service_for_cli_only(): void {
		$source = $this->plugin_source();

		$this->assertStringContainsString( 'new BlockMetricsAggregator', $source );
		$this->assertStringContainsString( 'new BlockHealthService', $source );
		$this->assertStringContainsString( '$metrics->register()', $source );
		$this->assertStringContainsString( 'Cli::register( $languages, $store, $extractor, $migration, $health, $metrics, $seo_diagnostics, $publication )', $source );
		$this->assertDoesNotMatchRegularExpression(
			'/BlockHealthService.*->scan\s*\(/s',
			$source
		);
	}

	public function test_no_automatic_health_scan_on_boot(): void {
		$source = $this->plugin_source();

		$this->assertDoesNotMatchRegularExpression(
			'/function init\(\).*block_status/s',
			$source
		);
	}
}
