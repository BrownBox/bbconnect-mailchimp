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
                            'meta_key' => 'bbconnect_mailchimp_connection_title',
                            'name' => __('Connection Settings', 'bbconnect'),
                            'help' => '',
                            'options' => array(
                                    'field_type' => 'title',
                                    'req' => false,
                                    'public' => false,
                                    'choices' => false,
                            ),
                    ),
            ),
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
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailchimp_subscription_title',
                            'name' => __('Subscription Settings', 'bbconnect'),
                            'help' => '',
                            'options' => array(
                                    'field_type' => 'title',
                                    'req' => false,
                                    'public' => false,
                                    'choices' => false,
                            ),
                    ),
            ),
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailchimp_optin_groups',
                            'name' => __('Default Groups', 'bbconnect'),
                            'help' => 'Select the groups (from the category you entered above) users should be added to by default',
                            'options' => array(
                                    'field_type' => 'plugin',
                                    'req' => false,
                                    'public' => false,
                                    'choices' => 'bbconnect_mailchimp_mapped_group_options',
                            ),
                    ),
            ),
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailchimp_enable_optin',
                            'name' => __('Enable Subscribe Opt-In', 'bbconnect'),
                            'help' => 'Tick this option to enable the opt-in messaging.',
                            'options' => array(
                                    'field_type' => 'checkbox',
                                    'req' => false,
                                    'public' => false,
                                    'choices' => false,
                            ),
                    ),
            ),
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailchimp_optin_countries',
                            'name' => __('Show Opt-in for Countries (leave blank for all)', 'bbconnect'),
                            'help' => 'Select the countries you want the subscribe opt-in to be displayed in (if enabled). If no countries are selected, it will be displayed in all countries.',
                            'options' => array(
                                    'field_type' => 'multiselect',
                                    'req' => false,
                                    'public' => false,
                                    'choices' => bbconnect_helper_country(),
                            ),
                    ),
            ),
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailchimp_optin_modal_content',
                            'name' => __('Opt-In Messaging', 'bbconnect'),
                            'help' => 'Explain to the user what you want to subscribe them to and why they should choose to accept. HTML can be used if desired.',
                            'options' => array(
                                    'field_type' => 'textarea',
                                    'req' => false,
                                    'public' => false,
                                    'choices' => false,
                            ),
                    ),
            ),
            array(
                    'meta' => array(
                            'source' => 'bbconnect',
                            'meta_key' => 'bbconnect_mailchimp_auto_subscribe',
                            'name' => __('Auto-Subscribe If No Opt-In', 'bbconnect'),
                            'help' => 'Tick this option if you want new contacts who don\'t meet the criteria above to be automatically subscribed.',
                            'options' => array(
                                    'field_type' => 'checkbox',
                                    'req' => false,
                                    'public' => false,
                                    'choices' => false,
                            ),
                    ),
            ),
    );
}

add_action('bbconnect_options_save_ext', 'bbconnect_mailchimp_save_settings');
function bbconnect_mailchimp_save_settings() {
    // Set up group syncing
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
        update_option('bbconnect_mailchimp_last_group_update', current_time('timestamp'));
    }
}

function bbconnect_mailchimp_mapped_group_options() {
    $default_groups = get_option('bbconnect_mailchimp_optin_groups');
    $groups = bbconnect_mailchimp_mapped_groups();
    foreach ($groups as $group) {
        $val = $default_groups[$group['id']] == 'true' ? 'true' : 'false';
        $class = $val == 'true' ? 'on' : 'off';
        echo '<a class="upt '.$class.'" title="bbconnect_mailchimp_optin_groups_'.$group['id'].'"><input type="hidden" id="bbconnect_mailchimp_optin_groups_'.$group['id'].'" name="_bbc_option[bbconnect_mailchimp_optin_groups]['.$group['id'].']" value="'.$val.'"> '.$group['name'].'</a> ';
    }
    echo '<br>';
}
