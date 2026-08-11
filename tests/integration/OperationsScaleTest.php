<?php
/**
 * OTL.0 scale / performance integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Routing\Router;
use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\FieldSemanticMapper;
use AIMultilingual\Translation\Assessment\AssessmentAssembler;
use AIMultilingual\Translation\Publication\PublicationAuditLogger;
use AIMultilingual\Translation\Publication\PublicationPolicy;
use AIMultilingual\Translation\Publication\PublicationService;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Operator\AllowedActionsResolver;
use AIMultilingual\Workspace\Operator\OperatorTranslationAssembler;
use AIMultilingual\Workspace\PreviewService;
use AIMultilingual\Workspace\QA\QAEngine;

/**
 * Proves list stays cheap (zero assessment/explain/qa) at representative scale.
 */
final class OperationsScaleTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		$this->enable_strategy_f_flags();
	}

	public function test_list_invokes_zero_assessment_explain_and_qa(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Scale root', '<p>Body</p>' );
		$this->seed_operations_rows( $post, $language, 120 );

		$assembler = $this->make_assembler();
		$assembler->reset_invocation_counts();

		$result = $this->store->query_operations(
			array(
				'language_id' => (int) $language->language_id,
				'page'        => 1,
				'per_page'    => 50,
			)
		);
		$this->assertCount( 50, $result['items'] );
		$this->assertGreaterThanOrEqual( 120, $result['total'] );

		foreach ( $result['items'] as $row ) {
			$item = $assembler->assemble_list_item( $row );
			$this->assertArrayNotHasKey( 'assessment', $item );
			$this->assertArrayNotHasKey( 'publication', $item );
			$this->assertArrayNotHasKey( 'qa', $item );
			$this->assertLessThanOrEqual( 201, strlen( (string) $item['source_preview'] ) );
		}

		$counts = $assembler->invocation_counts();
		$this->assertSame( 0, $counts['assessment'] );
		$this->assertSame( 0, $counts['publication_explain'] );
		$this->assertSame( 0, $counts['qa'] );
	}

	public function test_detail_invokes_assessment_explain_once(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Detail scale', '<p>Body</p>' );
		$this->translate( $post, $language, 'post_title', 'Detalj' );
		$row = $this->store->get( Store::SOURCE_POST, (int) $post->ID, (int) $language->language_id, 'post_title' );
		$this->assertNotNull( $row );

		wp_set_current_user( $this->create_translator() );
		$assembler = $this->make_assembler();
		$assembler->reset_invocation_counts();
		$detail = $assembler->assemble_detail( $row );
		$this->assertIsArray( $detail );
		$counts = $assembler->invocation_counts();
		$this->assertSame( 1, $counts['assessment'] );
		$this->assertSame( 1, $counts['publication_explain'] );
		$this->assertSame( 1, $counts['qa'] );
	}

	public function test_query_operations_paginates_thousands_without_load_all(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Bulk root', '<p>Body</p>' );
		$this->seed_operations_rows( $post, $language, 1100 );

		$page1 = $this->store->query_operations(
			array(
				'language_id' => (int) $language->language_id,
				'page'        => 1,
				'per_page'    => 50,
			)
		);
		$this->assertCount( 50, $page1['items'] );
		$this->assertGreaterThanOrEqual( 1100, $page1['total'] );

		$page22 = $this->store->query_operations(
			array(
				'language_id' => (int) $language->language_id,
				'page'        => 22,
				'per_page'    => 50,
			)
		);
		$this->assertGreaterThan( 0, count( $page22['items'] ) );
		$ids1  = array_map( static fn( $r ) => (int) $r->translation_id, $page1['items'] );
		$ids22 = array_map( static fn( $r ) => (int) $r->translation_id, $page22['items'] );
		$this->assertSame( array(), array_intersect( $ids1, $ids22 ) );

		// Deterministic: translation_id DESC within equal updated_at (same seed timestamp window).
		$prev_id = PHP_INT_MAX;
		foreach ( $page1['items'] as $row ) {
			$id = (int) $row->translation_id;
			$this->assertLessThanOrEqual( $prev_id, $id );
			$prev_id = $id;
		}
	}

	public function test_axis_filter_totals_act_as_count_primitives(): void {
		$language = $this->add_language();
		$post     = $this->create_page( 'Axis', '<p>Body</p>' );
		$this->seed_operations_rows( $post, $language, 5 );
		$failed = $this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => (int) $language->language_id,
				'field_key'       => 'post_title',
				'segment_key'     => 'axis-failed',
				'segment_order'   => 99,
				'text_format'     => Store::FORMAT_PLAIN,
				'source_text'     => 'Fail',
				'translated_text' => 'Misslyckades',
				'status'          => Store::STATUS_FAILED,
			)
		);
		$this->assertTrue( $failed );

		$counts = $this->store->query_operations(
			array(
				'language_id' => (int) $language->language_id,
				'status'      => Store::STATUS_FAILED,
				'page'        => 1,
				'per_page'    => 1,
			)
		);
		$this->assertGreaterThanOrEqual( 1, $counts['total'] );
	}

	/**
	 * @param \WP_Post $post     Canonical post.
	 * @param object   $language Language row.
	 * @param int      $n        Row count.
	 */
	private function seed_operations_rows( \WP_Post $post, object $language, int $n ): void {
		for ( $i = 0; $i < $n; $i++ ) {
			$ok = $this->store->save_translation(
				array(
					'source_type'     => Store::SOURCE_POST,
					'source_id'       => (int) $post->ID,
					'source_subtype'  => (string) $post->post_type,
					'language_id'     => (int) $language->language_id,
					'field_key'       => 'post_title',
					'segment_key'     => 'scale-' . $i,
					'segment_order'   => $i,
					'text_format'     => Store::FORMAT_PLAIN,
					'source_text'     => 'Source ' . $i . ' ' . str_repeat( 'x', 50 ),
					'translated_text' => 'Target ' . $i,
					'status'          => Store::STATUS_MACHINE_TRANSLATED,
				)
			);
			$this->assertTrue( $ok );
		}
	}

	private function make_assembler(): OperatorTranslationAssembler {
		$publication = new PublicationService(
			$this->store,
			new AssessmentAssembler(),
			new PublicationPolicy(),
			new PublicationAuditLogger(),
			new Settings()
		);
		$preview     = new PreviewService(
			$this->languages,
			$this->context,
			new Router( $this->languages, $this->resolver, $this->context )
		);

		return new OperatorTranslationAssembler(
			$this->store,
			$this->languages,
			new AllowedActionsResolver(),
			$preview,
			new AssessmentAssembler(),
			new QAEngine(),
			new FieldSemanticMapper(),
			$publication
		);
	}
}
