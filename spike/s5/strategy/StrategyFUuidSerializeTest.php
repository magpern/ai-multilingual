<?php
/**
 * Spike S5 — verify custom aimlBlockId attribute round-trips through core serialize.
 *
 * @package AIMultilingualSpike
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

require_once dirname( __DIR__ ) . '/lib/Strategy/StrategyFContract.php';

use AIMultilingual\Spike\S5\Strategy\StrategyFContract;

final class StrategyFUuidSerializeTest extends \WP_UnitTestCase {

	public function test_custom_attr_round_trips_on_core_paragraph(): void {
		$uuid = '550e8400-e29b-41d4-a716-446655440000';
		$in   = '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->';
		$blocks = parse_blocks( $in );
		$blocks[0]['attrs'][ StrategyFContract::ATTR_NAME ] = $uuid;
		$out    = serialize_blocks( $blocks );
		$re     = parse_blocks( $out );

		$this->assertStringContainsString( $uuid, $out );
		$this->assertSame( $uuid, $re[0]['attrs'][ StrategyFContract::ATTR_NAME ] ?? null );
	}
}
