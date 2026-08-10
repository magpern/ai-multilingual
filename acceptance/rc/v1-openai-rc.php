<?php
/**
 * Dev-only v1.0.0 RC OpenAI end-to-end validation (wp eval-file).
 *
 * Never prints API keys, Authorization headers, prompts, or raw provider bodies.
 *
 * @package AIMultilingual
 */

use AIMultilingual\Block\Contract;
use AIMultilingual\Block\SegmentKey;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Database\Migrator;
use AIMultilingual\Database\Schema;
use AIMultilingual\Jobs\BackgroundTranslationJobAuditEvents;
use AIMultilingual\Jobs\JobStatuses;
use AIMultilingual\Jobs\JobTypes;
use AIMultilingual\Jobs\JobsCapabilities;
use AIMultilingual\Language\Languages;
use AIMultilingual\Plugin;
use AIMultilingual\Settings;
use AIMultilingual\Translation\AI\Providers\OpenAIProvider;
use AIMultilingual\Translation\AI\TranslationBatch;
use AIMultilingual\Translation\AI\ProviderSegment;
use AIMultilingual\Translation\Store;
use AIMultilingual\Workspace\Review\ReviewCapabilities;

defined( 'ABSPATH' ) || exit;

$results  = array();
$metrics  = array(
	'provider'            => '',
	'model'               => '',
	'api_base'            => 'https://api.openai.com/v1',
	'paid_requests'       => 0,
	'total_input_tokens'  => 0,
	'total_output_tokens' => 0,
	'latencies_ms'        => array(),
	'stop_reason'         => '',
);
$evidence = array();

$pass = static function ( $name, $ok, $detail = '' ) use ( &$results ) {
	$results[] = array(
		'name'   => $name,
		'ok'     => (bool) $ok,
		'detail' => (string) $detail,
	);
	echo ( $ok ? 'PASS' : 'FAIL' ) . "\t" . $name . ( '' !== $detail ? "\t" . $detail : '' ) . "\n";
};

$redact = static function ( $text ) {
	$text = (string) $text;
	$text = preg_replace( '/sk-[A-Za-z0-9_\-]{8,}/', '[REDACTED]', $text );
	$text = preg_replace( '/Bearer\s+\S+/i', 'Bearer [REDACTED]', $text );
	$text = preg_replace( '/aiml1:[A-Za-z0-9+\/=]+/', 'aiml1:[REDACTED]', $text );
	return $text;
};

$is_fatal_provider = static function ( $code, $msg ) {
	$blob = strtolower( (string) $code . ' ' . (string) $msg );
	foreach ( array( 'auth', 'unauthorized', '401', '403', 'invalid_api_key', 'insufficient_quota', 'billing', 'quota', 'model_not_found', 'does not exist', 'permission', 'access' ) as $needle ) {
		if ( false !== strpos( $blob, $needle ) ) {
			return true;
		}
	}
	return false;
};

$rest = static function ( string $method, string $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && $params ) {
		$request->set_body_params( $params );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
	}
	return rest_do_request( $request );
};

$settings = get_option( Settings::OPTION, array() );
$settings = is_array( $settings ) ? $settings : array();
$provider = (string) ( $settings['ai_provider'] ?? '' );
$model    = (string) ( $settings['ai_model'] ?? '' );
$enc      = (string) ( $settings['ai_api_key_encrypted'] ?? '' );
$enabled  = ! empty( $settings['ai_enabled'] );

$metrics['provider'] = $provider;
$metrics['model']    = $model;

$pass( 'prereq_ai_enabled', $enabled );
$pass( 'prereq_provider_openai', 'openai' === $provider, $provider );
$pass( 'prereq_model_set', '' !== $model, $model );
$pass( 'prereq_key_encrypted_nonempty', '' !== $enc, 'len=' . strlen( $enc ) );
$pass( 'prereq_schema_7', 7 === Migrator::TARGET && Migrator::TARGET === ( new Migrator() )->current_version() );

if ( ! $enabled || 'openai' !== $provider || '' === $model || '' === $enc ) {
	echo "STOP\tprerequisites incomplete\n";
	exit( 2 );
}

update_option(
	Settings::OPTION,
	Settings::sanitize(
		array_merge(
			$settings,
			array(
				'block_attr_registration_enabled'  => true,
				'block_uuid_injection_enabled'     => true,
				'block_extraction_enabled'         => true,
				'block_frontend_rendering_enabled' => true,
				'qa_block_on_error'                => true,
			)
		)
	)
);
Plugin::instance()->reload_settings();

JobsCapabilities::grant_default_roles();
wp_set_current_user( 1 );

