<?php
/**
 * PromptProfileRegistry unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Translation\AI;

use AIMultilingual\Translation\AI\PromptProfileRegistry;
use PHPUnit\Framework\TestCase;

/**
 * F11 WP4 — six canonical profiles.
 */
final class PromptProfileRegistryTest extends TestCase {

	public function test_six_profiles_are_defined(): void {
		$registry = new PromptProfileRegistry();
		$ids      = PromptProfileRegistry::ids();

		$this->assertCount( 6, $ids );
		$this->assertSame(
			array(
				PromptProfileRegistry::TRANSLATE,
				PromptProfileRegistry::IMPROVE,
				PromptProfileRegistry::REWRITE,
				PromptProfileRegistry::SHORTEN,
				PromptProfileRegistry::FORMAL,
				PromptProfileRegistry::CASUAL,
			),
			$ids
		);
		$this->assertCount( 6, $registry->all() );
	}

	public function test_each_profile_has_instructions_constraints_and_version(): void {
		$registry = new PromptProfileRegistry();

		foreach ( PromptProfileRegistry::ids() as $id ) {
			$profile = $registry->get( $id );
			$this->assertNotNull( $profile );
			$this->assertSame( $id, $profile->id );
			$this->assertSame( PromptProfileRegistry::VERSION, $profile->version );
			$this->assertNotSame( '', $profile->system_instructions );
			$this->assertContains( 'placeholders', $profile->constraints );
			$this->assertContains( 'html', $profile->constraints );
			$this->assertContains( 'numbers', $profile->constraints );
		}
	}

	public function test_unknown_profile_returns_null(): void {
		$registry = new PromptProfileRegistry();
		$this->assertFalse( $registry->has( 'openai-special' ) );
		$this->assertNull( $registry->get( 'openai-special' ) );
	}
}
