<?php
add_action( 'admin_menu', 'register_membership_fee_menu_page' );

function register_membership_fee_menu_page(){
	add_submenu_page('bbconnect_options', 'MailChimp', 'MailChimp', 'manage_options', 'bbconnect_mailchimp_options', 'bbconnect_mailchimp_options_page');
    add_action('admin_init', 'register_bbconnect_mailchimp_options');
}

function bbconnect_mailchimp_options_page(){
    echo '<div class="wrap">'."\n";
    echo '<h2>MailChimp Settings</h2>'."\n";
	echo "<form action='options.php' method='post'>"."\n";
	echo "<label>List ID:</label><input type='text' name='bbconnect_mailchimp_list_id' value='".get_option('bbconnect_mailchimp_list_id')."' ><br>"."\n";
	echo "<label>API Key:</label><input type='text' name='bbconnect_mailchimp_api_key' value='".get_option('bbconnect_mailchimp_api_key')."' ><br>"."\n";

    submit_button();
    settings_fields('bbconnect-mailchimp-options-group');
    do_settings_fields('bbconnect-mailchimp-options-group');

	echo '</form>'."\n";
}

function register_bbconnect_mailchimp_options() {
    $fields = array ('bbconnect_mailchimp_list_id', 'bbconnect_mailchimp_api_key');
    foreach ($fields as $field) {
        register_setting('bbconnect-mailchimp-options-group', $field);
    }
}