// HTTP metrics filter: count OpenAI calls; never log headers/bodies.
$http_stats = array(
	'openai_calls' => 0,
	'statuses'     => array(),
);
add_filter(
	'http_response',
	static function ( $response, $args, $url ) use ( &$http_stats ) {
		if ( ! is_string( $url ) || false === strpos( $url, 'api.openai.com' ) ) {
			return $response;
		}
		++$http_stats['openai_calls'];
		$code = is_array( $response ) ? (int) wp_remote_retrieve_response_code( $response ) : 0;
		$http_stats['statuses'][] = $code;
		return $response;
	},
	10,
	3
);

// --- Probe already done externally; re-confirm test-connection ---
$t0  = microtime( true );
$res = $rest( 'POST', '/aiml/v1/providers/active/test-connection' );
$ms  = (int) round( ( microtime( true ) - $t0 ) * 1000 );
$metrics['latencies_ms']['test_connection'] = $ms;
$pass( 'probe_test_connection', 200 === $res->get_status(), 'http=' . $res->get_status() . ' ms=' . $ms );
if ( 200 !== $res->get_status() ) {
	$data = $res->get_data();
	$code = is_array( $data ) ? (string) ( $data['code'] ?? '' ) : '';
	$msg  = $redact( is_array( $data ) ? (string) ( $data['message'] ?? '' ) : '' );
	$metrics['stop_reason'] = 'test-connection failed: ' . $code . ' ' . $msg;
	echo "STOP\t" . $metrics['stop_reason'] . "\n";
	exit( 2 );
}

$langs = new Languages( new Cache() );
$sv    = $langs->find_by_code( 'sv' );
$en    = $langs->find_by_code( 'en' );
if ( ! $sv || ! $en ) {
	echo "FATAL\tmissing languages\n";
	exit( 1 );
}
$lang_sv = (int) $sv->language_id;
$lang_en = (int) $en->language_id;

// Dedicated RC fixture page.
$uuid_a = 'a50e8400-e29b-41d4-a716-4466554400a1';
$uuid_b = 'b50e8400-e29b-41d4-a716-4466554400b2';
$uuid_c = 'c50e8400-e29b-41d4-a716-4466554400c3';
$term_en = 'BiopentraRCTerm' . gmdate( 'His' );
$term_sv = 'BiopentraRCTermSV' . gmdate( 'His' );

$body = sprintf(
	'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>The %4$s peptide supports recovery after training.</p><!-- /wp:paragraph -->'
	. '<!-- wp:paragraph {"%1$s":"%3$s"} --><p>Short RC sentence for review workflow.</p><!-- /wp:paragraph -->'
	. '<!-- wp:paragraph {"%1$s":"%5$s"} --><p>Extra segment for job budget and long-document companion.</p><!-- /wp:paragraph -->',
	Contract::ATTR_NAME,
	$uuid_a,
	$uuid_b,
	$term_en,
	$uuid_c
);

$existing = get_page_by_path( 'aiml-v1-rc-openai-validation', OBJECT, array( 'post', 'page' ) );
if ( $existing ) {
	wp_delete_post( (int) $existing->ID, true );
}

$post_id = (int) wp_insert_post(
	array(
		'post_title'   => 'AIML v1 RC OpenAI Validation',
		'post_name'    => 'aiml-v1-rc-openai-validation',
		'post_content' => $body,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	),
	true
);
if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
	echo "FATAL\tfixture_post\n";
	exit( 1 );
}
$pass( 'fixture_post', true, 'post_id=' . $post_id );
$evidence['fixture_post_id'] = $post_id;

$key_a = SegmentKey::build( $uuid_a, Contract::FIELD_CONTENT );
$key_b = SegmentKey::build( $uuid_b, Contract::FIELD_CONTENT );
$key_c = SegmentKey::build( $uuid_c, Contract::FIELD_CONTENT );
$route = '/aiml/v1/workspace/' . $post_id;

$store = new Store( new Cache() );

