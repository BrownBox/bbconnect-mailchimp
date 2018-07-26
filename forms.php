<?php
add_filter('gform_form_settings', 'bbconnect_mailchimp_custom_form_setting', 10, 2);
function bbconnect_mailchimp_custom_form_setting($settings, $form) {
    if (!empty(get_option('bbconnect_mailchimp_enable_optin'))) {
        $settings['MailChimp']['bbconnect_mailchimp_subscribe_type'] = '
            <tr>
                <th><label for="bbconnect_mailchimp_subscribe_type">Subscribe Opt-in Style '.gform_tooltip('Connexions MailChimp has been configured to display a message prior to form submission on any form containing an email address, asking the user to confirm that they are happy to be subscribed to the default groups. This option allows you to select the style of messaging.', '', true).'</label></th>
                <td><label>
                    <select name="bbconnect_mailchimp_subscribe_type" id="bbconnect_mailchimp_subscribe_type">
                        <option value=""'.selected('', rgar($form, 'bbconnect_mailchimp_subscribe_type'), false).'>Modal (requires a theme built on Zurb Foundation)</option>
                        <option value="form"'.selected('form', rgar($form, 'bbconnect_mailchimp_subscribe_type'), false).'>In-Form</option>
                        <option value="none"'.selected('none', rgar($form, 'bbconnect_mailchimp_subscribe_type'), false).'>None</option>
                    </select>
                    </label></td>
            </tr>';
    }
    return $settings;
}

add_filter('gform_pre_form_settings_save', 'bbconnect_mailchimp_save_form_setting');
function bbconnect_mailchimp_save_form_setting($form) {
    if (!empty(get_option('bbconnect_mailchimp_enable_optin'))) {
        $form['bbconnect_mailchimp_subscribe_type'] = rgpost('bbconnect_mailchimp_subscribe_type');
    }
    return $form;
}

function bbconnect_mailchimp_show_subscribe_on_form($form) {
    if (!empty(get_option('bbconnect_mailchimp_enable_optin')) && empty($_SESSION['bbconnect_mailchimp_subscribe_optin']) && $_SESSION['bbconnect_mailchimp_subscribe_ask'] !== false) {
        $countries = get_option('bbconnect_mailchimp_optin_countries');
        if (empty($countries) || in_array(bbconnect_get_user_country(), $countries)) {
            if (is_numeric($form)) {
                $form = GFAPI::get_form($form);
            }
            if (isset($form['fields']) && is_array($form['fields'])) {
                if (rgar($form, 'bbconnect_mailchimp_subscribe_type') != 'none') {
                    foreach ($form['fields'] as $field) {
                        if ($field->type == 'email' && $field->visibility == 'visible') {
                            if (!session_id()) {
                                session_start();
                            }
                            $_SESSION['bbconnect_mailchimp_subscribe_ask'] = true;
                            return $field->id;
                        }
                    }
                }
            }
        }
    }
    return false;
}

