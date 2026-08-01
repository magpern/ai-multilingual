<?php
/**
 * F9 — queue an invalid Strategy F flag submission for admin notice validation.
 *
 * @package AIMultilingual
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via wp eval-file inside WordPress.\n" );
	exit( 1 );
}

use AIMultilingual\Admin\SettingsPage;
use AIMultilingual\Cache\Cache;
use AIMultilingual\Language\Languages;
use AIMultilingual\Settings;

$user_id = 1;
if ( function_exists( 'wp_set_current_user' ) ) {
	wp_set_current_user( $user_id );
} else {
	$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
}
if ( $user_id <= 0 ) {
	echo "empty\n";
	exit( 0 );
}

delete_transient( SettingsPage::FLAG_NOTICE_TRANSIENT . '_' . $user_id );
update_option( Settings::OPTION, Settings::defaults() );

$settings_page = new SettingsPage(
	new Settings(),
	new Languages( new Cache() )
);

$clean = $settings_page->sanitize_settings(
	array(
		'block_uuid_injection_enabled' => '1',
	)
);

update_option( Settings::OPTION, $clean );

echo get_transient( SettingsPage::FLAG_NOTICE_TRANSIENT . '_' . $user_id ) ? "queued\n" : "empty\n";