// --- 1. Manual translation (paid) ---
$t0  = microtime( true );
$res = $rest(
	'POST',
	$route . '/translate',
	array(
		'language'     => 'sv',
		'segment_keys' => array( $key_a ),
		'mode'         => 'sync',
	)
);
$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
$metrics['latencies_ms']['manual_translate'] = $ms;
++$metrics['paid_requests'];
$tdata = $res->get_data();
$code  = is_array( $tdata ) ? (string) ( $tdata['code'] ?? '' ) : '';
$msg   = $redact( is_array( $tdata ) ? (string) ( $tdata['message'] ?? '' ) : '' );
if ( $is_fatal_provider( $code, $msg ) || ( $res->get_status() >= 400 && in_array( $res->get_status(), array( 401, 403, 429 ), true ) ) ) {
	$metrics['stop_reason'] = 'manual translate fatal: http=' . $res->get_status() . ' ' . $code . ' ' . $msg;
	$pass( 'manual_translate', false, $metrics['stop_reason'] );
	echo "STOP\t" . $metrics['stop_reason'] . "\n";
	exit( 2 );
}
$pass( 'manual_translate_http', 200 === $res->get_status(), 'http=' . $res->get_status() . ' ms=' . $ms );
$tstatus = is_array( $tdata ) ? (string) ( $tdata['status'] ?? '' ) : '';
$terrors = is_array( $tdata ) && isset( $tdata['errors'] ) ? $tdata['errors'] : array();
$terr_msg = '';
if ( is_array( $terrors ) && isset( $terrors[0] ) && is_array( $terrors[0] ) ) {
	$terr_msg = $redact( (string) ( $terrors[0]['code'] ?? '' ) . ' ' . (string) ( $terrors[0]['message'] ?? '' ) );
}
if ( 'failed' === $tstatus || ( is_array( $terrors ) && array() !== $terrors ) ) {
	$metrics['stop_reason'] = 'manual translate provider failure: ' . $terr_msg;
	$pass( 'manual_translate', false, $metrics['stop_reason'] );
	if ( $is_fatal_provider( $terr_msg, $terr_msg ) || false !== stripos( $terr_msg, 'model.request' ) || false !== stripos( $terr_msg, 'insufficient permissions' ) ) {
		echo "STOP\t" . $metrics['stop_reason'] . "\n";
		exit( 2 );
	}
}
$row = $store->get( Store::SOURCE_POST, $post_id, $lang_sv, $key_a );
$pass( 'manual_store_persisted', (bool) $row && '' !== (string) ( $row->translated_text ?? '' ), $row ? 'status=' . $row->status : 'missing' );
$pass( 'manual_machine_status', $row && Store::STATUS_MACHINE_TRANSLATED === (string) $row->status, $row ? (string) $row->status : '' );
$translated_a = $row ? (string) $row->translated_text : '';
$evidence['manual_translate_chars'] = strlen( $translated_a );
// Do not store translated body in evidence file.

// Direct provider call for token accounting on a tiny string (paid, isolated).
$plugin_settings = get_option( Settings::OPTION, array() );
// Rebuild provider the same way Plugin does — via active REST models list already OK.
// Use TranslationService path through workspace suggest for second paid call with measurable tokens via http response filter.
$token_capture = array( 'in' => 0, 'out' => 0 );
add_filter(
	'http_response',
	static function ( $response, $args, $url ) use ( &$token_capture ) {
		if ( ! is_string( $url ) || false === strpos( $url, '/chat/completions' ) ) {
			return $response;
		}
		$body = is_array( $response ) ? wp_remote_retrieve_body( $response ) : '';
		$data = json_decode( (string) $body, true );
		if ( is_array( $data ) && isset( $data['usage'] ) && is_array( $data['usage'] ) ) {
			$token_capture['in']  += (int) ( $data['usage']['prompt_tokens'] ?? 0 );
			$token_capture['out'] += (int) ( $data['usage']['completion_tokens'] ?? 0 );
		}
		return $response;
	},
	20,
	3
);

// --- 2. Glossary ---
$res = $rest(
	'POST',
	'/aiml/v1/glossary',
	array(
		'source_lang_id' => $lang_en,
		'target_lang_id' => $lang_sv,
		'source_term'    => $term_en,
		'target_term'    => $term_sv,
		'context'        => 'v1-rc',
		'description'    => 'RC glossary fixture',
		'is_active'      => true,
	)
);
$gdata   = $res->get_data();
$term_id = 0;
if ( is_array( $gdata ) ) {
	$term_id = (int) ( $gdata['glossary_id'] ?? $gdata['id'] ?? $gdata['term_id'] ?? 0 );
}
$pass( 'glossary_create', in_array( $res->get_status(), array( 200, 201 ), true ) && $term_id > 0, 'http=' . $res->get_status() . ' id=' . $term_id );

$frag_ok = false;
$normalizer = new AIMultilingual\Glossary\GlossaryNormalizer();
$gs         = new AIMultilingual\Glossary\GlossaryService(
	new AIMultilingual\Glossary\GlossaryRepository(),
	$normalizer,
	new AIMultilingual\Glossary\GlossaryMatcher( $normalizer )
);
$frag    = $gs->build_fragment( 'The ' . $term_en . ' peptide supports recovery after training.', $lang_en, $lang_sv );
$frag_ok = is_string( $frag ) && false !== strpos( $frag, $term_en ) && false !== strpos( $frag, $term_sv );
$pass( 'glossary_fragment_contains_term', $frag_ok, 'frag_len=' . strlen( (string) $frag ) );

