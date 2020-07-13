<?php
/**
 * Generate clean form of category/group name to be used in meta key
 * @param string $category
 * @param string $group
 * @return string
 */
function bbconnect_mailchimp_clean_group_name($category, $group) {
    return strtolower(preg_replace('/[^a-z\d]/i', '_', $category.'_'.$group));
}

/**
 * Delete CRM fields that are currently mapped to MC
 * @param string $delete_category
 */
function bbconnect_mailchimp_delete_group_fields($delete_category) {
    $groups = bbconnect_mailchimp_mapped_groups();
    $umo = get_option('_bbconnect_user_meta');
    foreach ($groups as $group) {
        // Remove fields
        $field_key = 'mailchimp_group_'.bbconnect_mailchimp_clean_group_name($delete_category, $group['name']);
        delete_option('bbconnect_'.$field_key);

        foreach ($umo as $uk => $uv) {
            foreach ($uv as $suk => $suv) {
                if ($suv == 'bbconnect_'.$field_key) { // Field is sitting directly in one of the columns
                    unset($umo[$uk][$suk]);
                    update_option('_bbconnect_user_meta', $umo);
                    break(2);
                }
                $section = get_option($suv);
                foreach ($section['options']['choices'] as $ck => $cv) {
                    if ($cv == $field_key) { // Field is in a section
                        unset($section['options']['choices'][$ck]);
                        update_option($suv, $section);
                        break(3);
                    }
                }
            }
        }
    }
}

/**
 * Create CRM fields for each group in mapped category
 * @param string $create_category
 */
function bbconnect_mailchimp_create_group_fields($create_category) {
    try {
        $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
        $mailchimp_lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
    } catch (BB\Mailchimp\Mailchimp_Error $e) {
        return;
    }
    try {
        $group_categories = $mailchimp_lists->interestGroupings(BBCONNECT_MAILCHIMP_LIST_ID);

        foreach ($group_categories as $category) {
            if ($category['name'] == $create_category) {
                update_option('bbconnect_mailchimp_current_groups', $category['groups'], false);
                // Add fields
                $fields = array();
                $default_groups = get_option('bbconnect_mailchimp_optin_groups');
                foreach ($category['groups'] as $group) {
                	$val = $default_groups[$group['id']] == 'true' ? 'true' : 'false';
                	$fields[] = array('source' => 'bbconnect', 'meta_key' => 'mailchimp_group_'.bbconnect_mailchimp_clean_group_name($create_category, $group['name']), 'tag' => '', 'name' => $create_category.': '.$group['name'], 'options' => array('admin' => true, 'user' => true, 'signup' => false, 'reports' => true, 'public' => false, 'req' => false, 'field_type' => 'checkbox', 'choices' => array($val)), 'help' => '');
                }
                $field_keys = array();

                foreach ($fields as $key => $value) {
                    if (false != get_option('bbconnect_'.$value['meta_key'])) {
                        continue;
                    }

                    $field_keys[] = $value['meta_key'];
                    add_option('bbconnect_'.$value['meta_key'], $value);
                }

                $umo = get_option('_bbconnect_user_meta');
                if (!empty($field_keys)) {
                    foreach ($umo as $uk => $uv) {
                        // Add to the preferences section
                        foreach ($uv as $suk => $suv) {
                            if ('bbconnect_preferences' == $suv) {
                                $acct = get_option($suv);
                                foreach ($field_keys as $fk => $fv) {
                                    $acct['options']['choices'][] = $fv;
                                }
                                update_option($suv, $acct);
                                $aok = true;
                                break(2);
                            }
                        }
                    }
                    // If we couldn't find the preferences section just add to column 3
                    if (!isset($aok)) {
                        foreach ($field_keys as $fk => $fv) {
                            $umo['column_3'][] = 'bbconnect_' . $fv;
                        }

                        update_option('_bbconnect_user_meta', $umo);
                    }
                }
            }
        }
    } catch (BB\Mailchimp\Mailchimp_Error $e) {
        // Do nothing
    }
}

