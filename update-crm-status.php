<?php
require_once(dirname(__FILE__)."/../../../wp-load.php");

if(isset($_POST['type']) && (!empty($_POST['data']['merges']['EMAIL']) || !empty($_POST['data']['old_email']))) {
    if ($_POST['type'] == 'upemail') {
        $email = $_POST['data']['old_email'];
    } else {
        $email = $_POST['data']['merges']['EMAIL'];
    }

    do_action('bbconnect_mailchimp_before_webhook', $email, $_POST);

    // WARNING!! HACK AHEAD!!
    // When adding a new user, the MC call is triggered before we create the user - so when MC comes back straight away to say they're subscribed, we can't find the user because they don't exist yet.
    // So we delay the lookup for a few seconds to give it a chance to create the user first. This doesn't impact the end user as this script is called by MC.
    // We also delay it if it's a profile update in case there's a change of email at the same time - so that we have time to update the email address before doing the lookup.
    if ($_POST['type'] == 'subscribe' || $_POST['type'] == 'profile') {
        sleep(5);
    }
    // HACK OVER. You can relax now.

    $userobject = get_user_by('email',$email);
    if ($userobject instanceof WP_User) {
        $userid = $userobject->data->ID;
        switch ($_POST['type']) {
            case 'subscribe':
                // Update Subscribe meta on user
                update_user_meta($userid, 'bbconnect_bbc_subscription', 'true');
                break;
            case 'unsubscribe':
                // Update Subscribe meta on user
                update_user_meta($userid, 'bbconnect_bbc_subscription', 'false');
                break;
            case 'upemail':
                $userobject->data->user_email = $_POST['data']['new_email'];
                $result = wp_update_user($userobject);
                return; // Don't need to go any further as we always get a separate profile call at the same time as an email update
                break;
        }

        $profile_fields = array(
                'COUNTRY' => 'bbconnect_address_country_1',
                'FNAME' => 'first_name',
                'LNAME' => 'last_name',
        );

        foreach ($profile_fields as $mc_field => $meta_key) {
            if (!empty($_POST['data']['merges'][$mc_field])) {
                $meta_value = $_POST['data']['merges'][$mc_field];
                if ($meta_key == 'bbconnect_address_country_1') {
                    $bbconnect_helper_country = bbconnect_helper_country();
                    $meta_value = array_search($meta_value, $bbconnect_helper_country);
                }
                update_user_meta($user_id, $meta_key, $meta_value);
            }
        }
    }

    do_action('bbconnect_mailchimp_after_webhook', $email, $_POST);
}
