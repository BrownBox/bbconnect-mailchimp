<?php
add_filter('gform_form_settings', 'bbconnect_mailchimp_custom_form_setting', 10, 2);
function bbconnect_mailchimp_custom_form_setting($settings, $form) {
    if (rgar($form, 'bbconnect_mailchimp_no_modal') == 'true') {
        $checked_text = 'checked="checked"';
    } else {
        $checked_text = '';
    }

    $settings['Form Options']['bbconnect_mailchimp_no_modal'] = '
        <tr>
            <th><label for="bbconnect_mailchimp_no_modal">Don\'t show subscribe modal</label></th>
            <td><label><input type="checkbox" value="true" '.$checked_text.' name="bbconnect_mailchimp_no_modal"> When enabled, Connexions MailChimp will automatically display a modal prior to form submission on any form containing an email address, asking the user to confirm that they are happy to be subscribed. Tick this option to override this functionality.</label></td>
        </tr>';
    return $settings;
}

add_filter('gform_pre_form_settings_save', 'bbconnect_mailchimp_save_form_setting');
function bbconnect_mailchimp_save_form_setting($form) {
    $form['bbconnect_mailchimp_no_modal'] = rgpost('bbconnect_mailchimp_no_modal');
    return $form;
}