if (!function_exists('subscribe_to_mailchimp')) { // backwards compatibility
    /**
     * Subscribe user to MailChimp. Use bbconnect_mailchimp_subscribe_user() instead.
     * @deprecated
     * @see bbconnect_mailchimp_subscribe_user()
     * @param integer $user_id
     * @param boolean $force
     */
    function subscribe_to_mailchimp($user_id, $force = false) {
        return bbconnect_mailchimp_subscribe_user($user_id, $force);
    }
}

function bbconnect_mailchimp_mapped_groups() {
    $groups = get_option('bbconnect_mailchimp_current_groups');
    if ($groups === false) {
        $groups = array();
    }
    return $groups;
}

/**
 * Subscribe user to MailChimp
 * @param integer|WP_User $user_id User to subscribe
 * @param boolean $force Optional. Whether to force them to resubscribe if they've previously unsubscribed. Default false (will not resubscribe unsubscribed users).
 * @return boolean|string True on success, error message on failure
 */
function bbconnect_mailchimp_subscribe_user($user, $force = false) {
    if (is_numeric($user)) {
        $user = get_user_by('id', $user);
    }
    $firstname = get_user_meta($user->ID, 'first_name', true);
    $lastname = get_user_meta($user->ID, 'last_name', true);

    $email = $user->user_email;

    $groupings = apply_filters('bbconnect_mailchimp_default_groupings', array());

    try {
        $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
        $mailchimp_lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
    } catch (BB\Mailchimp\Mailchimp_Error $e) {
        return $e->getMessage();
    }
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

        if ($is_User_Registered['success_count'] == 0 || ($is_User_Registered['success_count'] != 0 && $is_User_Registered['data'][0]['status'] != 'subscribed' && ($is_User_Registered['data'][0]['status'] != 'unsubscribed' || $force))) {
            $mc_email = array(
                    'email' => $email
            );
            $merge_vars = array(
                    'FNAME' => $firstname,
                    'LNAME' => $lastname,
                    'groupings' => $groupings,
            );
            $merge_vars = apply_filters('bbconnect_mailchimp_push_data', $merge_vars, $user);
            $subscriber = $mailchimp_lists->subscribe(BBCONNECT_MAILCHIMP_LIST_ID, $mc_email, $merge_vars, '', false, false, false, false);
            if (empty($subscriber['leid'])) {
                return 'Failed to add subscriber';
            } else {
                remove_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10);
                update_user_meta($user->ID, 'bbconnect_bbc_subscription', 'true');
                add_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10, 5);
                return true;
            }
        }
        remove_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10);
        update_user_meta($user->ID, 'bbconnect_bbc_subscription', 'true');
        add_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10, 5);
        return 'Already subscribed';
    } catch (BB\Mailchimp\Mailchimp_Error $e) {
        return $e->getMessage();
    }
}

add_filter('bbconnect_mailchimp_default_groupings', 'bbconnect_mailchimp_default_groupings', 0);
function bbconnect_mailchimp_default_groupings(array $groupings = array()) {
    $default_groups = get_option('bbconnect_mailchimp_optin_groups');
    $mapped_groups = bbconnect_mailchimp_mapped_groups();
    $groups = array();
    foreach ($mapped_groups as $mapped_group) {
        if ($default_groups[$mapped_group['id']] == 'true') {
            $groups[] = $mapped_group['name'];
        }
    }
    if (!empty($groups)) {
        $groupings[] = array(
                'name' => get_option('bbconnect_mailchimp_channels_group'),
                'groups' => $groups,
        );
    }
    return $groupings;
}

/**
 * Is user subscribed to MailChimp?
 * @param integer|WP_User $user
 * @return NULL|boolean Whether user is subscribed or null on failure to get status from MailChimp
 */
function bbconnect_mailchimp_is_user_subscribed($user) {
    if (is_numeric($user)) {
        $user = get_user_by('id', $user);
    }
    $email = $user->user_email;
    try {
        $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
        $mailchimp_lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
    } catch (BB\Mailchimp\Mailchimp_Error $e) {
        return null;
    }
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

        return $is_User_Registered['success_count'] > 0 && $is_User_Registered['data'][0]['status'] == 'subscribed';
    } catch (BB\Mailchimp\Mailchimp_Error $e) {
        return null;
    }
}