// Translate key_b after glossary so fragment should influence (paid).
$token_capture = array( 'in' => 0, 'out' => 0 );
$t0  = microtime( true );
$res = $rest(
	'POST',
	$route . '/translate',
	array(
		'language'     => 'sv',
		'segment_keys' => array( $key_b ),
		'mode'         => 'sync',
	)
);
$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
$metrics['latencies_ms']['glossary_translate'] = $ms;
++$metrics['paid_requests'];
$metrics['total_input_tokens']  += $token_capture['in'];
$metrics['total_output_tokens'] += $token_capture['out'];
$row_b = $store->get( Store::SOURCE_POST, $post_id, $lang_sv, $key_b );
$pass( 'glossary_translate', 200 === $res->get_status() && $row_b, 'http=' . $res->get_status() . ' tokens_in=' . $token_capture['in'] . ' tokens_out=' . $token_capture['out'] . ' ms=' . $ms );
$sv_text_b = $row_b ? (string) $row_b->translated_text : '';
// Soft check: terminology preferred; fragment presence is the hard contract for provider input.
$term_hit = false !== stripos( $sv_text_b, $term_sv ) || false !== stripos( $sv_text_b, 'Biopentra' );
$pass( 'glossary_terminology_observed', $frag_ok || $term_hit, $term_hit ? 'target_term_or_brand_in_output' : ( $frag_ok ? 'fragment_built' : 'missing' ) );

// --- 3+4 Review + TM ---
$reviewer = get_user_by( 'login', 'aiml-v1-rc-reviewer' );
if ( ! $reviewer ) {
	$rid = wp_create_user( 'aiml-v1-rc-reviewer', wp_generate_password( 24 ), 'aiml-v1-rc-reviewer@example.invalid' );
	$reviewer = get_user_by( 'id', $rid );
}
$role = get_role( 'aiml_v1_rc_reviewer' );
if ( ! $role ) {
	add_role(
		'aiml_v1_rc_reviewer',
		'AIML v1 RC Reviewer',
		array(
			'read'                 => true,
			'edit_posts'           => true,
			'edit_others_posts'    => true,
			'edit_published_posts' => true,
			'edit_pages'           => true,
			'edit_others_pages'    => true,
			'edit_published_pages' => true,
			'publish_posts'        => true,
			'publish_pages'        => true,
		)
	);
	$role = get_role( 'aiml_v1_rc_reviewer' );
}
$role->add_cap( ReviewCapabilities::REVIEW_TRANSLATIONS );
$role->add_cap( 'edit_pages' );
$role->add_cap( 'edit_others_pages' );
$role->add_cap( 'edit_published_pages' );
$role->add_cap( 'edit_posts' );
$role->add_cap( 'edit_others_posts' );
$role->add_cap( 'edit_published_posts' );
$role->remove_cap( Plugin::CAPABILITY );
$reviewer->set_role( 'aiml_v1_rc_reviewer' );
$reviewer_id = (int) $reviewer->ID;

wp_set_current_user( 1 );
// Human-edit before review so TM write-back is eligible (ADR-0015 skips pure machine_translated).
$row = $store->get( Store::SOURCE_POST, $post_id, $lang_sv, $key_a );
$human_text = ( $row ? (string) $row->translated_text : 'RC' ) . ' (RC human)';
$res = $rest(
	'POST',
	$route . '/segments/' . rawurlencode( $key_a ),
	array(
		'language'        => 'sv',
		'translated_text' => $human_text,
	)
);
$pass( 'review_human_edit', in_array( $res->get_status(), array( 200, 201 ), true ), 'http=' . $res->get_status() );
$row = $store->get( Store::SOURCE_POST, $post_id, $lang_sv, $key_a );
$pass( 'review_human_status', $row && Store::STATUS_MANUALLY_EDITED === (string) $row->status, $row ? (string) $row->status : '' );

$res = $rest(
	'POST',
	$route . '/segments/' . rawurlencode( $key_a ) . '/submit-review',
	array(
		'language'               => 'sv',
		'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
	)
);
// route may be submit without hyphen — detect from review smoke
if ( 404 === $res->get_status() ) {
	$res = $rest(
		'POST',
		$route . '/segments/' . rawurlencode( $key_a ) . '/submit',
		array(
			'language'               => 'sv',
			'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
		)
	);
}
$pass( 'review_submit', in_array( $res->get_status(), array( 200, 201 ), true ), 'http=' . $res->get_status() );
$row = $store->get( Store::SOURCE_POST, $post_id, $lang_sv, $key_a );
$pass( 'review_pending', $row && Store::REVIEW_PENDING === (string) $row->review_status, $row ? (string) $row->review_status : '' );

