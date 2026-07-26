<?php
/**
 * Composition root basics.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit;

use AIMultilingual\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Checks only what can be checked without WordPress: that the class autoloads
 * and that the singleton is one. Boot idempotence is asserted in the
 * integration guard suite, where hooks actually exist.
 */
final class PluginTest extends TestCase {

	public function test_instance_is_a_singleton(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}

	public function test_capability_is_namespaced(): void {
		$this->assertSame( 'aiml_translate', Plugin::CAPABILITY );
	}
}
