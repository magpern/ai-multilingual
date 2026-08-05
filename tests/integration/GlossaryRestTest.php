<?php
/**
 * Glossary REST integration tests.
 *
 * @package AIMultilingual
 */

declare(strict_types=1);

namespace AIMultilingual\Tests\Integration;

use AIMultilingual\Glossary\GlossaryAuditEvents;
use AIMultilingual\Glossary\GlossaryCapabilities;
use AIMultilingual\Plugin;
use Normalizer;
use WP_REST_Request;

/**
 * G6: capability matrix, CRUD, audit privacy.
 */
final class GlossaryRestTest extends AimlTestCase {

	/**
	 * Administrator receives manage glossary; editor does not.
	 */
	public function test_capability_matrix(): void {
		Plugin::activate();

		$admin  = get_role( 'administrator' );
		$editor = get_role( 'editor' );
		$this->assertNotNull( $admin );
		$this->assertNotNull( $editor );
		$this->assertTrue( $admin->has_cap( GlossaryCapabilities::MANAGE_GLOSSARY ) );
		$this->assertFalse( $editor->has_cap( GlossaryCapabilities::MANAGE_GLOSSARY ) );
		$this->assertTrue( $editor->has_cap( Plugin::CAPABILITY ) );
	}

	/**
	 * Translator-only users cannot list glossary terms.
	 */
	public function test_translator_cannot_list_glossary(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/aiml/v1/glossary' );
		$response = rest_do_request( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Administrator can create, read, deactivate, and delete.
	 */
	public function test_admin_crud_roundtrip(): void {
		$this->require_intl();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		Plugin::activate();

		$default = $this->languages->default();
		$sv      = $this->add_language( 'sv', 'sv_SE' );

		$create = new WP_REST_Request( 'POST', '/aiml/v1/glossary' );
		$create->set_header( 'Content-Type', 'application/json' );
		$create->set_body(
			wp_json_encode(
				array(
					'source_lang_id' => (int) $default->language_id,
					'target_lang_id' => (int) $sv->language_id,
					'source_term'    => 'Biopentra',
					'target_term'    => 'Biopentra',
					'context'        => 'brand',
				)
			)
		);

		$created = rest_do_request( $create );
		$this->assertSame( 201, $created->get_status(), wp_json_encode( $created->get_data() ) );
		$headers = $created->get_headers();
		$this->assertSame(
			'1',
			$headers['X-AIML-Glossary-Api-Version'][0] ?? $headers['x-aiml-glossary-api-version'][0] ?? ''
		);
		$data = $created->get_data();
		$id   = (int) $data['glossary_id'];
		$this->assertSame( 'Biopentra', $data['source_term'] );

		$list = rest_do_request( new WP_REST_Request( 'GET', '/aiml/v1/glossary' ) );
		$this->assertSame( 200, $list->get_status() );
		$this->assertGreaterThanOrEqual( 1, $list->get_data()['total'] );

		$deactivate  = new WP_REST_Request( 'POST', '/aiml/v1/glossary/' . $id . '/deactivate' );
		$deactivated = rest_do_request( $deactivate );
		$this->assertSame( 200, $deactivated->get_status() );
		$this->assertFalse( (bool) $deactivated->get_data()['is_active'] );

		$delete = rest_do_request( new WP_REST_Request( 'DELETE', '/aiml/v1/glossary/' . $id ) );
		$this->assertSame( 200, $delete->get_status() );
		$this->assertTrue( (bool) $delete->get_data()['deleted'] );
	}

	/**
	 * Audit events omit source/target term strings.
	 */
	public function test_audit_payload_omits_terms(): void {
		$this->require_intl();

		$captured = array();
		$listener = static function ( $event, $payload ) use ( &$captured ): void {
			$captured[] = array( $event, $payload );
		};
		add_action( 'aiml_glossary_audit', $listener, 10, 2 );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		Plugin::activate();

		$default = $this->languages->default();
		$sv      = $this->add_language( 'sv', 'sv_SE' );

		$create = new WP_REST_Request( 'POST', '/aiml/v1/glossary' );
		$create->set_header( 'Content-Type', 'application/json' );
		$create->set_body(
			wp_json_encode(
				array(
					'source_lang_id' => (int) $default->language_id,
					'target_lang_id' => (int) $sv->language_id,
					'source_term'    => 'Peptide',
					'target_term'    => 'Peptid',
				)
			)
		);
		rest_do_request( $create );
		remove_action( 'aiml_glossary_audit', $listener, 10 );

		$this->assertNotEmpty( $captured );
		$this->assertSame( GlossaryAuditEvents::TERM_CREATED, $captured[0][0] );
		$payload = $captured[0][1];
		$this->assertArrayHasKey( 'glossary_id', $payload );
		$this->assertArrayNotHasKey( 'source_term', $payload );
		$this->assertArrayNotHasKey( 'target_term', $payload );
	}

	/**
	 * Skip write paths when the test runner lacks ext-intl.
	 */
	private function require_intl(): void {
		if ( ! class_exists( Normalizer::class ) ) {
			$this->markTestSkipped( 'ext-intl required for glossary writes' );
		}
	}
}