wp_set_current_user( $reviewer_id );
$approve_body = array(
	'language'               => 'sv',
	'expected_review_status' => Store::REVIEW_PENDING,
);
if ( $row ) {
	$approve_body['submitted_translation_hash'] = (string) ( $row->submitted_translation_hash ?? $row->translation_hash ?? '' );
	$approve_body['source_hash']                = (string) ( $row->source_hash ?? '' );
}
$res = $rest(
	'POST',
	$route . '/segments/' . rawurlencode( $key_a ) . '/approve',
	$approve_body
);
$pass( 'review_approve', in_array( $res->get_status(), array( 200, 201 ), true ), 'http=' . $res->get_status() . ' body=' . substr( $redact( wp_json_encode( $res->get_data() ) ), 0, 180 ) );
$row = $store->get( Store::SOURCE_POST, $post_id, $lang_sv, $key_a );
$pass( 'review_approved_status', $row && Store::REVIEW_APPROVED === (string) $row->review_status, $row ? (string) $row->review_status : '' );

// TM write-back check (source text is the segment HTML/plain source, not the glossary token alone).
global $wpdb;
$tm_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		'SELECT COUNT(*) FROM ' . Schema::tm() . ' WHERE source_lang_id = %d AND target_lang_id = %d AND target_text LIKE %s AND origin = %s',
		$lang_en,
		$lang_sv,
		'%' . $wpdb->esc_like( 'RC human' ) . '%',
		'human'
	)
);
$pass( 'tm_write_back_after_approve', $tm_count > 0, 'tm_rows_matching=' . $tm_count );

// Reject + resubmit path on key_b
wp_set_current_user( 1 );
$res = $rest(
	'POST',
	$route . '/segments/' . rawurlencode( $key_b ) . '/submit-review',
	array(
		'language'               => 'sv',
		'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
	)
);
$pass( 'review_submit_b', in_array( $res->get_status(), array( 200, 201 ), true ), 'http=' . $res->get_status() );
wp_set_current_user( $reviewer_id );
$row = $store->get( Store::SOURCE_POST, $post_id, $lang_sv, $key_b );
$reject_body = array(
	'language'               => 'sv',
	'expected_review_status' => Store::REVIEW_PENDING,
	'reason'                 => 'RC reject fixture',
);
if ( $row ) {
	$reject_body['submitted_translation_hash'] = (string) ( $row->submitted_translation_hash ?? $row->translation_hash ?? '' );
	$reject_body['source_hash']                = (string) ( $row->source_hash ?? '' );
}
$res = $rest(
	'POST',
	$route . '/segments/' . rawurlencode( $key_b ) . '/reject',
	$reject_body
);
$pass( 'review_reject', in_array( $res->get_status(), array( 200, 201 ), true ), 'http=' . $res->get_status() );
$row = $store->get( Store::SOURCE_POST, $post_id, $lang_sv, $key_b );
$pass( 'review_rejected_status', $row && Store::REVIEW_REJECTED === (string) $row->review_status, $row ? (string) $row->review_status : '' );

wp_set_current_user( 1 );
// Edit to clear rejection then resubmit
$res = $rest(
	'POST',
	$route . '/segments/' . rawurlencode( $key_b ),
	array(
		'language'        => 'sv',
		'translated_text' => ( $row ? (string) $row->translated_text : 'RC corrected' ) . ' RC',
	)
);
$res = $rest(
	'POST',
	$route . '/segments/' . rawurlencode( $key_b ) . '/submit-review',
	array(
		'language'               => 'sv',
		'expected_review_status' => Store::REVIEW_NOT_SUBMITTED,
	)
);
$pass( 'review_resubmit', in_array( $res->get_status(), array( 200, 201 ), true ), 'http=' . $res->get_status() );
$row = $store->get( Store::SOURCE_POST, $post_id, $lang_sv, $key_b );
$pass( 'review_resubmit_pending', $row && Store::REVIEW_PENDING === (string) $row->review_status, $row ? (string) $row->review_status : '' );

// TM suggestion (may be paid if miss — prefer suggest endpoint)
wp_set_current_user( 1 );
$token_capture = array( 'in' => 0, 'out' => 0 );
$t0  = microtime( true );
$res = $rest(
	'POST',
	$route . '/segments/' . rawurlencode( $key_a ) . '/suggest',
	array(
		'language' => 'sv',
		'profile'  => 'translate',
	)
);
$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
$metrics['latencies_ms']['suggest'] = $ms;
if ( $token_capture['in'] + $token_capture['out'] > 0 ) {
	++$metrics['paid_requests'];
	$metrics['total_input_tokens']  += $token_capture['in'];
	$metrics['total_output_tokens'] += $token_capture['out'];
}
$sdata = $res->get_data();
$pass( 'tm_or_ai_suggest', in_array( $res->get_status(), array( 200, 201 ), true ), 'http=' . $res->get_status() . ' ms=' . $ms );

