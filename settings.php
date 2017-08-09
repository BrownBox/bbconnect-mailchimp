<?php
add_filter('bbconnect_options_tabs', 'bbconnect_mailchimp_options');
function bbconnect_mailchimp_options($navigation) {
    $navigation['bbconnect_mailchimp_settings'] = array(
            'title' => __('MailChimp', 'bbconnect'),
            'subs' => false,
    );
    return $navigation;
}

function bbconnect_mailchimp_settings() {
    return array(
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailchimp_list_id',
                            'name' => __('List ID', 'bbconnect'),
                            'help' => '',
                            'options' => array(
                                    'field_type' => 'text',
                                    'req' => true,
                                    'public' => false,
                            ),
                    ),
            ),
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailchimp_api_key',
                            'name' => __('API Key', 'bbconnect'),
                            'help' => '',
                            'options' => array(
                                    'field_type' => 'text',
                                    'req' => true,
                                    'public' => false,
                            ),
                    ),
            ),
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailchimp_channels_group',
                            'name' => __('Mapped Group Category', 'bbconnect'),
                            'help' => 'If you enter the name of one of your MailChimp group categories here, Connexions will automatically create fields for each group in that category and keep them in sync with MailChimp.',
                            'options' => array(
                                    'field_type' => 'text',
                                    'req' => false,
                                    'public' => false,
                            ),
                    ),
            ),
    );
}

add_action('bbconnect_options_save_ext', 'bbconnect_mailchimp_save_settings');
function bbconnect_mailchimp_save_settings() {
    $current_group = get_option('bbconnect_mailchimp_channels_group');
    $submitted_group = $_POST['_bbc_option']['bbconnect_mailchimp_channels_group'];

    if ($current_group != $submitted_group) { // No need to do anything if the value hasn't changed
        if (!empty($current_group)) {
            // Clear previous fields
            bbconnect_mailchimp_delete_group_fields($current_group);
        }

        if (!empty($submitted_group)) {
            // Create new fields
            bbconnect_mailchimp_create_group_fields($submitted_group);
        }
    }
}
