<?php
/**
 * Plugin Name: Connexions MailChimp
 * Plugin URI: n/a
 * Description: An addon to provide a bridge to connect with MailChimp for Connexions
 * Connexions
 * Version: 0.1.1
 * Author: Brown Box
 * Author URI: http://brownbox.net.au
 * License: Proprietary Brown Box
 */
require_once (plugin_dir_path(__FILE__).'mailchimp-api-php/Mailchimp.php');
require_once (plugin_dir_path(__FILE__).'mailchimp-api-php/Mailchimp-o.php');
require_once (plugin_dir_path(__FILE__).'settings.php');
require_once (plugin_dir_path(__FILE__).'updates.php');

define('BBCONNECT_MAILCHIMP_API_KEY', get_option('bbconnect_mailchimp_api_key'));
define('BBCONNECT_MAILCHIMP_LIST_ID', get_option('bbconnect_mailchimp_list_id'));

function bbconnect_mailchimp_init() {
    if (!defined('BBCONNECT_VER')) {
        add_action('admin_init', 'bbconnect_mailchimp_deactivate');
        add_action('admin_notices', 'bbconnect_mailchimp_deactivate_notice');
        return;
    }
    if (is_admin()) {
        new BbConnectUpdates(__FILE__, 'BrownBox', 'bbconnect-mailchimp');
    }
}
add_action('plugins_loaded', 'bbconnect_mailchimp_init');

function bbconnect_mailchimp_deactivate() {
    deactivate_plugins(plugin_basename(__FILE__));
}

function bbconnect_mailchimp_deactivate_notice() {
    echo '<div class="updated"><p><strong>Connexions MailChimp</strong> has been <strong>deactivated</strong> as it requires Connexions.</p></div>';
    if (isset($_GET['activate']))
        unset($_GET['activate']);
}

function subscribe_to_mailchimp($user_id) {
    $user = get_user_by('id', $user_id);
    $firstname = get_user_meta($user_id, 'first_name', true);
    $lastname = get_user_meta($user_id, 'last_name', true);
    $address1 = get_user_meta($user_id, 'bbconnect_address_one_1', true);
    $city = get_user_meta($user_id, 'bbconnect_address_city_1', true);
    $state = get_user_meta($user_id, 'bbconnect_address_state_1', true);
    $postal_code = get_user_meta($user_id, 'bbconnect_address_postal_code_1', true);
    $country = get_user_meta($user_id, 'bbconnect_address_country_1', true);

    $bbconnect_helper_country = bbconnect_helper_country();
    $country = $bbconnect_helper_country[$country];

    $email = $user->user_email;

    $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
    $Mailchimp_Lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
    try {
        $params = array(
                'id' => BBCONNECT_MAILCHIMP_LIST_ID,
                'emails' => array(
                        array(
                                'email' => $email
                        )
                )
        );
        $is_User_Registered = $mailchimp->call('lists/member-info', $params);

        if ($is_User_Registered['success_count'] == 0 || ($is_User_Registered['success_count'] != 0 && $is_User_Registered['data'][0]['status'] != 'subscribed' && $is_User_Registered['data'][0]['status'] != 'unsubscribed')) {
            $mc_email = array(
                    'email' => $email
            );
            $merge_vars = array(
                    'FNAME' => $firstname,
                    'LNAME' => $lastname,
                    'addr1' => $address1,
                    'city' => $city,
                    'state' => $state,
                    'zip' => $postal_code,
                    'country' => $country
            );
            $subscriber = $Mailchimp_Lists->subscribe(BBCONNECT_MAILCHIMP_LIST_ID, $mc_email, $merge_vars, '', false, false, false, false);
            if (empty($subscriber['leid'])) {
                // Something went wrong
            }
        }
    } catch (BB\Mailchimp\Mailchimp_Error $e) {
        // Something went wrong
        return;
    }
}