/**
 * Make sure a user's subscription preferences includes all of the default groups
 * @param WP_User|integer $user User to update. Can be either user ID or WP_User object.
 */
function bbconnect_mailchimp_update_user_default_groups($user) {
    $groupings = bbconnect_mailchimp_default_groupings();
    if (!empty($groupings)) {
        if (is_numeric($user)) {
            $user = get_user_by('id', $user);
        }
        $email = $user->user_email;
        foreach ($groupings as $grouping) {
            foreach ($grouping['groups'] as $group) {
                $meta_key = 'bbconnect_mailchimp_group_'.bbconnect_mailchimp_clean_group_name($grouping['name'], $group);
                update_user_meta($user->ID, $meta_key, 'true');
            }
        }
        bbconnect_mailchimp_push_user_groups($user);
    }
}

add_action('user_register', 'bbconnect_mailchimp_push_user_groups'); // Push selected groups to MailChimp as soon as a new user is created
/**
 * Update mapped groups in MailChimp based on user meta
 * @param WP_User|integer $user User to update. Can be either user ID or WP_User object.
 * @param array $old_user_data
 */
function bbconnect_mailchimp_push_user_groups($user, $old_user_data = null) {
    $mapped_category = get_option('bbconnect_mailchimp_channels_group');
    if (!empty($mapped_category)) {
        if (is_numeric($user)) {
            $user = get_user_by('id', $user);
        }
        $email = $user->user_email;

        try {
            $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
            $mailchimp_lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
        } catch (BB\Mailchimp\Mailchimp_Error $e) {
            return;
        }
        try {
            // Get user details from MailChimp so we don't lose any non-mapped group settings
            $user_registered = $mailchimp_lists->memberInfo(BBCONNECT_MAILCHIMP_LIST_ID, array(array('email' => $email)));

            // Argh MailChimp is painful - format of groupings array is different on read vs write
            $groupings = array();
            $current_groupings = $user_registered['data'][0]['merges']['GROUPINGS'];
            foreach ($current_groupings as $category) {
                $this_grouping = array(
                        'name' => $category['name'],
                        'groups' => array(),
                );
                foreach ($category['groups'] as $group) {
                    if ($category['name'] == $mapped_category) {
                        $meta_key = 'bbconnect_mailchimp_group_'.bbconnect_mailchimp_clean_group_name($mapped_category, $group['name']);
                        if (get_user_meta($user->ID, $meta_key, true) == 'true') {
                            $this_grouping['groups'][] = $group['name'];
                        }
                    } elseif ($group['interested']) {
                        $this_grouping['groups'][] = $group['name'];
                    }
                }
                $groupings[] = $this_grouping;
            }
            $mailchimp_lists->updateMember(BBCONNECT_MAILCHIMP_LIST_ID, array('email' => $email), array('groupings' => $groupings), '', true);
        } catch (BB\Mailchimp\Mailchimp_Error $e) {
             // Do nothing
        }
    }
}

/**
 * Update user meta based on mapped groups in MailChimp
 * @param WP_User|integer $user User to update. Can be either user ID or WP_User object.
 * @param string $meta_key Optional. If empty will update all mapped fields.
 */
