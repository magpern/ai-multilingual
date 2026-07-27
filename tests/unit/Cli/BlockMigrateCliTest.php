<?php
/**
 * Strategy F block migration CLI guard tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;

/**
 * Ensures migration CLI requires an explicit selector.
 */
final class BlockMigrateCliTest extends TestCase {

	public function test_cli_command_is_registered_in_source(): void {
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/Cli.php'
		);

		$this->assertStringContainsString( "'aiml block migrate'", $source );
		$this->assertStringContainsString( 'Pass --post-id=<id> or --post-type=<type>.', $source );
	}

	public function test_no_automatic_migration_on_plugin_boot(): void {
		$plugin = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/Plugin.php'
		);

		$this->assertDoesNotMatchRegularExpression(
			'/register_activation_hook\s*\(/',
			$plugin
		);
		$this->assertDoesNotMatchRegularExpression(
			'/function init\(\).*migrate_post/s',
			$plugin
		);
		$this->assertStringNotContainsString( 'migrate_batch', $plugin );
	}
}