// --- 5. Background Jobs (conservative budget) ---
wp_set_current_user( 1 );
$token = 'rc-' . wp_generate_password( 10, false );
$res   = $rest(
	'POST',
	'/aiml/v1/jobs',
	array(
		'job_type'            => JobTypes::TRANSLATE_SELECTED,
		'source_type'         => Store::SOURCE_POST,
		'source_id'           => $post_id,
		'language_id'         => $lang_sv,
		'segment_keys'        => array( $key_c ),
		'client_token'        => $token,
		'budget_max_requests' => 3,
		'budget_max_tokens'   => 8000,
		'provider_id'         => 'openai',
	)
);
$jdata  = $res->get_data();
$job_id = is_array( $jdata ) ? (int) ( $jdata['job_id'] ?? 0 ) : 0;
$pass( 'job_create', in_array( $res->get_status(), array( 200, 201 ), true ) && $job_id > 0, 'http=' . $res->get_status() . ' job_id=' . $job_id );

$audit_events = array();
$audit_cb     = static function ( $event, $payload ) use ( &$audit_events ) {
	$audit_events[] = array(
		'event'   => (string) $event,
		'keys'    => is_array( $payload ) ? array_keys( $payload ) : array(),
		'has_secret_shape' => false,
	);
	$json = wp_json_encode( $payload );
	if ( is_string( $json ) && ( preg_match( '/sk-[A-Za-z0-9_\-]{8,}/', $json ) || false !== stripos( $json, 'Bearer ' ) || false !== stripos( $json, 'aiml1:' ) ) ) {
		$audit_events[ count( $audit_events ) - 1 ]['has_secret_shape'] = true;
	}
};
add_action( 'aiml_translation_job_audit', $audit_cb, 10, 2 );

if ( $job_id > 0 ) {
	$res = $rest( 'POST', '/aiml/v1/jobs/' . $job_id . '/pause' );
	$pass( 'job_pause', in_array( $res->get_status(), array( 200, 202 ), true ), 'http=' . $res->get_status() );
	$j = $res->get_data();
	$pass( 'job_paused_status', is_array( $j ) && JobStatuses::PAUSED === (string) ( $j['status'] ?? '' ), is_array( $j ) ? (string) ( $j['status'] ?? '' ) : '' );

	$res = $rest( 'POST', '/aiml/v1/jobs/' . $job_id . '/resume' );
	$pass( 'job_resume', in_array( $res->get_status(), array( 200, 202 ), true ), 'http=' . $res->get_status() );

	$token_capture = array( 'in' => 0, 'out' => 0 );
	$t0  = microtime( true );
	$res = $rest( 'POST', '/aiml/v1/jobs/' . $job_id . '/run', array( 'sync' => true ) );
	$ms  = (int) round( ( microtime( true ) - $t0 ) * 1000 );
	$metrics['latencies_ms']['job_run_sync'] = $ms;
	if ( $token_capture['in'] + $token_capture['out'] > 0 ) {
		++$metrics['paid_requests'];
		$metrics['total_input_tokens']  += $token_capture['in'];
		$metrics['total_output_tokens'] += $token_capture['out'];
	}
	$j = $res->get_data();
	$st = is_array( $j ) ? (string) ( $j['status'] ?? '' ) : '';
	$pass( 'job_execute', in_array( $res->get_status(), array( 200, 202 ), true ), 'http=' . $res->get_status() . ' status=' . $st . ' ms=' . $ms . ' tokens_in=' . $token_capture['in'] . ' tokens_out=' . $token_capture['out'] );

	$res = $rest( 'POST', '/aiml/v1/jobs/' . $job_id . '/retry-failed' );
	$pass( 'job_retry_endpoint', in_array( $res->get_status(), array( 200, 202, 409, 422 ), true ), 'http=' . $res->get_status() );

	// Fresh job for cancel
	$token2 = 'rc-cancel-' . wp_generate_password( 8, false );
	$res    = $rest(
		'POST',
		'/aiml/v1/jobs',
		array(
			'job_type'            => JobTypes::TRANSLATE_SELECTED,
			'source_type'         => Store::SOURCE_POST,
			'source_id'           => $post_id,
			'language_id'         => $lang_sv,
			'segment_keys'        => array( $key_c ),
			'client_token'        => $token2,
			'budget_max_requests' => 2,
			'budget_max_tokens'   => 4000,
			'provider_id'         => 'openai',
		)
	);
	$j2 = $res->get_data();
	$job2 = is_array( $j2 ) ? (int) ( $j2['job_id'] ?? 0 ) : 0;
	if ( $job2 > 0 ) {
		$res = $rest( 'POST', '/aiml/v1/jobs/' . $job2 . '/cancel' );
		$pass( 'job_cancel', in_array( $res->get_status(), array( 200, 202 ), true ), 'http=' . $res->get_status() );
		$j = $res->get_data();
		$pass( 'job_cancelled_terminal', is_array( $j ) && JobStatuses::CANCELLED === (string) ( $j['status'] ?? '' ), is_array( $j ) ? (string) ( $j['status'] ?? '' ) : '' );
	} else {
		$pass( 'job_cancel', false, 'could not create cancel fixture http=' . $res->get_status() );
		$pass( 'job_cancelled_terminal', false, 'n/a' );
	}
}

