<?php
function bbconnect_mailchimp_updates() {
    // Get current version
    $dbv = get_option('_bbconnect_mailchimp_version', 0);

    // If it's not the latest, run our updates
    if (version_compare($dbv, BBCONNECT_MAILCHIMP_VERSION, '<')) {
        // List of versions that involved a DB update - each one must have a corresponding function below
        $db_versions = array(
                '1.0.4',
        );

        foreach ($db_versions as $version) {
            if (version_compare($version, $dbv, '>')) {
                call_user_func('bbconnect_mailchimp_db_update_'.str_replace('.', '_', $version));
                update_option('_bbconnect_mailchimp_version', $version);
            }
        }
        update_option('_bbconnect_mailchimp_version', BBCONNECT_MAILCHIMP_VERSION);
    }
}

function bbconnect_mailchimp_db_update_1_0_4() {
    $auto_subscribe = get_option('bbconnect_mailchimp_auto_subscribe');
    if (empty($auto_subscribe)) {
        update_option('bbconnect_mailchimp_auto_subscribe', 'true');
    }
}
