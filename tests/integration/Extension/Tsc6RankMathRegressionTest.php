<?php
/**
 * TSC.6 Rank Math SEO regression characterization.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration\Extension;

use AIMultilingual\Integration\RankMath\RankMathIntegration;
use AIMultilingual\Surface\Meta\RankMathMetaDefinitions;
use AIMultilingual\Tests\Integration\AimlTestCase;

/**
 * @coversNothing
 */
final class Tsc6RankMathRegressionTest extends AimlTestCase {

	public function test_six_literal_rank_math_meta_keys_unchanged(): void {
		$this->assertSame(
			array(
				RankMathIntegration::META_TITLE,
				RankMathIntegration::META_DESCRIPTION,
				RankMathIntegration::META_FACEBOOK_TITLE,
				RankMathIntegration::META_FACEBOOK_DESCRIPTION,
				RankMathIntegration::META_TWITTER_TITLE,
				RankMathIntegration::META_TWITTER_DESCRIPTION,
			),
			RankMathMetaDefinitions::seo_meta_keys()
		);
	}

	public function test_rank_math_integration_id_unchanged(): void {
		$this->assertSame( 'rankmath', RankMathIntegration::ID );
	}
}
