<?php
/**
 * TranslationMemoryService integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Translation\Memory\TMRepository;
use AIMultilingual\Translation\Memory\TranslationMemoryService;
use AIMultilingual\Translation\Store;

/**
 * F11 WP2 — exact/fuzzy lookup and write-back policy against aiml_tm.
 */
final class TranslationMemoryServiceIntegrationTest extends AimlTestCase {

	/**
	 * Service under test.
	 *
	 * @var TranslationMemoryService
	 */
	private TranslationMemoryService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new TranslationMemoryService( new TMRepository() );
	}

	public function test_exact_match_returns_confidence_100(): void {
		$source = 'Add this product to your shopping cart today';
		$hash   = Store::source_hash( $source, Store::FORMAT_PLAIN );

		$this->service->repository()->upsert(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_hash'    => $hash,
				'source_text'    => $source,
				'target_text'    => 'Lägg denna produkt i varukorgen idag',
				'context'        => 'block:core/paragraph',
				'origin'         => TMRepository::ORIGIN_HUMAN,
			)
		);

		$match = $this->service->lookup_exact( $source, 1, 2, 'block:core/paragraph' );

		$this->assertNotNull( $match );
		$this->assertSame( 100.0, $match['confidence'] );
		$this->assertSame( 'exact', $match['match_type'] );
		$this->assertSame( 'Lägg denna produkt i varukorgen idag', $match['target_text'] );
	}

	public function test_fuzzy_returns_ranked_candidates(): void {
		$this->service->repository()->upsert(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_hash'    => Store::source_hash( 'Free shipping on all domestic orders', Store::FORMAT_PLAIN ),
				'source_text'    => 'Free shipping on all domestic orders',
				'target_text'    => 'Fri frakt på alla inrikes beställningar',
				'context'        => 'block:core/paragraph',
				'origin'         => TMRepository::ORIGIN_HUMAN,
			)
		);

		$matches = $this->service->lookup_fuzzy(
			'Free shipping on all domestic order',
			1,
			2,
			'block:core/paragraph',
			Store::FORMAT_PLAIN,
			70.0
		);

		$this->assertNotEmpty( $matches );
		$this->assertSame( 'fuzzy', $matches[0]['match_type'] );
		$this->assertGreaterThanOrEqual( 60.0, $matches[0]['confidence'] );
		$this->assertLessThan( 100.0, $matches[0]['confidence'] );
	}

	public function test_human_write_back_persists_and_machine_is_skipped(): void {
		$source = 'Out of stock for this variant currently';

		$written = $this->service->write_back(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_text'    => $source,
				'target_text'    => 'Slut i lager för denna variant',
				'context'        => 'block:core/paragraph',
				'text_format'    => Store::FORMAT_PLAIN,
			),
			'human'
		);

		$this->assertNotInstanceOf( \WP_Error::class, $written );
		$this->assertNotNull( $written );
		$this->assertSame( TMRepository::ORIGIN_HUMAN, $written->origin );

		$skipped = $this->service->write_back(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_text'    => 'Machine only text that must not enter memory bank',
				'target_text'    => 'Maskinöversättning',
				'context'        => 'block:core/paragraph',
				'text_format'    => Store::FORMAT_PLAIN,
			),
			'machine'
		);

		$this->assertNull( $skipped );
	}

	public function test_ai_accepted_write_back_uses_ai_origin(): void {
		$row = $this->service->write_back(
			array(
				'source_lang_id' => 1,
				'target_lang_id' => 2,
				'source_text'    => 'Welcome to our multilingual product catalogue page',
				'target_text'    => 'Välkommen till vår flerspråkiga produktkatalogsida',
				'context'        => 'block:core/heading',
				'text_format'    => Store::FORMAT_PLAIN,
			),
			'ai_accepted'
		);

		$this->assertNotInstanceOf( \WP_Error::class, $row );
		$this->assertNotNull( $row );
		$this->assertSame( TMRepository::ORIGIN_AI, $row->origin );
		$this->assertSame( TMRepository::QUALITY_HUMAN_APPROVED, $row->quality );
	}
}
