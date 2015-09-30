<?php
/*
Plugin Name: k-OutDated Checker (k-OC)
Plugin URI: https://kanenas.net/k-outdated-checker/
Description: k-OutDated Checker (k-OC) will scan automatically, twice a day, all of your installed plugins against the WordPress Plugin Directory for outdated plugins and email an alert for immediate update.
Version: 1.2.1
Author: kanenas (aka Nikolas Branis)
Author URI: https://kanenas.net/
License: GPLv2
Text Domain: k-outdated-plugin-checker-k-opc
Domain Path: /languages
*/

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

register_activation_hook( __FILE__, 'k_outdated_activation' );
/**
 * On activation, set a time, frequency and name of an action hook to be scheduled.
 */
function k_outdated_activation() {
	wp_schedule_event( time(), 'twicedaily', 'k_outdated_twicedaily_event_hook' );
}
add_action( 'k_outdated_twicedaily_event_hook', 'k_outdated' );

register_deactivation_hook( __FILE__, 'k_outdated_deactivation' );
/**
 * On deactivation, remove all functions from the scheduled action hook.
 */
function k_outdated_deactivation() {
	wp_clear_scheduled_hook( 'k_outdated_twicedaily_event_hook' );
}

/**
 * Load the plugin textdomain.
 */
function k_outdated_load_plugin_textdomain() {
	load_plugin_textdomain( 'k-outdated-plugin-checker-k-opc', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'k_outdated_load_plugin_textdomain' );

/**
 * On the scheduled action hook, run the function.
 */
function k_outdated() {
	// Check if get_plugins() function exists. This is required on the front end of the site, since it is in a file that is normally only loaded in the admin.
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all_plugins = get_plugins();

	$alert_content        = '';
	$alert_content_update = __( '***There is a new version of the plugin, please update!***', 'k-outdated-plugin-checker-k-opc' );
	$alert_content_ok     = __( '(OK)', 'k-outdated-plugin-checker-k-opc' );

	foreach ( $all_plugins as $plugins => $plugins_value ) {
		$plugin_slug = explode( "/", $plugins );

		$json = k_outdated_check_plugin_version( $plugin_slug[0] );

		// Check only for plugins that exist in WordPress Plugin Directory
		if ( !empty( $json ) ) {
			foreach ( $plugins_value as $plugin => $value ) {
				if ( $plugin == 'Name' ) {
					$plugin_name_html = '<strong>' . $value . ':</strong> ';
					$alert_content .= $plugin_name_html;
				}
				if ( $plugin == 'Version' ) {
					if ( $value != $json['version'] ) {
						
						// At least one plugin is outdated
						$found_outdated = true;

						$plugin_version_html = $value . ' <span style="color: red; font-weight: bolder;">' . $alert_content_update . '</span><br />';
						$alert_content .= $plugin_version_html;
					} else {
						$plugin_version_html = $value . ' <span style="color: green; font-weight: bolder;">' . $alert_content_ok . '</span><br />';
						$alert_content .= $plugin_version_html;
					}
				}
			}
		}
	}
	// Send email alert if there is at least one outdated plugin.
	if ( true == $found_outdated ) {
		k_outdated_send_email_alert( $alert_content );		
	}
}

function k_outdated_check_plugin_version( $plugin_slug ) {
	$url     = 'https://api.wordpress.org/plugins/info/1.0/' . $plugin_slug . '.json';
	$content = file_get_contents( $url );
	$content = utf8_encode( $content );
	$json    = json_decode( $content, true );
	return $json;
}

function k_outdated_send_email_alert( $alert_content ) {
	$k_outdated_url    = 'https://kanenas.net/k-outdated-checker/';
	$current_datetime  = date( 'Y-m-d H:i:s' );
	$website_url       = esc_url( get_bloginfo( 'url' ) );
	$website_name      = esc_html( get_bloginfo( 'name' ) );
	$website_admin_url = esc_url( admin_url( 'update-core.php' ) );

	// TO-DO: Add a field (email) where alerts will be send to more than one email accounts, comma separated.
	$mail_to      = get_option( 'admin_email' );
	$mail_subject = __( '[k-OC Alert] Some plugins need your attention', 'k-outdated-plugin-checker-k-opc' );
	$mail_content = sprintf( 
						__(
							'<p>This email was sent from your website <strong><a href="%1$s" target="_blank" title="%2$s">%3$s</a></strong> by the <strong><a href="%4$s" target="_blank" title="k-OutDated Checker (k-OC)">k-OutDated Checker (k-OC)</a></strong> plugin at %5$s.</p>',
							'k-outdated-plugin-checker-k-opc'
						),
						$website_url,
						esc_attr( $website_name ),
						$website_name,
						$k_outdated_url,
						$current_datetime
					) .
					__(
						'<p>There is at least one plugin that is <strong>outdated</strong>, please update!</p>',
						'k-outdated-plugin-checker-k-opc'
					) .
					__(
						'<p>These are the plugins that need your attention:</p>',
						'k-outdated-plugin-checker-k-opc'
					)
					. $alert_content .
					sprintf(
						__(
							'<p>Please <strong><a href="%1$s" target="_blank">UPDATE NOW</a></strong>.</p>',
							'k-outdated-plugin-checker-k-opc'
						),
						$website_admin_url
					);
	$mail_headers = array('Content-Type: text/html; charset=UTF-8');
	$status       = wp_mail( $mail_to, $mail_subject, $mail_content, $mail_headers );
}
?>