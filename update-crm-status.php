<?php
require_once(dirname(__FILE__)."/../../../wp-load.php");
if(isset($_POST['type'])) {
	$email = $_POST['data']['merges']['EMAIL'];
	$userobject = get_user_by('email',$email);
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
    }
}