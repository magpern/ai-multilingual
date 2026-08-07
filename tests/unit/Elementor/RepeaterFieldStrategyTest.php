<?php
/**
 * Repeater nested identity strategy tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Unit\Elementor;

use AIMultilingual\Elementor\ElementorControlRegistry;
use AIMultilingual\Elementor\ElementorDiagnostics;
use AIMultilingual\Elementor\ElementorIdentity;
use AIMultilingual\Elementor\Strategy\RepeaterFieldStrategy;
use PHPUnit\Framework\TestCase;

/**
 * Nested `_id` extract rules.
 */
final class RepeaterFieldStrategyTest extends TestCase {

	private RepeaterFieldStrategy $strategy;
	private ElementorIdentity $identity;
	private ElementorDiagnostics $diag;
	private array $entry;

	protected function setUp(): void {
		parent::setUp();
		$this->strategy = new RepeaterFieldStrategy();
		$this->identity = new ElementorIdentity();
		$this->diag     = new ElementorDiagnostics();
		$registry       = new ElementorControlRegistry();
		$this->entry    = $registry->repeater_entry( 'accordion', 'tabs', 'tab_title', ElementorControlRegistry::SANITIZE_PLAIN, 'plain' );
	}

	public function test_valid_nested_keys(): void {
		$settings = array(
			'tabs' => array(
				array(
					'_id'       => 'aaa111',
					'tab_title' => 'One',
				),
				array(
					'_id'       => 'bbb222',
					'tab_title' => 'Two',
				),
			),
		);
		$units    = $this->strategy->extract( 9, 'acc1', 'accordion', $settings, $this->entry, $this->identity, $this->diag );
		$this->assertCount( 2, $units );
		$this->assertSame( 'e:d:9:acc1:tab_title:aaa111', $units[0]->segment_key );
		$this->assertSame( 'aaa111', $units[0]->nested_item_id );
	}

	public function test_missing_and_empty_id_denied(): void {
		$settings = array(
			'tabs' => array(
				array( 'tab_title' => 'No id' ),
				array(
					'_id'       => '',
					'tab_title' => 'Empty',
				),
				array(
					'_id'       => 'ok1',
					'tab_title' => 'Ok',
				),
			),
		);
		$units    = $this->strategy->extract( 1, 'acc1', 'accordion', $settings, $this->entry, $this->identity, $this->diag );
		$this->assertCount( 1, $units );
		$this->assertSame( 'e:d:1:acc1:tab_title:ok1', $units[0]->segment_key );
		$this->assertGreaterThan( 0, $this->diag->snapshot()['missing_nested_id'] );
	}

	public function test_duplicate_id_fails_admission_extract(): void {
		$settings = array(
			'tabs' => array(
				array(
					'_id'       => 'dup',
					'tab_title' => 'A',
				),
				array(
					'_id'       => 'dup',
					'tab_title' => 'B',
				),
			),
		);
		$units    = $this->strategy->extract( 1, 'acc1', 'accordion', $settings, $this->entry, $this->identity, $this->diag );
		$this->assertSame( array(), $units );
		$this->assertSame( 1, $this->diag->snapshot()['duplicate_nested_id'] );
	}

	public function test_reorder_preserves_identity(): void {
		$a  = array(
			'tabs' => array(
				array(
					'_id'       => 'r1',
					'tab_title' => 'First',
				),
				array(
					'_id'       => 'r2',
					'tab_title' => 'Second',
				),
			),
		);
		$b  = array(
			'tabs' => array(
				array(
					'_id'       => 'r2',
					'tab_title' => 'Second',
				),
				array(
					'_id'       => 'r1',
					'tab_title' => 'First',
				),
			),
		);
		$ua = $this->strategy->extract( 3, 'acc1', 'accordion', $a, $this->entry, $this->identity, $this->diag );
		$ub = $this->strategy->extract( 3, 'acc1', 'accordion', $b, $this->entry, $this->identity, $this->diag );
		$ka = array_map( static fn( $u ) => $u->segment_key, $ua );
		$kb = array_map( static fn( $u ) => $u->segment_key, $ub );
		sort( $ka );
		sort( $kb );
		$this->assertSame( $ka, $kb );
	}

	public function test_delete_insert_row(): void {
		$before = array(
			'tabs' => array(
				array(
					'_id'       => 'keep',
					'tab_title' => 'Keep',
				),
				array(
					'_id'       => 'gone',
					'tab_title' => 'Gone',
				),
			),
		);
		$after  = array(
			'tabs' => array(
				array(
					'_id'       => 'keep',
					'tab_title' => 'Keep',
				),
				array(
					'_id'       => 'new1',
					'tab_title' => 'New',
				),
			),
		);
		$ua     = $this->strategy->extract( 4, 'acc1', 'accordion', $before, $this->entry, $this->identity, $this->diag );
		$ub     = $this->strategy->extract( 4, 'acc1', 'accordion', $after, $this->entry, $this->identity, $this->diag );
		$ka     = array_map( static fn( $u ) => $u->segment_key, $ua );
		$kb     = array_map( static fn( $u ) => $u->segment_key, $ub );
		$this->assertContains( 'e:d:4:acc1:tab_title:keep', $ka );
		$this->assertContains( 'e:d:4:acc1:tab_title:keep', $kb );
		$this->assertNotContains( 'e:d:4:acc1:tab_title:gone', $kb );
		$this->assertContains( 'e:d:4:acc1:tab_title:new1', $kb );
	}

	public function test_no_array_index_identity(): void {
		$settings = array(
			'tabs' => array(
				array(
					'_id'       => 'stable',
					'tab_title' => 'T',
				),
			),
		);
		$units    = $this->strategy->extract( 5, 'acc1', 'accordion', $settings, $this->entry, $this->identity, $this->diag );
		$this->assertStringNotContainsString( ':0:', $units[0]->segment_key );
		$this->assertStringEndsWith( ':stable', $units[0]->segment_key );
	}
}
