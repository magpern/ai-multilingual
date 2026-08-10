<?php
defined( 'ABSPATH' ) || exit;
$s = get_option( 'aiml_settings', array() );
$s = is_array( $s ) ? $s : array();
echo sprintf(
	"enabled=%d provider=%s model=%s key_present=%d\n",
	empty( $s['ai_enabled'] ) ? 0 : 1,
	(string) ( $s['ai_provider'] ?? '' ),
	(string) ( $s['ai_model'] ?? '' ),
	'' === (string) ( $s['ai_api_key_encrypted'] ?? '' ) ? 0 : 1
);