function bbconnect_mailchimp_pull_user_groups($user, $meta_key = '') {
    $mapped_category = get_option('bbconnect_mailchimp_channels_group');
    if (!empty($mapped_category)) {
        if (is_numeric($user)) {
            $user = get_user_by('id', $user);
        }
        $email = $user->user_email;

        try {
            $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
            $mailchimp_lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
        } catch (BB\Mailchimp\Mailchimp_Error $e) {
            return;
        }

        remove_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10); // Don't want to trigger the filter again otherwise we'll end up in an endless loop
        try {
            $user_registered = $mailchimp_lists->memberInfo(BBCONNECT_MAILCHIMP_LIST_ID, array(array('email' => $email)));
            if (empty($user_registered['status'])) { // No errors
                $field_keys = array();
                if (empty($meta_key)) {
                    $groups = bbconnect_mailchimp_mapped_groups();
                    foreach ($groups as $group) {
                        $field_keys[] = 'bbconnect_mailchimp_group_'.bbconnect_mailchimp_clean_group_name($mapped_category, $group['name']);
                    }
                } else {
                    $field_keys[] = $meta_key;
                }

                foreach ($field_keys as $field_key) {
                    if ($user_registered['success_count'] != 0 && $user_registered['data'][0]['status'] == 'subscribed') { // Subscribed
                        // They're subscribed to MC, check groups to see which ones are selected
                        $group_selected = false;
                        foreach ($user_registered['data'][0]['merges']['GROUPINGS'] as $grouping) {
                            foreach ($grouping['groups'] as $group) {
                                if ('bbconnect_mailchimp_group_'.bbconnect_mailchimp_clean_group_name($grouping['name'], $group['name']) == $field_key) {
                                    $group_selected = $group['interested'];
                                    break(2);
                                }
                            }
                        }
                        update_user_meta($user->ID, $field_key, $group_selected ? 'true' : 'false');
                    } else { // Not subscribed at all
                        update_user_meta($user->ID, $field_key, 'false');
                    }
                }
            }
        } catch (BB\Mailchimp\Mailchimp_Error $e) {
            // Do nothing
        }
        update_user_meta($user->ID, 'bbconnect_mailchimp_last_group_update', current_time('timestamp'));
        add_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10, 5);
    }
}

/**
 * Update user meta for all users based on mapped groups in MailChimp.
 * You shouldn't ever need to call this function directly - it is run automatically in the background.
 */
function bbconnect_mailchimp_pull_all_user_groups() {
    $mapped_category = get_option('bbconnect_mailchimp_channels_group');
    if (!empty($mapped_category)) {
        $last_update = get_option('bbconnect_mailchimp_last_group_update');
        $offset = 0;
        $limit = 20;
        $get_total = true;
        global $blog_id;

        do {
            set_time_limit(600);
            $args = array(
                    'fields' => array('ID'),
                    'blog_id' => $blog_id,
                    'number' => $limit,
                    'offset' => $offset,
                    'count_total' => $get_total,
                    'meta_query' => array(
                            'relation' => 'or',
                            array(
                                    'key' => 'bbconnect_mailchimp_last_group_update',
                                    'value' => $last_update,
                                    'compare' => '<',
                            ),
                            array(
                                    'key' => 'bbconnect_mailchimp_last_group_update',
                                    'compare' => 'NOT EXISTS',
                            ),
                    ),
            );
            $query = new WP_User_Query($args);
            $users = $query->get_results();
            if ($get_total) {
                $total_users = $query->get_total();
            }

            foreach ($users as $user) {
                set_time_limit(300);
                bbconnect_mailchimp_pull_user_groups($user->ID);
            }
            $get_total = false;
            $offset += $limit;
            unset($query, $users, $user);
        } while ($offset <= $total_users);
    }
}

/**
 * If personalisation module is running, push key to MC
 * @param WP_User|integer $user User to update. Can be either user ID or WP_User object.
 * @param string $key Optional. Key to push to MailChimp.
 */
function bbconnect_mailchimp_maybe_push_personalisation_key($user, $key = null) {
    if (function_exists('bbconnect_personalisation_get_key_for_user')) {
        // Push personalisation key to MC
        if (empty($key)) {
            $key = bbconnect_personalisation_get_key_for_user($user);
        }
        if (!empty($key)) {
            if (is_numeric($user)) {
                $user = get_user_by('id', $user);
            }
            $email = $user->user_email;
            try {
                $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
                $mailchimp_lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
                $lists = $mailchimp->helper->listsForEmail(array('email' => $email));
                if (is_array($lists)) {
                    foreach ($lists as $list) {
                        $mailchimp_lists->updateMember($list['id'], array('email' => $email), array('KEY' => $key), '', false);
                    }
                }
            } catch (BB\Mailchimp\Mailchimp_Error $e) {
                // Do nothing
            }
        }
    }
}