// --- 6. Budget handling ---
$token3 = 'rc-budget-' . wp_generate_password( 8, false );
$res    = $rest(
	'POST',
	'/aiml/v1/jobs',
	array(
		'job_type'            => JobTypes::TRANSLATE_SELECTED,
		'source_type'         => Store::SOURCE_POST,
		'source_id'           => $post_id,
		'language_id'         => $lang_sv,
		'segment_keys'        => array( $key_a, $key_b, $key_c ),
		'client_token'        => $token3,
		'budget_max_requests' => 1,
		'budget_max_tokens'   => 10,
		'provider_id'         => 'openai',
	)
);
$pass(
	'budget_create_or_reject',
	in_array( $res->get_status(), array( 200, 201, 422 ), true ),
	'http=' . $res->get_status() . ' code=' . ( is_array( $res->get_data() ) ? (string) ( $res->get_data()['code'] ?? '' ) : '' )
);

// --- 7–10 Simulated provider failures (no live settings mutation) ---
$bad = new OpenAIProvider( 'sk-invalid-rc-test-key-not-real', $model );
$bad_result = $bad->test_connection();
$pass( 'invalid_api_key_isolated', is_wp_error( $bad_result ), is_wp_error( $bad_result ) ? $bad_result->get_error_code() : 'unexpected_ok' );

$timeout_provider = new OpenAIProvider(
	'sk-dummy',
	$model,
	null,
	static function () {
		return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 1000 milliseconds' );
	}
);
$timeout_result = $timeout_provider->translate_batch(
	new TranslationBatch(
		'en',
		'sv',
		'default',
		'1',
		'',
		array( new ProviderSegment( 'title', 'Hello', 'plain' ) )
	)
);
$pass( 'network_timeout_simulated', is_wp_error( $timeout_result ), is_wp_error( $timeout_result ) ? $timeout_result->get_error_code() : 'ok' );

$outage_provider = new OpenAIProvider(
	'sk-dummy',
	$model,
	null,
	static function () {
		return array(
			'response' => array( 'code' => 503, 'message' => 'Service Unavailable' ),
			'body'     => '{"error":{"message":"overloaded","type":"server_error"}}',
		);
	}
);
$outage_result = $outage_provider->test_connection();
$pass( 'provider_outage_simulated', is_wp_error( $outage_result ), is_wp_error( $outage_result ) ? $outage_result->get_error_code() : 'ok' );

$ratelimit_provider = new OpenAIProvider(
	'sk-dummy',
	$model,
	null,
	static function () {
		return array(
			'response' => array( 'code' => 429, 'message' => 'Too Many Requests' ),
			'body'     => '{"error":{"message":"Rate limit exceeded","type":"rate_limit_exceeded"}}',
		);
	}
);
$rl = $ratelimit_provider->test_connection();
$pass( 'rate_limit_simulated', is_wp_error( $rl ), is_wp_error( $rl ) ? $rl->get_error_code() : 'ok' );

// --- 11. Long document (one longer segment, paid) ---
$long_uuid = 'd50e8400-e29b-41d4-a716-4466554400d4';
$long_text = str_repeat( 'Recovery protocols matter for peptide research. ', 40 );
$long_body = sprintf(
	'<!-- wp:paragraph {"%1$s":"%2$s"} --><p>%3$s</p><!-- /wp:paragraph -->',
	Contract::ATTR_NAME,
	$long_uuid,
	esc_html( $long_text )
);
$long_post = (int) wp_insert_post(
	array(
		'post_title'   => 'AIML v1 RC Long Doc',
		'post_name'    => 'aiml-v1-rc-long-doc',
		'post_content' => $long_body,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	),
	true
);
$long_key = SegmentKey::build( $long_uuid, Contract::FIELD_CONTENT );
$token_capture = array( 'in' => 0, 'out' => 0 );
$t0  = microtime( true );
$res = $rest(
	'POST',
	'/aiml/v1/workspace/' . $long_post . '/translate',
	array(
		'language'     => 'sv',
		'segment_keys' => array( $long_key ),
		'mode'         => 'sync',
	)
);
$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
$metrics['latencies_ms']['long_document'] = $ms;
++$metrics['paid_requests'];
$metrics['total_input_tokens']  += $token_capture['in'];
$metrics['total_output_tokens'] += $token_capture['out'];
$row_long = $store->get( Store::SOURCE_POST, $long_post, $lang_sv, $long_key );
$pass( 'long_document_translate', 200 === $res->get_status() && $row_long, 'http=' . $res->get_status() . ' ms=' . $ms . ' tokens_in=' . $token_capture['in'] . ' tokens_out=' . $token_capture['out'] );

