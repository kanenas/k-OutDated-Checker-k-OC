<?php
/*
Plugin Name: k-OutDated Plugin Checker (k-OPC)
Plugin URI: https://kanenas.net/
Description: k-OutDated Plugin Checker (k-OPC) will scan automatically, twice a day, all of your installed plugins against the WordPress Plugin Repository for outdated plugins and email an alert for immediate update.
Version: 1.0
Author: kanenas (aka Nikolas Branis)
Author URI: https://kanenas.net/
License: GPLv2
Text Domain: k-opc
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
 * On the scheduled action hook, run the function.
 */
function k_outdated() {
	// Check if get_plugins() function exists. This is required on the front end of the site, since it is in a file that is normally only loaded in the admin.
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all_plugins = get_plugins();

	$alert_content = '';

	foreach ( $all_plugins as $plugins => $plugins_value ) {
		$plugin_slug = explode( "/", $plugins );

		$json = k_outdated_check_plugin_version( $plugin_slug[0] );

		foreach ( $plugins_value as $plugin => $value ) {
			if ( $plugin == 'Name' ) {
				$plugin_name_html = '<strong>' . $value . ':</strong> ';
				$alert_content .= $plugin_name_html;
			}
			if ( $plugin == 'Version' ) {
				if ( $value != $json['version'] ) {
					$plugin_version_html = $value . ' <span style="color: red; font-weight: bolder;">***There is a new version of the plugin, please update!***</span><br />';
					$alert_content .= $plugin_version_html;
				} else {
					$plugin_version_html = $value . ' <span style="color: green; font-weight: bolder;">(OK)</span><br />';
					$alert_content .= $plugin_version_html;
				}
			}
		}
	}
	// Send email alert if there is at least one outdated plugin.
	k_outdated_send_email_alert( $alert_content );
}

function k_outdated_check_plugin_version( $plugin_slug ) {
	$url = 'https://api.wordpress.org/plugins/info/1.0/' . $plugin_slug . '.json';
	$content = file_get_contents( $url );
	$content = utf8_encode( $content );
	$json = json_decode( $content, true );
	return $json;
}

function k_outdated_send_email_alert( $alert_content ) {
	// TO-DO: Add a field (email) where alerts will be send to more than one email accounts, comma separated.
	$mail_to = get_option( 'admin_email' );
	$mail_subject = "[k-OPC Alert] Some plugins need your attention";
	$mail_content = '<p>This email was sent from your website <strong><a href="' . esc_attr(get_bloginfo('url')) . '" target="_blank">' . get_bloginfo('name') . '</a></strong> by the <strong><a href="https://kanenas.net" target="_blank" title="k-OutDated (k-OPC)">k-OutDated (k-OPC)</a></strong> plugin at ' . date('Y-m-d H:i:s') . '.</p>
							<p>There is at least one plugin that is <strong>outdated</strong>, please update!</p>
							<p>These are the plugins that need your attention:</p>' . $alert_content . '
							<p>Please <strong><a href="' . esc_attr(admin_url('update-core.php')) .'" target="_blank">UPDATE NOW</a></strong>.</p>';
	$mail_headers = array('Content-Type: text/html; charset=UTF-8');
	$status = wp_mail($mail_to, $mail_subject, $mail_content, $mail_headers);
}
?>