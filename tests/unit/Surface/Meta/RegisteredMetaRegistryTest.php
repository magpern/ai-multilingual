<?php
/**
 * Unit tests for RegisteredMetaRegistry (TSC.2).
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Surface\Meta;

use AIMultilingual\Surface\Meta\RankMathMetaDefinitions;
use AIMultilingual\Surface\Meta\RegisteredMetaDefinition;
use AIMultilingual\Surface\Meta\RegisteredMetaRegistry;
use AIMultilingual\Surface\PostSurfaceAdapter;
use AIMultilingual\Translation\Store;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Surface\Meta\RegisteredMetaRegistry
 * @covers \AIMultilingual\Surface\Meta\RegisteredMetaDefinition
 * @covers \AIMultilingual\Surface\Meta\RankMathMetaDefinitions
 */
final class RegisteredMetaRegistryTest extends TestCase {

	public function test_native_segment_key_contract(): void {
		$def = new RegisteredMetaDefinition(
			namespace: 'demo',
			source_type: Store::SOURCE_POST,
			meta_key: 'subtitle',
			segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
			label: 'Subtitle',
			provider_allowed: true,
		);
		$this->assertSame( 'm:demo:subtitle', $def->native_segment_key() );
		$this->assertSame( RegisteredMetaDefinition::FIELD_KEY, RegisteredMetaDefinition::FIELD_KEY );
		$this->assertFalse( ( new RegisteredMetaDefinition(
			namespace: 'demo',
			source_type: Store::SOURCE_POST,
			meta_key: 'x',
			segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
			label: 'X',
		) )->provider_allowed );
	}

	public function test_rejects_wildcard_meta_key(): void {
		$registry = new RegisteredMetaRegistry();
		$this->expectException( InvalidArgumentException::class );
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: 'foo*',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'Bad',
			)
		);
	}

	public function test_rejects_duplicate_native_identity(): void {
		$registry = new RegisteredMetaRegistry();
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: 'a',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'A',
			)
		);
		$this->expectException( InvalidArgumentException::class );
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: 'a',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'A2',
			)
		);
	}

	public function test_rejects_mode_switch_on_same_meta_key(): void {
		$registry = new RegisteredMetaRegistry();
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: 'a',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'A',
			)
		);
		$this->expectException( InvalidArgumentException::class );
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: 'a',
				segment_key_mode: RegisteredMetaDefinition::MODE_EXTERNAL_P,
				label: 'A',
				external_field_token: 'title',
			)
		);
	}

	public function test_no_production_ceiling_at_33(): void {
		$registry = new RegisteredMetaRegistry();
		for ( $i = 0; $i < 33; $i++ ) {
			$registry->register(
				new RegisteredMetaDefinition(
					namespace: 'bulk',
					source_type: Store::SOURCE_POST,
					meta_key: 'field_' . $i,
					segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
					label: 'Field ' . $i,
				)
			);
		}
		$this->assertSame( 33, $registry->count() );
	}

	public function test_rank_math_keys_single_source_of_truth(): void {
		$from_module = RankMathMetaDefinitions::seo_meta_keys();
		$from_const  = PostSurfaceAdapter::RANK_MATH_SEO_META_KEYS;
		$from_method = PostSurfaceAdapter::rank_math_seo_meta_keys();
		$this->assertSame( $from_module, $from_const );
		$this->assertSame( $from_module, $from_method );
		$this->assertCount( 6, $from_module );

		$registry = new RegisteredMetaRegistry();
		RankMathMetaDefinitions::register_into( $registry );
		foreach ( $from_module as $key ) {
			$this->assertTrue( $registry->has( Store::SOURCE_POST, $key ) );
			$this->assertTrue( $registry->has( Store::SOURCE_TERM, $key ) );
		}
	}

	public function test_provider_allowed_defaults_false_for_native(): void {
		$registry = new RegisteredMetaRegistry();
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: 'note',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'Note',
			)
		);
		$this->assertFalse( $registry->provider_allowed_for_segment( Store::SOURCE_POST, 'm:demo:note' ) );
	}

	public function test_inactive_definition_provider_denied(): void {
		$registry = new RegisteredMetaRegistry();
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: 'note',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'Note',
				provider_allowed: true,
				activation: static fn (): bool => false,
			)
		);
		$this->assertFalse( $registry->provider_allowed_for_segment( Store::SOURCE_POST, 'm:demo:note' ) );
		$this->assertSame( array( 'm:demo:note' ), $registry->retain_segment_keys( Store::SOURCE_POST, 1 ) );
	}

	public function test_catalog_does_not_admit_sources(): void {
		$registry = new RegisteredMetaRegistry();
		$registry->register(
			new RegisteredMetaDefinition(
				namespace: 'demo',
				source_type: Store::SOURCE_POST,
				meta_key: 'x',
				segment_key_mode: RegisteredMetaDefinition::MODE_NATIVE_M,
				label: 'X',
			)
		);
		$this->assertTrue( $registry->has( Store::SOURCE_POST, 'x' ) );
		$this->assertFalse( method_exists( $registry, 'exists' ) );
		$this->assertFalse( method_exists( $registry, 'user_can_edit_source' ) );
		$this->assertFalse( method_exists( $registry, 'is_visitor_public' ) );
	}
}
