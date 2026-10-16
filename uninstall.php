<?php
/**
 * Removes the plugin's settings when it is deleted from WordPress.
 *
 * @package Adventistai\PopUp
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wppu_settings' );
delete_transient( 'wppu_github_latest_release' );
