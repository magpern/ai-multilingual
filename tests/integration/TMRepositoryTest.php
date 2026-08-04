<?php
/**
 * TMRepository integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Memory\TMRepository;
use AIMultilingual\Translation\Store;

/**
 * F11 WP1 — repository upsert / identity / usage persistence.
 */
final class TMRepositoryTest extends AimlTestCase {

	/**
	 * Repository under test.
	 *
	 * @var TMRepository
	 */
	private TMRepository $repo;

	protected function setUp(): void {
		parent::setUp();
		$this->repo = new TMRepository();
	}

	public function test_upsert_persists_origin_and_identity(): void {
		$row = $this->repo->upsert(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_hash'    => str_repeat( 'a', 40 ),
				'source_text'    => 'Hello world from memory',
				'target_text'    => 'Hej värld från minnet',
				'text_format'    => Store::FORMAT_PLAIN,
				'context'        => 'block:core/paragraph',
				'origin'         => TMRepository::ORIGIN_HUMAN,
			)
		);

		$this->assertNotInstanceOf( \WP_Error::class, $row );
		$this->assertSame( TMRepository::ORIGIN_HUMAN, $row->origin );
		$this->assertSame( TMRepository::QUALITY_HUMAN_APPROVED, $row->quality );
		$this->assertSame( 'block:core/paragraph', $row->context );
		$this->assertSame( 0, (int) $row->use_count );

		$found = $this->repo->find_by_identity(
			str_repeat( 'a', 40 ),
			1,
			2,
			'block:core/paragraph'
		);

		$this->assertNotNull( $found );
		$this->assertSame( (int) $row->tm_id, (int) $found->tm_id );
	}

	public function test_upsert_is_idempotent_on_identity(): void {
		$first = $this->repo->upsert(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_hash'    => str_repeat( 'b', 40 ),
				'source_text'    => 'Add to cart now please',
				'target_text'    => 'Lägg i varukorgen',
				'context'        => 'block:core/button',
				'origin'         => TMRepository::ORIGIN_AI,
			)
		);

		$second = $this->repo->upsert(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_hash'    => str_repeat( 'b', 40 ),
				'source_text'    => 'Add to cart now please',
				'target_text'    => 'Lägg i kundvagnen',
				'context'        => 'block:core/button',
				'origin'         => TMRepository::ORIGIN_HUMAN,
			)
		);

		$this->assertNotInstanceOf( \WP_Error::class, $first );
		$this->assertNotInstanceOf( \WP_Error::class, $second );
		$this->assertSame( (int) $first->tm_id, (int) $second->tm_id );
		$this->assertSame( TMRepository::ORIGIN_HUMAN, $second->origin );
		$this->assertSame( 'Lägg i kundvagnen', $second->target_text );
	}

	public function test_record_usage_increments_counter(): void {
		$row = $this->repo->upsert(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_hash'    => str_repeat( 'c', 40 ),
				'source_text'    => 'Free shipping on orders',
				'target_text'    => 'Fri frakt på beställningar',
				'context'        => '',
				'origin'         => TMRepository::ORIGIN_HUMAN,
			)
		);

		$this->assertNotInstanceOf( \WP_Error::class, $row );

		$used = $this->repo->record_usage( (int) $row->tm_id );
		$this->assertNotInstanceOf( \WP_Error::class, $used );
		$this->assertSame( 1, (int) $used->use_count );
		$this->assertNotEmpty( $used->last_used_at );
	}

	public function test_fuzzy_candidates_respect_language_pair(): void {
		$this->repo->upsert(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_hash'    => str_repeat( 'd', 40 ),
				'source_text'    => 'Out of stock currently',
				'target_text'    => 'Slut i lager',
				'text_format'    => Store::FORMAT_PLAIN,
				'context'        => 'field:post_title',
				'origin'         => TMRepository::ORIGIN_HUMAN,
			)
		);

		$candidates = $this->repo->find_fuzzy_candidates( 1, 2, Store::FORMAT_PLAIN, 50 );
		$this->assertNotEmpty( $candidates );

		$other = $this->repo->find_fuzzy_candidates( 1, 99, Store::FORMAT_PLAIN, 50 );
		$this->assertSame( array(), $other );
	}

	public function test_invalid_origin_is_rejected(): void {
		$result = $this->repo->upsert(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_hash'    => str_repeat( 'e', 40 ),
				'source_text'    => 'Hello',
				'target_text'    => 'Hej',
				'origin'         => 'machine',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'aiml_tm_invalid_origin', $result->get_error_code() );
	}
}