add_action('bbconnect_mailchimp_maybe_push_all_personalisation_keys', 'bbconnect_mailchimp_maybe_push_all_personalisation_keys');
/**
 * If personalisation module is running, push key for all users to MC. Don't ever call this function manually - it will be triggered automatically when the plugin is successfully connected to MailChimp.
 */
function bbconnect_mailchimp_maybe_push_all_personalisation_keys() {
    if (function_exists('bbconnect_personalisation_get_key_for_user')) {
        set_time_limit(600);
        $users = get_users();
        foreach ($users as $user) {
            set_time_limit(300);
            bbconnect_mailchimp_maybe_push_personalisation_key($user);
        }
    }
}

add_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10, 5);
/**
 * Sync modified user meta to MailChimp. Runs on update_user_metadata hook.
 * @param null $null Deprecated
 * @param integer $user_id
 * @param string $meta_key
 * @param mixed $meta_value
 * @param mixed $prev_value
 * @return NULL|boolean Null for WP to continue saving, false to tell WP not to continue.
 */
function bbconnect_mailchimp_update($null, $user_id, $meta_key, $meta_value, $prev_value) {
    $email = get_userdata($user_id)->user_email;

    $mailchimp_fields = apply_filters('bbconnect_mailchimp_synced_meta_fields', array(
            'bbconnect_bbc_subscription',
            'COUNTRY' => 'bbconnect_address_country_1',
            'FNAME' => 'first_name',
            'LNAME' => 'last_name',
    ));

    try {
        $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
        $mailchimp_lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
    } catch (BB\Mailchimp\Mailchimp_Error $e) {
        return null;
    }
    if ($meta_key == 'bbconnect_bbc_subscription') {
        if (empty($prev_value)) { // The existing value often doesn't get passed through so we'll grab it ourselves
            $prev_value = get_user_meta($user_id, $meta_key, true);
        }
        if ($meta_value != $prev_value && !empty($prev_value)) {
            if ($meta_value == 'false') {
                try {
                    $mailchimp_lists->unsubscribe(BBCONNECT_MAILCHIMP_LIST_ID, array('email' => $email));
                } catch (BB\Mailchimp\Mailchimp_Error $e) {
                    // Do nothing
                }
            } elseif ($meta_value == 'true') {
                bbconnect_mailchimp_subscribe_user($user_id, true);
            }
        } elseif (empty($prev_value)) { // We had no meta, check MC to see whether they're subscribed
            try {
                $is_registered = $mailchimp->call('lists/member-info', array(
                        'id'        => BBCONNECT_MAILCHIMP_LIST_ID,
                        'emails'    => array(array('email' => $email))
                ));

                remove_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10); // Don't want to trigger this filter again otherwise we'll end up in an endless loop
                if (empty($is_registered['status'])) { // No errors
                    if ($is_registered['success_count'] != 0 && $is_registered['data'][0]['status'] == 'subscribed') { // Subscribed
                        update_user_meta($user_id, 'bbconnect_bbc_subscription', 'true');
                    } else { // Not subscribed
                        update_user_meta($user_id, 'bbconnect_bbc_subscription', 'false');
                    }
                }
                add_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10, 5);
            } catch (BB\Mailchimp\Mailchimp_Error $e) {
                // Do nothing
            }
            return false; // Don't want WP to keep saving as we've already updated it
        }
    } elseif (strpos($meta_key, 'mailchimp_group') !== false) {
        if (empty($prev_value)) { // The existing value often doesn't get passed through so we'll grab it ourselves
            $prev_value = get_user_meta($user_id, $meta_key, true);
        }
        if ($meta_value != $prev_value && !empty($prev_value)) { // We've specifically changed the value, update MC
            add_action('profile_update', 'bbconnect_mailchimp_push_user_groups', 99, 2);
        } elseif (empty($prev_value)) { // We had no meta previously, check MC to see whether they're subscribed
            bbconnect_mailchimp_pull_user_groups($user_id, $meta_key);
            return false; // Don't want WP to keep saving as we've already updated it
        }
    } elseif (in_array($meta_key, $mailchimp_fields)) {
        if ($meta_key == 'bbconnect_address_country_1') {
            $bbconnect_helper_country = bbconnect_helper_country();
            $meta_value = $bbconnect_helper_country[$meta_value];
        }
        try {
            $mailchimp_lists->updateMember(BBCONNECT_MAILCHIMP_LIST_ID, array('email' => $email), array(array_search($meta_key, $mailchimp_fields) => $meta_value), '', false);
        } catch (BB\Mailchimp\Mailchimp_Error $e) {
            // Do nothing
        }
    } elseif ($meta_key == 'bbconnect_personalisation_key') { // Send personalisation key to MC
        bbconnect_mailchimp_maybe_push_personalisation_key($user_id, $meta_value);
    }

    return null; // Tells WP to continue with saving the meta data
}

