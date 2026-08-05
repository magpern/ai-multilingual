<?php
/**
 * Rollout policy service unit tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Unit\Rollout;

use AIMultilingual\Rollout\GeneralAvailabilityCohortProvider;
use AIMultilingual\Rollout\RolloutConfiguration;
use AIMultilingual\Rollout\RolloutPolicyDecision;
use AIMultilingual\Rollout\RolloutPolicyRequest;
use AIMultilingual\Rollout\RolloutPolicyService;
use AIMultilingual\Rollout\RolloutReasonCodes;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AIMultilingual\Rollout\RolloutPolicyService
 * @covers \AIMultilingual\Rollout\RolloutPolicyDecision
 * @covers \AIMultilingual\Rollout\GeneralAvailabilityCohortProvider
 */
final class RolloutPolicyServiceTest extends TestCase {

	private RolloutPolicyService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new RolloutPolicyService();
	}

	public function test_allow_when_post_language_and_type_match(): void {
		$config = $this->active_config(
			array(
				'allowed_post_ids'       => array( 42 ),
				'allowed_language_codes' => array( 'sv' ),
			)
		);

		$decision = $this->service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertTrue( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::ALLOWED, $decision->reason_code );
		$this->assertTrue( $decision->cohort_match );
		$this->assertTrue( $decision->language_match );
		$this->assertTrue( $decision->post_type_match );
	}

	public function test_rollout_disabled_when_flag_off(): void {
		$config = $this->active_config( array( 'rollout_render_enabled' => false ) );

		$decision = $this->service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::ROLLOUT_DISABLED, $decision->reason_code );
	}

	public function test_empty_allowlist_denies_limited_stage(): void {
		$config = $this->active_config(
			array(
				'rollout_stage'    => 2,
				'allowed_post_ids' => array(),
			)
		);

		$decision = $this->service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::POST_NOT_ALLOWLISTED, $decision->reason_code );
	}

	public function test_post_not_allowlisted(): void {
		$config = $this->active_config(
			array(
				'allowed_post_ids' => array( 99 ),
			)
		);

		$decision = $this->service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::POST_NOT_ALLOWLISTED, $decision->reason_code );
	}

	public function test_invalid_post_type_denied(): void {
		$config = $this->active_config(
			array(
				'allowed_post_ids'   => array( 42 ),
				'allowed_post_types' => array( 'post' ),
			)
		);

		$decision = $this->service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::POST_TYPE_NOT_ALLOWED, $decision->reason_code );
	}

	public function test_language_not_allowed(): void {
		$config = $this->active_config(
			array(
				'allowed_post_ids'       => array( 42 ),
				'allowed_language_codes' => array( 'de' ),
			)
		);

		$decision = $this->service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::LANGUAGE_NOT_ALLOWED, $decision->reason_code );
	}

	public function test_unsupported_request_for_non_frontend(): void {
		$config = $this->active_config( array( 'allowed_post_ids' => array( 42 ) ) );

		$decision = $this->service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv', false ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::UNSUPPORTED_REQUEST, $decision->reason_code );
	}

	public function test_invalid_configuration_schema(): void {
		$config = RolloutConfiguration::defaults()->with(
			array(
				'schema_version'         => 99,
				'rollout_render_enabled' => true,
				'rollout_stage'          => 2,
				'allowed_post_ids'       => array( 42 ),
			)
		);

		$decision = $this->service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::INVALID_CONFIGURATION, $decision->reason_code );
	}

	public function test_deterministic_decisions_for_same_input(): void {
		$config  = $this->active_config(
			array(
				'allowed_post_ids' => array( 42 ),
			)
		);
		$request = new RolloutPolicyRequest( 42, 'page', 'sv' );

		$first  = $this->service->evaluate( $request, $config );
		$second = $this->service->evaluate( $request, $config );

		$this->assertNotSame( $first, $second );
		$this->assertSame( $first->allowed, $second->allowed );
		$this->assertSame( $first->reason_code, $second->reason_code );
		$this->assertSame( $first->policy_version, $second->policy_version );
	}

	public function test_decision_is_immutable(): void {
		$config   = $this->active_config( array( 'allowed_post_ids' => array( 42 ) ) );
		$decision = $this->service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertInstanceOf( RolloutPolicyDecision::class, $decision );
		$this->assertTrue( property_exists( $decision, 'allowed' ) );
	}

	public function test_ga_allows_non_allowlisted_post_when_enabled(): void {
		$service = new RolloutPolicyService( new GeneralAvailabilityCohortProvider() );
		$config  = $this->active_config(
			array(
				'rollout_stage'           => 6,
				'allowed_post_ids'        => array( 99 ),
				'allowed_language_codes'  => array( 'sv' ),
				'general_rollout_enabled' => true,
			)
		);

		$decision = $service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertTrue( $decision->allowed );
		$this->assertTrue( $decision->cohort_match );
		$this->assertSame( RolloutReasonCodes::ALLOWED, $decision->reason_code );
	}

	public function test_ga_disabled_preserves_allowlist_behavior_with_provider(): void {
		$service = new RolloutPolicyService( new GeneralAvailabilityCohortProvider() );
		$config  = $this->active_config(
			array(
				'allowed_post_ids'        => array( 99 ),
				'general_rollout_enabled' => false,
			)
		);

		$decision = $service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::POST_NOT_ALLOWLISTED, $decision->reason_code );
	}

	public function test_null_provider_ignores_general_rollout_flag_for_cohort(): void {
		$config = $this->active_config(
			array(
				'allowed_post_ids'        => array( 99 ),
				'general_rollout_enabled' => true,
			)
		);

		$decision = $this->service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::POST_NOT_ALLOWLISTED, $decision->reason_code );
	}

	public function test_ga_still_respects_language_filter(): void {
		$service = new RolloutPolicyService( new GeneralAvailabilityCohortProvider() );
		$config  = $this->active_config(
			array(
				'rollout_stage'           => 6,
				'allowed_post_ids'        => array(),
				'allowed_language_codes'  => array( 'sv' ),
				'general_rollout_enabled' => true,
			)
		);

		$decision = $service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'de' ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::LANGUAGE_NOT_ALLOWED, $decision->reason_code );
	}

	public function test_ga_still_respects_rollout_disabled_kill_switch(): void {
		$service = new RolloutPolicyService( new GeneralAvailabilityCohortProvider() );
		$config  = $this->active_config(
			array(
				'rollout_stage'           => 6,
				'rollout_render_enabled'  => false,
				'general_rollout_enabled' => true,
				'allowed_post_ids'        => array(),
			)
		);

		$decision = $service->evaluate(
			new RolloutPolicyRequest( 42, 'page', 'sv' ),
			$config
		);

		$this->assertFalse( $decision->allowed );
		$this->assertSame( RolloutReasonCodes::ROLLOUT_DISABLED, $decision->reason_code );
	}

	public function test_supported_blocks_include_f14_admitted_set(): void {
		$blocks = \AIMultilingual\Block\BlockRegistry::SUPPORTED_BLOCKS;
		$this->assertContains( 'core/paragraph', $blocks );
		$this->assertContains( 'core/heading', $blocks );
		$this->assertContains( 'core/button', $blocks );
		$this->assertContains( 'core/list-item', $blocks );
	}

	/**
	 * @param array<string, mixed> $overrides Config overrides.
	 */
	private function active_config( array $overrides = array() ): RolloutConfiguration {
		$base = array(
			'rollout_stage'          => 2,
			'rollout_render_enabled' => true,
			'allowed_post_ids'       => array( 42 ),
			'allowed_post_types'     => RolloutConfiguration::APPROVED_POST_TYPES,
			'allowed_language_codes' => array(),
			'policy_version'         => 3,
		);

		return RolloutConfiguration::defaults()->with( array_merge( $base, $overrides ) );
	}
}
