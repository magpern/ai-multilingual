<?php
/**
 * Site Translate REST integration tests.
 *
 * @package AIMultilingual
 */

declare( strict_types=1 );

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Block\FeatureFlags;
use AIMultilingual\Jobs\JobsCapabilities;
use AIMultilingual\Plugin;
use AIMultilingual\Settings;
use AIMultilingual\SiteTranslate\SiteTranslateLocalizedUrlBatchService;
use AIMultilingual\Translation\Extractor;
use AIMultilingual\Translation\Store;
use WP_REST_Request;

/**
 * Site Translate coverage, admission, batch create, and run batch REST tests.
 */
final class SiteTranslateRestTest extends AimlTestCase {

	use WorkspaceTestHelpers;

	protected function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/UnavailableJobsSchedulerStub.php';
		$this->define_action_scheduler_stubs();
		JobsCapabilities::grant_default_roles();
	}

	public function test_site_translate_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/aiml/v1/site-translate/objects', $routes );
		$this->assertArrayHasKey( '/aiml/v1/site-translate/coverage', $routes );
		$this->assertArrayHasKey( '/aiml/v1/site-translate/admission', $routes );
		$this->assertArrayHasKey( '/aiml/v1/site-translate/jobs', $routes );
		$this->assertArrayHasKey( '/aiml/v1/site-translate/jobs/run', $routes );
		$this->assertArrayHasKey( '/aiml/v1/site-translate/routes', $routes );
	}

	public function test_strategy_f_blocks_gutenberg_selection_when_incomplete(): void {
		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array(
					FeatureFlags::REGISTRATION      => false,
					FeatureFlags::INJECTION         => false,
					FeatureFlags::EXTRACTION        => false,
					FeatureFlags::FRONTEND_RENDER   => false,
				)
			)
		);
		Plugin::instance()->reload_settings();

		$block_page = $this->create_block_page();
		$classic    = $this->create_page( 'Classic page', 'Classic body without blocks.' );

		wp_set_current_user( $this->create_translator() );

		$blocked = rest_do_request(
			new WP_REST_Request(
				'POST',
				'/aiml/v1/site-translate/admission'
			)
		);
		$blocked->set_header( 'Content-Type', 'application/json' );
		$blocked->set_body(
			wp_json_encode(
				array(
					'post_ids' => array( (int) $block_page->ID ),
				)
			)
		);
		$response = rest_get_server()->dispatch( $blocked );
		$this->assertSame( 422, $response->get_status() );
		$this->assertSame( 'aiml_site_translate_strategy_f_required', $response->as_error()->get_error_code() );

		$allowed = rest_do_request(
			new WP_REST_Request(
				'POST',
				'/aiml/v1/site-translate/admission'
			)
		);
		$allowed->set_header( 'Content-Type', 'application/json' );
		$allowed->set_body(
			wp_json_encode(
				array(
					'post_ids' => array( (int) $classic->ID ),
				)
			)
		);
		$ok = rest_get_server()->dispatch( $allowed );
		$this->assertSame( 200, $ok->get_status() );
		$this->assertTrue( $ok->get_data()['allowed'] );
	}

	public function test_coverage_zero_eligible_is_not_complete(): void {
		$post = $this->create_page( 'Empty coverage', '' );
		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request( 'GET', '/aiml/v1/site-translate/coverage' );
		$request->set_param( 'language_id', 2 );
		$request->set_param( 'post_ids', array( (int) $post->ID ) );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$item     = $response->get_data()['items'][0];
		$coverage = $item['coverage'];
		$this->assertSame( 0, $coverage['eligible_total'] );
		$this->assertTrue( $coverage['no_extractable_work'] );
		$this->assertContains( 'zero_eligible', $coverage['blocked_or_unsupported'] );
		$this->assertFalse( $coverage['translation_complete'] );
	}

	public function test_chunked_create_shares_batch_id_and_run_batch_enqueues_waiting_only(): void {
		$this->enable_strategy_f_flags();
		wp_set_current_user( $this->create_translator() );

		$post_ids = array();
		for ( $i = 0; $i < 51; $i++ ) {
			$post_ids[] = (int) $this->create_page( 'Bulk ' . $i, 'Body ' . $i )->ID;
		}

		$request = new WP_REST_Request( 'POST', '/aiml/v1/site-translate/jobs' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'post_ids'      => $post_ids,
					'language_id'   => 2,
					'client_token'  => 'site-translate-test-token',
					'provider_id'   => 'openai',
					'prompt_profile'=> 'default',
					'prompt_version'=> '1',
				)
			)
		);

		$create = rest_get_server()->dispatch( $request );
		$this->assertContains( $create->get_status(), array( 201, 207 ) );

		$data     = $create->get_data();
		$batch_id = (string) $data['batch_id'];
		$this->assertNotSame( '', $batch_id );
		$this->assertSame( 51, $data['created_count'] );
		$this->assertSame( 2, $data['chunk_count'] );

		$repo = new BackgroundTranslationJobRepository();
		$jobs = $repo->list_by_batch_id( $batch_id );
		$this->assertCount( 51, $jobs );

		$batch_ids = array_unique(
			array_map(
				static fn( object $job ): string => (string) $job->batch_id,
				$jobs
			)
		);
		$this->assertCount( 1, $batch_ids );

		wp_set_current_user( 1 );
		$run = rest_do_request(
			new WP_REST_Request(
				'POST',
				'/aiml/v1/site-translate/jobs/run'
			)
		);
		$run->set_header( 'Content-Type', 'application/json' );
		$run->set_body( wp_json_encode( array( 'batch_id' => $batch_id ) ) );
		$run_response = rest_get_server()->dispatch( $run );
		$this->assertSame( 202, $run_response->get_status() );
		$this->assertSame( 51, count( $run_response->get_data()['enqueued_job_ids'] ) );
	}

	public function test_localized_url_batch_reports_title_stale_without_generating(): void {
		$post  = $this->create_page( 'Stale title route', 'Body' );

		$this->store->save_translation(
			array(
				'source_type'     => Store::SOURCE_POST,
				'source_id'       => (int) $post->ID,
				'source_subtype'  => 'page',
				'language_id'     => 2,
				'field_key'       => Extractor::FIELD_TITLE,
				'segment_key'     => Extractor::FIELD_TITLE,
				'source_text'     => 'Stale title route',
				'translated_text' => 'Stale title sv',
				'text_format'     => Store::FORMAT_PLAIN,
				'status'          => Store::STATUS_TRANSLATED,
				'publish_status'  => Store::PUBLISH_PUBLISHED,
				'is_stale'        => 1,
			)
		);

		wp_set_current_user( $this->create_translator() );

		$request = new WP_REST_Request( 'POST', '/aiml/v1/site-translate/routes' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'post_ids'    => array( (int) $post->ID ),
					'language_id' => 2,
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$outcome = $response->get_data()['outcomes'][0];
		$this->assertSame( SiteTranslateLocalizedUrlBatchService::OUTCOME_TITLE_STALE, $outcome['outcome'] );
	}
}
