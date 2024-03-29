<?php
require_once(dirname(__FILE__)."/../../../wp-load.php");

if (!defined('BBCONNECT_MAILCHIMP_VERSION')) {
	// Plugin isn't active, abort
	return;
}

// Long-running requests to this script (e.g. campaign send tracking) sometimes result in MailChimp receiving a timeout.
// MailChimp will then send the webhook again as they read it as a failure, so if possible we try and return a response immediately so they know it was successful.
if (function_exists('fastcgi_finish_request')) {
	fastcgi_finish_request();
} else {
	ob_end_clean();
	ignore_user_abort(true);
	header("Connection: close\r\n");
	header("Content-Encoding: none\r\n");
	header("Content-Length: 0");
	flush();
}

if (isset($_POST['type'])) {
	$hook_date = bbconnect_get_datetime($_POST['fired_at'], new DateTimeZone('GMT')); // MailChimp always sends date as GMT
	$hook_date->setTimezone(bbconnect_get_timezone()); // Convert to local timezone
	$tracking_args = array(
			'type' => 'mailchimp',
			'source' => 'bbconnect-mailchimp',
			'title' => 'MailChimp Webhook Received: ',
			'date' => $hook_date->format('Y-m-d H:i:s'),
	);

	// WARNING!! HACK AHEAD!!
	// When adding a new user, the MC call is triggered before we create the user - so when MC comes back straight away to say they're subscribed, we can't find the user because they don't exist yet.
	// So we delay the lookup for a few seconds to give it a chance to create the user first. This doesn't impact the end user as this script is called by MC.
	// We also delay it if it's a profile update in case there's a change of email at the same time - so that we have time to update the email address before doing the lookup.
	if ($_POST['type'] == 'subscribe' || $_POST['type'] == 'profile') {
		sleep(5);
	}
	// HACK OVER. You can relax now.

	$mailchimp = bbconnect_mailchimp_get_client();
	if (!$mailchimp) {
		return;
	}

	try {
		if (isset($_POST['data']['list_id'])) {
			$list_details = $mailchimp->lists->getList($_POST['data']['list_id'], 'name');
		}
	} catch (Exception $e) {
		trigger_error($e->getMessage(), E_USER_WARNING);
		return;
	}

	if (!empty($_POST['data']['merges']['EMAIL']) || !empty($_POST['data']['old_email'])) {
		remove_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10);
		if ($_POST['type'] == 'upemail') {
			$email = $_POST['data']['old_email'];
		} else {
			$email = $_POST['data']['merges']['EMAIL'];
		}

		do_action('bbconnect_mailchimp_before_webhook', $email, $_POST);

		if (!email_exists($email)) {
			$userdata = array(
					'user_login' => $email,
					'first_name' => $_POST['data']['merges']['FNAME'],
					'last_name' => $_POST['data']['merges']['LNAME'],
					'user_email' => $email,
					'user_nicename' => $_POST['data']['merges']['FNAME'],
					'nickname' => $_POST['data']['merges']['FNAME'],
			);
			$user_id = wp_insert_user($userdata);
			update_user_meta($user_id, 'bbconnect_source', 'mailchimp');
		}
		$userobject = get_user_by('email', $email);
		if ($userobject instanceof WP_User) {
			$user_id = $userobject->data->ID;

			$tracking_args['user_id'] = $user_id;
			$tracking_args['email'] = $email;

			switch ($_POST['type']) {
				case 'subscribe':
					// Update Subscribe meta on user
					update_user_meta($user_id, 'bbconnect_bbc_subscription', 'true');
					$tracking_args['title'] .= 'Subscribe';
					$tracking_args['description'] = '<p>Subscribed to "'.$list_details->name.'".</p>';
					break;
				case 'unsubscribe':
					// Update Subscribe meta on user
					update_user_meta($user_id, 'bbconnect_bbc_subscription', 'false');
					$tracking_args['title'] .= 'Unsubscribe';
					$tracking_args['description'] = '<p>Unsubscribed from "'.$list_details->name.'".</p>';
					break;
				case 'cleaned':
					// Update Subscribe meta on user
					update_user_meta($user_id, 'bbconnect_bbc_subscription', 'false');
					$tracking_args['title'] .= 'Cleaned';
					$reason = $_POST['data']['reason'] == 'hard' ? 'hard bounce' : 'abuse report';
					$tracking_args['description'] = '<p>Email was forcibly removed from list "'.$list_details->name.'" due to '.$reason.'.</p>';
					break;
				case 'upemail':
					$userobject->data->user_email = $_POST['data']['new_email'];
					wp_update_user($userobject);
					$tracking_args['title'] .= 'Email Address Change';
					$tracking_args['description'] = '<p>Email address changed from '.$_POST['data']['old_email'].' to '.$_POST['data']['new_email'].'.</p>';
					break;
				case 'profile':
					$tracking_args['title'] .= 'Profile Update';
					$tracking_args['description'] = '<p>Updated profile details for "'.$list_details->name.'".</p>';
					break;
			}

			if ($_POST['type'] != 'upemail') { // Don't need to run this for a change of email address as we always get a separate profile call at the same time as an email update
				$profile_fields = apply_filters('bbconnect_mailchimp_synced_meta_fields', array());

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

				bbconnect_mailchimp_maybe_push_personalisation_key($user_id);
				bbconnect_mailchimp_pull_user_groups($user_id);
			}

			bbconnect_track_activity($tracking_args);
		}
	} elseif ($_POST['type'] == 'campaign') {
		do_action('bbconnect_mailchimp_before_webhook', null, $_POST);
		if ($_POST['data']['status'] == 'sent') {
			$start = 0;
			do {
				$params = array(
						'cid' => $_POST['data']['id'],
						'opts' => array(
								'start' => $start,
								'limit' => 100,
						),
				);
				$recipients = $mailchimp->call('reports/sent-to', $params);
				$total = $recipients['total'];
				foreach ($recipients['data'] as $recipient) {
					$email = $recipient['member']['email'];
					$userobject = get_user_by('email', $email);
					if ($userobject instanceof WP_User) {
						$user_id = $userobject->data->ID;

						$tracking_args['user_id'] = $user_id;
						$tracking_args['email'] = $email;
						$tracking_args['title'] = 'MailChimp Webhook Received: Campaign "'.$_POST['data']['subject'].'"';
						if ($recipient['status'] == 'sent') {
							$description = 'was sent successfully';
						} else {
							$description = 'could not be sent';
						}
						$tracking_args['description'] = '<p>Campaign '.$description.'.</p>';
						bbconnect_track_activity($tracking_args);
					}
				}
				$start++;
			} while (($start*100) <= $total);
		}
	}

	do_action('bbconnect_mailchimp_after_webhook', $email, $_POST);
}

echo 'Thanks!';
