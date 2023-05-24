<?php
/**
 * Plugin Name: Connexions MailChimp
 * Description: An addon to provide a bridge to connect with MailChimp for Connexions
 * Version: 2.0.4
 * Author: Spark Web Solutions
 * Author URI: https://sparkweb.com.au
 */
define('BBCONNECT_MAILCHIMP_VERSION', '2.0.4');

require_once (plugin_dir_path(__FILE__).'includes/vendor/autoload.php');
require_once (plugin_dir_path(__FILE__).'db.php');
require_once (plugin_dir_path(__FILE__).'fx.php');
require_once (plugin_dir_path(__FILE__).'settings.php');
require_once (plugin_dir_path(__FILE__).'forms.php');

function bbconnect_mailchimp_init() {
	if (!defined('BBCONNECT_VER') || version_compare(BBCONNECT_VER, '2.8.1', '<')) {
		add_action('admin_init', 'bbconnect_mailchimp_deactivate');
		add_action('admin_notices', 'bbconnect_mailchimp_deactivate_notice');
		return;
	}

	if (is_admin()) {
		// DB updates
		bbconnect_mailchimp_updates();
		// Plugin updates
		new BbConnectUpdates(__FILE__, 'BrownBox', 'bbconnect-mailchimp');
	}

	// Push personalisation keys to MC if personalisation module is present and we haven't pushed them yet
	if (!empty(get_option('bbconnect_mailchimp_api_key')) && function_exists('bbconnect_personalisation_get_key_for_user') && !get_option('bbconnect_mailchimp_personalisation_keys_pushed')) {
		add_option('bbconnect_mailchimp_personalisation_keys_pushed', true);
		wp_schedule_single_event(time()-24*HOUR_IN_SECONDS, 'bbconnect_personalisation_generate_keys_for_all_users');
	}

	if (!wp_next_scheduled('bbconnect_mailchimp_do_daily_updates')) {
		$run_time = bbconnect_get_datetime('3am');
		$run_time->setTimezone(new DateTimeZone('UTC'));
		wp_schedule_event($run_time->getTimestamp(), 'daily', 'bbconnect_mailchimp_do_daily_updates');
	}
	if (!wp_next_scheduled('bbconnect_mailchimp_do_hourly_updates')) {
		wp_schedule_event(time(), 'hourly', 'bbconnect_mailchimp_do_hourly_updates');
	}
	register_deactivation_hook(__FILE__, 'bbconnect_mailchimp_deactivation');
}
add_action('plugins_loaded', 'bbconnect_mailchimp_init');

function bbconnect_mailchimp_deactivate() {
	deactivate_plugins(plugin_basename(__FILE__));
}

function bbconnect_mailchimp_deactivate_notice() {
	echo '<div class="updated"><p><strong>Connexions MailChimp</strong> has been <strong>deactivated</strong> as it requires Connexions (v2.8.1 or higher).</p></div>';
	if (isset($_GET['activate'])) {
		unset($_GET['activate']);
	}
}

add_filter('bbconnect_activity_types', 'bbconnect_mailchimp_activity_types');
function bbconnect_mailchimp_activity_types($types) {
	$types['mailchimp'] = 'MailChimp';
	return $types;
}

add_filter('bbconnect_activity_icon', 'bbconnect_mailchimp_activity_icon', 10, 2);
function bbconnect_mailchimp_activity_icon($icon, $activity_type) {
	if ($activity_type == 'mailchimp') {
		$icon = plugin_dir_url(__FILE__).'images/activity-icon.png';
	}
	return $icon;
}

function bbconnect_mailchimp_deactivation() {
	wp_clear_scheduled_hook('bbconnect_mailchimp_do_daily_updates');
}