add_action('profile_update', 'bbconnect_mailchimp_email_update', 10, 2);
/**
 * Update email address in MailChimp when changed in CRM
 * @param integer $user_id User being updated
 * @param WP_User $old_user_data User data before update
 */
function bbconnect_mailchimp_email_update($user_id, $old_user_data) {
    $new_user_data = get_user_by('id', $user_id);
    $new_email = $new_user_data->user_email;
    $old_email = $old_user_data->user_email;
    if (!empty($new_email) && !empty($old_email) && $new_email != $old_email) {
        // Changing email address is tricky...
        try {
            $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
            $mailchimp_lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
        } catch (BB\Mailchimp\Mailchimp_Error $e) {
            return;
        }
        try {
            $mc_info = $mailchimp->call('lists/member-info', array(
                    'id'        => BBCONNECT_MAILCHIMP_LIST_ID,
                    'emails'    => array(array('email' => $old_email))
            ));
            if (empty($mc_info['status']) && $mc_info['success_count'] > 0) { // Found them!
                $mailchimp_lists->updateMember(BBCONNECT_MAILCHIMP_LIST_ID, array('email' => $old_email), array('NEW-EMAIL' => $new_email), '', false);
            }
        } catch (BB\Mailchimp\Mailchimp_Error $e) {
            // Do nothing
        }
    }
}

add_action('bbconnect_mailchimp_do_daily_updates', 'bbconnect_mailchimp_daily_updates');
/**
 * Checks mapped groups in MailChimp to make sure CRM fields still match.
 * Don't call this function directly - it's run as a daily WP cron at 3am (local time)
 */
function bbconnect_mailchimp_daily_updates() {
    $mapped_category = get_option('bbconnect_mailchimp_channels_group');
    if (!empty($mapped_category)) {
        try {
            $mailchimp = new BB\Mailchimp\Mailchimp(BBCONNECT_MAILCHIMP_API_KEY);
            $mailchimp_lists = new BB\Mailchimp\Mailchimp_Lists($mailchimp);
        } catch (BB\Mailchimp\Mailchimp_Error $e) {
            return;
        }
        try {
            $group_categories = $mailchimp_lists->interestGroupings(BBCONNECT_MAILCHIMP_LIST_ID);

            foreach ($group_categories as $category) {
                if ($category['name'] == $mapped_category) {
                    if ($category['groups'] != bbconnect_mailchimp_mapped_groups()) { // Something has changed - remove the current fields and create new ones
                        bbconnect_mailchimp_delete_group_fields($mapped_category);
                        bbconnect_mailchimp_create_group_fields($mapped_category);
                        update_option('bbconnect_mailchimp_last_group_update', current_time('timestamp'));
                    }
                    break;
                }
            }
        } catch (BB\Mailchimp\Mailchimp_Error $e) {
            // Do nothing
        }
    }
}

add_action('bbconnect_mailchimp_do_hourly_updates', 'bbconnect_mailchimp_hourly_updates');
function bbconnect_mailchimp_hourly_updates() {
    bbconnect_mailchimp_pull_all_user_groups();
}