add_filter('gform_pre_render', 'bbconnect_mailchimp_subscribe_messaging');
function bbconnect_mailchimp_subscribe_messaging($form) {
    if (($field_id = bbconnect_mailchimp_show_subscribe_on_form($form)) !== false) {
        $form_id = $form['id'];
        switch (rgar($form, 'bbconnect_mailchimp_subscribe_type')) {
            case 'form':
                // In-form messaging
                add_filter('gform_submit_button_'.$form_id, function($button, $form) use ($form_id, $field_id) {
                    ob_start();
?>
    <script>
    if (typeof ajaxurl === 'undefined') {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
    jQuery(document).ready(function() {
        var submit_button = jQuery('#gform_submit_button_<?php echo $form_id; ?>');
        submit_button.prop('disabled', true);
        jQuery('input#input_<?php echo $form_id; ?>_<?php echo $field_id; ?>').blur(function() {
            var email = jQuery(this).val();
            if (email != '') {
                jQuery.post(ajaxurl, {
                    'action': 'bbconnect_mailchimp_optin_check',
                    'email': email
                }, function(response) {
                    var show_optin = response == '';
                    if (show_optin) {
                        jQuery('#subscribe_in_form_<?php echo $form_id; ?>').show();
                    } else {
                        jQuery('#subscribe_in_form_<?php echo $form_id; ?>').hide();
                    }
                    submit_button.prop('disabled', show_optin);
                });
            }
        });
    });
    function bb_mailchimp_subscription_select(value) {
        jQuery('#subscribe_in_form_<?php echo $form_id; ?>').html('<p class="text-center"><img src="<?php echo plugins_url('gravityforms/images/spinner.gif'); ?>" alt="Please wait..."></p>');
        jQuery.post(ajaxurl, {
            'action': 'bbconnect_mailchimp_subscription_select',
            'value': value
        }, function(response) {
            jQuery('#gform_submit_button_<?php echo $form_id; ?>').prop('disabled', false);
            jQuery('#subscribe_in_form_<?php echo $form_id; ?>').hide();
        });
    }
    </script>
    <div class="subscribe-optin in-form" id="subscribe_in_form_<?php echo $form_id; ?>" style="display: none;">
        <?php echo wpautop(stripslashes(get_option('bbconnect_mailchimp_optin_modal_content'))); ?>
        <p class="text-center" id="subscribe_options">
            <button onclick="bb_mailchimp_subscription_select('no'); return false;" class="button secondary hollow">No</button>
            <button onclick="bb_mailchimp_subscription_select('yes'); return false;" class="button">Yes</button>
        </p>
    </div>
<?php
                    return ob_get_clean().$button;
                }, 10, 2);
                break;
            case '':
            default:
                // Modal
?>
    <script>
    if (typeof ajaxurl === 'undefined') {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
    var really_submit_<?php echo $form_id; ?> = false;
    jQuery(document).ready(function() {
        jQuery('input#input_<?php echo $form_id; ?>_<?php echo $field_id; ?>').blur(function() {
            var email = jQuery(this).val();
            if (email != '') {
                jQuery.post(ajaxurl, {
                    'action': 'bbconnect_mailchimp_optin_check',
                    'email': email
                }, function(response) {
                    really_submit_<?php echo $form_id; ?> = response != '';
                });
            }
        });
        jQuery('form#gform_<?php echo $form_id; ?>').on('submit', function() {
            if (!really_submit_<?php echo $form_id; ?> && jQuery('input#input_<?php echo $form_id; ?>_<?php echo $field_id; ?>').val() != '') {
                window['gf_submitting_<?php echo $form_id; ?>'] = false;
                jQuery('#subscribe_modal_<?php echo $form_id; ?>').foundation('open');
                return false;
            }
        });
        jQuery('#subscribe_modal_<?php echo $form_id; ?>').on('closed.zf.reveal', function() {
            jQuery('#gform_submit_button_<?php echo $form_id; ?>').prop('disabled', false);
        });
    });
    function bb_mailchimp_subscription_select(value) {
        var submit_button = jQuery('#gform_submit_button_<?php echo $form_id; ?>');
        submit_button.prop('disabled', true);
        jQuery.post(ajaxurl, {
            'action': 'bbconnect_mailchimp_subscription_select',
            'value': value
        }, function(response) {
            submit_button.prop('disabled', false);
            really_submit_<?php echo $form_id; ?> = true;
            submit_button.click();
        });
    }
    </script>
    <div class="subscribe-optin reveal small" id="subscribe_modal_<?php echo $form_id; ?>" data-reveal>
        <?php echo wpautop(stripslashes(get_option('bbconnect_mailchimp_optin_modal_content'))); ?>
        <p class="text-center" id="subscribe_options">
            <button type="submit" onclick="bb_mailchimp_subscription_select('no');" class="button secondary hollow">No</button>
            <button type="submit" onclick="bb_mailchimp_subscription_select('yes');" class="button">Yes</button>
        </p>
    </div>
<?php
        }
    }
    return $form;
}

add_action('wp_ajax_bbconnect_mailchimp_optin_check', 'bbconnect_mailchimp_ajax_optin_check');
add_action('wp_ajax_nopriv_bbconnect_mailchimp_optin_check', 'bbconnect_mailchimp_ajax_optin_check');
function bbconnect_mailchimp_ajax_optin_check() {
    $email = $_POST['email'];
    $user = get_user_by('email', $email);
    if ($user instanceof WP_User) {
        echo get_user_meta($user->ID, 'bbconnect_mailchimp_subscribe_optin', true);
    }
    die();
}

add_action('wp_ajax_bbconnect_mailchimp_subscription_select', 'bbconnect_mailchimp_ajax_subscription_select');
add_action('wp_ajax_nopriv_bbconnect_mailchimp_subscription_select', 'bbconnect_mailchimp_ajax_subscription_select');
function bbconnect_mailchimp_ajax_subscription_select() {
    if (!session_id()) {
        session_start();
    }
    $_SESSION['bbconnect_mailchimp_subscribe_optin'] = $_POST['value'];
    die();
}

add_action('gform_after_submission', 'bbconnect_mailchimp_subscribe_optin', 999, 2);
function bbconnect_mailchimp_subscribe_optin($entry, $form) {
    if (rgar($form, 'bbconnect_mailchimp_subscribe_type') != 'none') {
        foreach ($form['fields'] as $field) {
            if ($field->type == 'email' && $field->visibility == 'visible') {
                $user = get_user_by('email', $entry[$field->id]);
            }
        }
    }
    if ($user instanceof WP_User) {
        $optin = get_user_meta($user->ID, 'bbconnect_mailchimp_subscribe_optin', true);
        if (empty($optin)) {
            if (!session_id()) {
                session_start();
            }
            switch ($_SESSION['bbconnect_mailchimp_subscribe_optin']) {
                case '': // We didn't ask them - auto subscribe
                    update_user_meta($user->ID, 'bbconnect_mailchimp_subscribe_optin', 'auto');
                    if (!bbconnect_mailchimp_is_user_subscribed($user)) {
                        bbconnect_mailchimp_subscribe_user($user);
                    }
                    bbconnect_mailchimp_update_user_default_groups($user);
                    break;
                case 'yes': // We asked and they said yes!
                    update_user_meta($user->ID, 'bbconnect_mailchimp_subscribe_optin', 'manual');
                    if (!bbconnect_mailchimp_is_user_subscribed($user)) {
                        bbconnect_mailchimp_subscribe_user($user, true);
                    }
                    bbconnect_mailchimp_update_user_default_groups($user);
                    break;
                case 'no': // They said no :-(
                default:
                    if ($_SESSION['bbconnect_mailchimp_subscribe_ask']) { // Make sure we only track their answer once per session
                        $optout = get_user_meta($user->ID, 'bbconnect_mailchimp_subscribe_optout_count', true);
                        update_user_meta($user->ID, 'bbconnect_mailchimp_subscribe_optout_count', $optout+1);
                        $_SESSION['bbconnect_mailchimp_subscribe_ask'] = false;
                    }
                    break;
            }
        }
    }
}