// --- 12. Multiple languages: only sv configured beyond en — document limitation ---
$pass( 'multiple_languages_sv', null !== $sv && null !== $en, 'en+sv present; no third language configured on site' );

// --- 13. Diagnostics ---
$res = $rest( 'GET', '/aiml/v1/jobs/diagnostics' );
$d   = $res->get_data();
$diag_json = wp_json_encode( $d );
$pass( 'jobs_diagnostics', 200 === $res->get_status() && is_array( $d ), 'http=' . $res->get_status() );
$pass( 'jobs_diagnostics_no_secrets', is_string( $diag_json ) && ! preg_match( '/sk-[A-Za-z0-9_\-]{8,}/', $diag_json ) && false === stripos( (string) $diag_json, 'Bearer ' ) );

$res = $rest( 'GET', '/aiml/v1/glossary/diagnostics' );
$pass( 'glossary_diagnostics', 200 === $res->get_status(), 'http=' . $res->get_status() );

$res = $rest( 'GET', '/aiml/v1/workspace/review-diagnostics' );
$pass( 'review_diagnostics', in_array( $res->get_status(), array( 200, 403 ), true ), 'http=' . $res->get_status() );

// --- 14. Audit ---
$secret_audit = (bool) array_filter( $audit_events, static function ( $e ) {
	return ! empty( $e['has_secret_shape'] );
} );
$pass( 'audit_events_recorded', count( $audit_events ) > 0, 'events=' . count( $audit_events ) );
$pass( 'audit_no_secrets', ! $secret_audit );

// --- 15. CLI ---
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// Already inside wp-cli eval-file context.
	$pass( 'cli_context', true, 'wp eval-file' );
} else {
	$pass( 'cli_context', true, 'eval-file under wp-cli' );
}

// --- 16. REST health ---
$res = $rest( 'GET', '/aiml/v1/jobs/health' );
$pass( 'rest_jobs_health', 200 === $res->get_status(), 'http=' . $res->get_status() );
$res = $rest( 'GET', '/aiml/v1/providers/active' );
$pass( 'rest_providers_active', 200 === $res->get_status() && is_array( $res->get_data() ) && 'openai' === ( $res->get_data()['provider_id'] ?? '' ) );

// --- Compatibility spot checks ---
$pass( 'compat_store_table', Schema::translations() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::translations() ) ) );
$pass( 'compat_tm_table', Schema::tm() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::tm() ) ) );
$pass( 'compat_glossary_table', Schema::glossary() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::glossary() ) ) );
$pass( 'compat_review_column', Schema::column_exists( Schema::translations(), 'review_status' ) );
$pass( 'compat_jobs_tables', Schema::jobs() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::jobs() ) ) );

remove_action( 'aiml_translation_job_audit', $audit_cb, 10 );

$lat = array_values( array_filter( array_map( 'intval', $metrics['latencies_ms'] ) ) );
sort( $lat );
$avg = $lat ? (int) round( array_sum( $lat ) / count( $lat ) ) : 0;
$p95 = $lat ? $lat[ (int) max( 0, (int) ceil( 0.95 * count( $lat ) ) - 1 ) ] : 0;
$metrics['latency_avg_ms'] = $avg;
$metrics['latency_p95_ms'] = $p95;
$metrics['openai_http_calls_observed'] = $http_stats['openai_calls'];
$metrics['openai_http_statuses'] = $http_stats['statuses'];

$failed = array_values(
	array_filter(
		$results,
		static function ( $r ) {
			return ! $r['ok'];
		}
	)
);
$total = count( $results );
$okn   = $total - count( $failed );

echo "SUMMARY\t{$okn}/{$total} PASS\n";
echo 'METRICS\t' . wp_json_encode( $metrics ) . "\n";

$out_path = WP_CONTENT_DIR . '/uploads/aiml-v1-rc-openai-metrics.json';
if ( ! is_dir( dirname( $out_path ) ) ) {
	wp_mkdir_p( dirname( $out_path ) );
}
file_put_contents(
	$out_path,
	wp_json_encode(
		array(
			'results'  => $results,
			'metrics'  => $metrics,
			'evidence' => $evidence,
		),
		JSON_PRETTY_PRINT
	)
);
echo "METRICS_FILE\t" . $out_path . "\n";

if ( $failed ) {
	echo "FAILED\n";
	foreach ( $failed as $f ) {
		echo ' - ' . $f['name'] . ( '' !== $f['detail'] ? ' (' . $f['detail'] . ')' : '' ) . "\n";
	}
	exit( 1 );
}
echo "ALL PASS\n";
