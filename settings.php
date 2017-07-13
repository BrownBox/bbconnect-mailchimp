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
    );
}
