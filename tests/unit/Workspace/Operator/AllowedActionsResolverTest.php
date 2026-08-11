<?php
/**
 * Unit tests for OTL AllowedActionsResolver.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Workspace\Operator;

use AIMultilingual\Translation\Publication\PublicationDecision;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Operator\ActionReasonCodes;
use AIMultilingual\Workspace\Operator\AllowedActionsResolver;
use AIMultilingual\Workspace\Operator\OperatorTranslationAssembler;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Workspace\Operator\AllowedActionsResolver
 * @covers \AIMultilingual\Workspace\Operator\OperatorTranslationAssembler
 */
final class AllowedActionsResolverTest extends TestCase {

	/**
	 * Preview truncation respects the frozen 200-character cap.
	 */
	public function test_preview_text_truncates_to_200(): void {
		$long = str_repeat( 'a', 250 );
		$out  = OperatorTranslationAssembler::preview_text( $long );
		$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $out ) : strlen( $out );
		$this->assertLessThanOrEqual( 201, $len );
		$this->assertStringEndsWith( '…', $out );
	}

	/**
	 * Publish is detail-only on the list action set.
	 */
	public function test_list_marks_publish_detail_only(): void {
		$resolver = new AllowedActionsResolver();
		$row      = $this->row(
			array(
				'status'          => Store::STATUS_MANUALLY_EDITED,
				'review_status'   => Store::REVIEW_NOT_SUBMITTED,
				'publish_status'  => Store::PUBLISH_UNPUBLISHED,
				'translated_text' => 'Hej',
			)
		);
		$caps     = array(
			'can_translate'   => true,
			'can_review'      => true,
			'can_edit_source' => true,
		);
		$actions  = $resolver->resolve_for_list( $row, $caps, array( 'edit_link' => 'https://example.test/edit' ) );
		$by_id    = $this->index( $actions );
		$this->assertFalse( $by_id['publish']['allowed'] );
		$this->assertSame( ActionReasonCodes::DETAIL_ONLY, $by_id['publish']['reason_code'] );
		$this->assertTrue( $by_id['submit_for_review']['allowed'] );
	}

	/**
	 * Approve requires pending + review capability.
	 */
	public function test_approve_requires_pending_and_review_cap(): void {
		$resolver = new AllowedActionsResolver();
		$row      = $this->row( array( 'review_status' => Store::REVIEW_PENDING ) );
		$denied   = $resolver->resolve_for_list(
			$row,
			array(
				'can_translate'   => true,
				'can_review'      => false,
				'can_edit_source' => true,
			)
		);
		$this->assertFalse( $this->index( $denied )['approve']['allowed'] );

		$allowed = $resolver->resolve_for_list(
			$row,
			array(
				'can_translate'   => true,
				'can_review'      => true,
				'can_edit_source' => true,
			)
		);
		$this->assertTrue( $this->index( $allowed )['approve']['allowed'] );
	}

	/**
	 * Detail publish uses TI.7 eligibility only.
	 */
	public function test_detail_publish_uses_publication_decision(): void {
		$resolver   = new AllowedActionsResolver();
		$row        = $this->row(
			array(
				'publish_status' => Store::PUBLISH_UNPUBLISHED,
				'review_status'  => Store::REVIEW_APPROVED,
			)
		);
		$caps       = array(
			'can_translate'   => true,
			'can_review'      => true,
			'can_edit_source' => true,
		);
		$ineligible = new PublicationDecision(
			'P1.0',
			false,
			array( 'assessment_blocked' ),
			'manual',
			'R1.0',
			'blocked',
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_APPROVED,
			'complete',
			'machine',
			true,
			false,
			array()
		);
		$actions    = $resolver->resolve_for_detail( $row, $caps, $ineligible );
		$this->assertFalse( $this->index( $actions )['publish']['allowed'] );
		$this->assertSame( ActionReasonCodes::PUBLICATION_INELIGIBLE, $this->index( $actions )['publish']['reason_code'] );

		$eligible = new PublicationDecision(
			'P1.0',
			true,
			array(),
			'manual',
			'R1.0',
			'structurally_clean',
			Store::PUBLISH_UNPUBLISHED,
			Store::REVIEW_APPROVED,
			'complete',
			'human',
			true,
			false,
			array()
		);
		$actions  = $resolver->resolve_for_detail( $row, $caps, $eligible );
		$this->assertTrue( $this->index( $actions )['publish']['allowed'] );
	}

	/**
	 * Retranslate stale is admitted without deferred_milestone when stale + caps.
	 */
	public function test_retranslate_stale_admitted_without_deferred_milestone(): void {
		$resolver = new AllowedActionsResolver();
		$row      = $this->row( array( 'is_stale' => 1 ) );
		$caps     = array(
			'can_translate'   => true,
			'can_review'      => true,
			'can_edit_source' => true,
		);
		$by_id    = $this->index( $resolver->resolve_for_list( $row, $caps ) );
		$this->assertTrue( $by_id['retranslate_stale']['allowed'] );
		$this->assertNull( $by_id['retranslate_stale']['reason_code'] );

		$not_stale = $this->index( $resolver->resolve_for_list( $this->row( array( 'is_stale' => 0 ) ), $caps ) );
		$this->assertFalse( $not_stale['retranslate_stale']['allowed'] );
	}

	/**
	 * Resolver has no side effects on the row (admission only).
	 */
	public function test_resolver_does_not_mutate_row(): void {
		$resolver = new AllowedActionsResolver();
		$row      = $this->row( array( 'review_status' => Store::REVIEW_PENDING ) );
		$before   = clone $row;
		$resolver->resolve_for_list(
			$row,
			array(
				'can_translate'   => true,
				'can_review'      => true,
				'can_edit_source' => true,
			)
		);
		$this->assertEquals( $before, $row );
	}

	/**
	 * Non-post rows must not admit mutation actions (OTL.2 honesty).
	 */
	public function test_non_post_denies_mutation_actions(): void {
		$resolver = new AllowedActionsResolver();
		$row      = $this->row(
			array(
				'source_type'     => 'term',
				'translated_text' => 'Term',
				'review_status'   => Store::REVIEW_PENDING,
			)
		);
		$caps     = array(
			'can_translate'   => true,
			'can_review'      => true,
			'can_edit_source' => true,
		);
		$by_id    = $this->index( $resolver->resolve_for_list( $row, $caps ) );
		$this->assertFalse( $by_id['edit']['allowed'] );
		$this->assertSame( ActionReasonCodes::MUTATION_UNSUPPORTED_TYPE, $by_id['edit']['reason_code'] );
		$this->assertFalse( $by_id['submit_for_review']['allowed'] );
		$this->assertFalse( $by_id['approve']['allowed'] );
		$this->assertFalse( $by_id['reject']['allowed'] );
	}

	/**
	 * @param array<string, mixed> $overrides Overrides.
	 */
	private function row( array $overrides = array() ): object {
		$defaults = array(
			'translation_id'  => 1,
			'source_type'     => Store::SOURCE_POST,
			'status'          => Store::STATUS_MANUALLY_EDITED,
			'review_status'   => Store::REVIEW_NOT_SUBMITTED,
			'publish_status'  => Store::PUBLISH_UNPUBLISHED,
			'translated_text' => 'Hello',
			'is_stale'        => false,
		);

		return (object) array_merge( $defaults, $overrides );
	}

	/**
	 * @param list<array{id: string, allowed: bool, reason_code: string|null}> $actions Actions.
	 * @return array<string, array{id: string, allowed: bool, reason_code: string|null}>
	 */
	private function index( array $actions ): array {
		$out = array();
		foreach ( $actions as $action ) {
			$out[ $action['id'] ] = $action;
		}

		return $out;
	}
}
