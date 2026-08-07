<?php
/**
 * Strategy F contract constants.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Block;

use AIMultilingual\Block\Contract;
use PHPUnit\Framework\TestCase;

/**
 * Strategy F contract constants.
 */
final class ContractTest extends TestCase {

	public function test_attribute_name_is_frozen(): void {
		$this->assertSame( 'aimlBlockId', Contract::ATTR_NAME );
	}

	public function test_segment_key_grammar_is_frozen(): void {
		$this->assertSame( 'b:<uuid>:<field>', Contract::SEGMENT_KEY_GRAMMAR );
		$this->assertSame( 'b', Contract::SEGMENT_KEY_PREFIX );
	}

	public function test_initial_supported_fields_include_a0_additions(): void {
		$this->assertContains( 'content', Contract::SUPPORTED_FIELDS );
		$this->assertContains( 'citation', Contract::SUPPORTED_FIELDS );
		$this->assertContains( 'summary', Contract::SUPPORTED_FIELDS );
		$this->assertContains( 'caption', Contract::SUPPORTED_FIELDS );
		$this->assertTrue( Contract::is_supported_field( 'content' ) );
		$this->assertTrue( Contract::is_supported_field( 'caption' ) );
		$this->assertFalse( Contract::is_supported_field( 'unknown_field' ) );
	}

	public function test_attribute_definition_is_shared_schema(): void {
		$this->assertSame(
			array( 'type' => 'string' ),
			Contract::attribute_definition()
		);
	}

	public function test_uuid_max_length_matches_rfc4122(): void {
		$this->assertSame( 36, Contract::UUID_MAX_LENGTH );
	}
}
