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
		if (is_array($group)) { // API v2
			$group_name = $group['name'];
		} else { // API v3
			$group_name = $group->id;
		}
		// Remove fields
		$field_key = 'mailchimp_group_'.bbconnect_mailchimp_clean_group_name($delete_category, $group_name);
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
	$mailchimp = bbconnect_mailchimp_get_client();
	if ($mailchimp) {
		try {
			$list_id = get_option('bbconnect_mailchimp_list_id');
			$group_categories = $mailchimp->lists->getListInterestCategories($list_id);

			foreach ($group_categories->categories as $category) {
				if ($category->title == $create_category) {
					$groups = $mailchimp->lists->listInterestCategoryInterests($list_id, $category->id);
					update_option('bbconnect_mailchimp_current_groups', $groups->interests, false);
					// Add fields
					$fields = array();
					$default_groups = get_option('bbconnect_mailchimp_optin_groups');
					foreach ($groups->interests as $group) {
						$val = $default_groups[$group->id] == 'true' ? 'true' : 'false';
						$fields[] = array('source' => 'bbconnect', 'meta_key' => 'mailchimp_group_'.bbconnect_mailchimp_clean_group_name($create_category, $group->id), 'tag' => '', 'name' => $create_category.': '.$group->name, 'options' => array('admin' => true, 'user' => true, 'signup' => false, 'reports' => true, 'public' => false, 'req' => false, 'field_type' => 'checkbox', 'choices' => $val), 'help' => '');
					}
					$field_keys = array();

					foreach ($fields as $value) {
						if (false != get_option('bbconnect_'.$value['meta_key'])) {
							continue;
						}

						$field_keys[] = $value['meta_key'];
						add_option('bbconnect_'.$value['meta_key'], $value);
					}

					$umo = get_option('_bbconnect_user_meta');
					if (!empty($field_keys)) {
						foreach ($umo as $uv) {
							// Add to the preferences section
							foreach ($uv as $suv) {
								if ('bbconnect_preferences' == $suv) {
									$acct = get_option($suv);
									foreach ($field_keys as $fv) {
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
							foreach ($field_keys as $fv) {
								$umo['column_3'][] = 'bbconnect_' . $fv;
							}

							update_option('_bbconnect_user_meta', $umo);
						}
					}
				}
			}
		} catch (Exception $e) {
			// Do nothing
		}
	}
}

function bbconnect_mailchimp_mapped_groups() {
	$groups = get_option('bbconnect_mailchimp_current_groups');
	if ($groups === false) {
		$groups = array();
	}
	return $groups;
}

add_filter('bbconnect_mailchimp_push_data', 'bbconnect_mailchimp_push_data', 1, 2);
/**
 * Set up base list of data to send to MailChimp
 * @param array $merge_vars
 * @param integer|WP_User $user
 * @return array
 */
function bbconnect_mailchimp_push_data(array $merge_vars, $user) {
	if (is_numeric($user)) {
		$user = get_user_by('id', $user);
	}

	$profile_fields = apply_filters('bbconnect_mailchimp_synced_meta_fields', array());
	foreach ($profile_fields as $mc_field => $meta_key) {
		$meta_value = get_user_meta($user->ID, $meta_key, true);
		if ($meta_key == 'bbconnect_address_country_1') {
			$bbconnect_helper_country = bbconnect_helper_country();
			if (array_key_exists($meta_value, $bbconnect_helper_country)) {
				$meta_value = $bbconnect_helper_country[$meta_value];
			}
		}
		$merge_vars[$mc_field] = $meta_value;
	}

	return $merge_vars;
}

add_filter('bbconnect_kpi_cron_mailchimp_push_data', 'bbconnect_mailchimp_kpi_cron_push_data', 10, 3);
function bbconnect_mailchimp_kpi_cron_push_data($push_data, $user, $kpi_prefix) {
	return bbconnect_mailchimp_push_data($push_data, $user);
}

add_filter('bbconnect_mailchimp_synced_meta_fields', 'bbconnect_mailchimp_synced_meta_fields', 1);
function bbconnect_mailchimp_synced_meta_fields(array $fields) {
	return array(
			'COUNTRY' => 'bbconnect_address_country_1',
			'FNAME' => 'first_name',
			'LNAME' => 'last_name',
	);
}

if (!function_exists('subscribe_to_mailchimp')) { // backwards compatibility
	/**
	 * Subscribe user to MailChimp.
	 * @deprecated Use bbconnect_mailchimp_subscribe_user() instead.
	 * @see bbconnect_mailchimp_subscribe_user()
	 * @param integer $user_id
	 * @param boolean $force
	 */
	function subscribe_to_mailchimp($user_id, $force = false) {
		return bbconnect_mailchimp_subscribe_user($user_id, $force);
	}
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
	$email = $user->user_email;
	$merge_vars = array_filter(apply_filters('bbconnect_mailchimp_push_data', array(), $user));

	$groupings = apply_filters('bbconnect_mailchimp_default_groupings', array());
	$default_groups = array();
	if (!empty($groupings)) {
		foreach (array_keys($groupings[0]['groups']) as $group_id) {
			$default_groups[$group_id] = true;
		}
	}

	$mailchimp = bbconnect_mailchimp_get_client();
	if ($mailchimp) {
		try {
			$list_id = get_option('bbconnect_mailchimp_list_id');
			// If they are in the list at all, this will return their details
			// Otherwise it will throw a GuzzleHttp\Exception\ClientException which we catch below
			$subscriber = $mailchimp->lists->getListMember($list_id, $email);
			switch ($subscriber->status) {
				case 'subscribed':
					remove_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10);
					update_user_meta($user->ID, 'bbconnect_bbc_subscription', 'true');
					add_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10, 5);
					return 'Already subscribed';
					break;
				case 'unsubscribed':
				case 'transactional':
				case 'archived':
					if ($force) {
						$body = array(
								'status' => 'subscribed',
								'merge_fields' => $merge_vars,
								'interests' => $default_groups,
						);
						$subscriber = $mailchimp->lists->setListMember($list_id, $email, $body, true);
						if (empty($subscriber->id)) {
							return 'Failed to resubscribe';
						} else {
							remove_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10);
							update_user_meta($user->ID, 'bbconnect_bbc_subscription', 'true');
							add_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10, 5);
							return true;
						}
					}
					break;
				case 'cleaned':
				case 'pending':
					// If they've been cleaned or are already pending there's no point in trying to resubscribe them
					return false;
					break;
			}
		} catch (Spark\MailChimp\Vendor\GuzzleHttp\Exception\ClientException $e) {
			switch ($e->getResponse()->getStatusCode()) {
				case 404:
					// Never subscribed - add them
					try {
						$body = array(
								'email_address' => $email,
								'status' => 'subscribed',
								'merge_fields' => $merge_vars,
								'interests' => $default_groups,
						);
						$subscriber = $mailchimp->lists->addListMember($list_id, $body, true);
						if (empty($subscriber->id)) {
							return 'Failed to add subscriber';
						} else {
							remove_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10);
							update_user_meta($user->ID, 'bbconnect_bbc_subscription', 'true');
							add_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10, 5);
							return true;
						}
					} catch (Exception $e) {
						return false;
					}
					break;
				case 400:
					// I wish there was a better way to parse this error
					// Fingers crossed MailChimp don't ever change the wording
					if (strpos($e->getMessage(), 'Member In Compliance State') !== false) {
						// They've previously unsubscribed - will have to manually resubscribe
						bbconnect_mailchimp_send_resubscribe_email($email);
					}
					break;
			}
			return false;
		} catch (Exception $e) {
			return $e->getMessage();
		}
	}
	return false;
}

function bbconnect_mailchimp_send_resubscribe_email($email) {
	$recipient = get_option('bbconnect_mailchimp_resubscribe_recipient');
	$message = get_option('bbconnect_mailchimp_resubscribe_message');
	if ('none' != $recipient && !empty($message)) {
		$subject = get_option('bbconnect_mailchimp_resubscribe_subject');
		$firstname = 'Friend';
		$lastname = '';
		$user = get_user_by('email', $email);
		if ($user instanceof WP_User) {
			$firstname = $user->user_firstname;
			$lastname = $user->user_lastname;
		}
		// Clean up weird quotes issue from Connexions
		$message = stripslashes(html_entity_decode($message));
		$message = wpautop(str_replace(array('%%firstname%%', '%%lastname%%', '%%email%%'), array($firstname, $lastname, $email), $message));
		$content_type = function() {return 'text/html';};
		add_filter('wp_mail_content_type', $content_type);
		if (in_array($recipient, array('subscriber', 'both'))) {
			wp_mail($email, $subject, $message);
		}
		if (in_array($recipient, array('admin', 'both'))) {
			$admin_email = get_option('bbconnect_mailchimp_resubscribe_admin_email') ?: get_option('admin_email');
			wp_mail($admin_email, $subject, $message);
		}
		remove_filter('wp_mail_content_type', $content_type);
	}
}

add_filter('bbconnect_mailchimp_default_groupings', 'bbconnect_mailchimp_default_groupings', 0);
function bbconnect_mailchimp_default_groupings(array $groupings = array()) {
	$default_groups = get_option('bbconnect_mailchimp_optin_groups');
	$mapped_groups = bbconnect_mailchimp_mapped_groups();
	$groups = array();
	foreach ($mapped_groups as $mapped_group) {
		if ($default_groups[$mapped_group->id] == 'true') {
			$groups[$mapped_group->id] = $mapped_group->name;
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
	$mailchimp = bbconnect_mailchimp_get_client();
	if ($mailchimp) {
		try {
			$list_id = get_option('bbconnect_mailchimp_list_id');
			$subscriber = $mailchimp->lists->getListMember($list_id, $email);
			return $subscriber->status == 'subscribed';
		} catch (Spark\MailChimp\Vendor\GuzzleHttp\Exception\ClientException $e) {
			if ($e->getResponse()->getStatusCode() == 404) {
				// A 404 means the request was successful but the email address doesn't exist
				return false;
			}
			// Any other error means we couldn't retrieve the details
			return null;
		} catch (Exception $e) {
			return null;
		}
	}
	return null;
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
		foreach ($groupings as $grouping) {
			foreach (array_keys($grouping['groups']) as $id) {
				$meta_key = 'bbconnect_mailchimp_group_'.bbconnect_mailchimp_clean_group_name($grouping['name'], $id);
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

		$mailchimp = bbconnect_mailchimp_get_client();
		if ($mailchimp) {
			try {
				// Get user details from MailChimp so we don't lose any non-mapped group settings
				$list_id = get_option('bbconnect_mailchimp_list_id');
				$subscriber = $mailchimp->lists->getListMember($list_id, $email);
				$groups = $subscriber->interests;
				$mapped_groups = bbconnect_mailchimp_mapped_groups();
				foreach ($mapped_groups as $group) {
					$meta_key = 'bbconnect_mailchimp_group_'.bbconnect_mailchimp_clean_group_name($mapped_category, $group->id);
					$groups->{$group->id} = get_user_meta($user->ID, $meta_key, true) == 'true';
				}
				$mailchimp->lists->updateListMember($list_id, $email, array('interests' => $groups));
			} catch (Exception $e) {
				 // Do nothing
			}
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

		$mailchimp = bbconnect_mailchimp_get_client();
		if ($mailchimp) {
			remove_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10); // Don't want to trigger the filter again otherwise we'll end up in an endless loop
			try {
				$list_id = get_option('bbconnect_mailchimp_list_id');
				$subscriber = $mailchimp->lists->getListMember($list_id, $email);
				if ($subscriber->status == 'subscribed') {
					$groups = $subscriber->interests;
					$mapped_groups = bbconnect_mailchimp_mapped_groups();
					foreach ($mapped_groups as $group) {
						$meta_key = 'bbconnect_mailchimp_group_'.bbconnect_mailchimp_clean_group_name($mapped_category, $group->id);
						update_user_meta($user->ID, $meta_key, $groups->{$group->id} ? 'true' : 'false');
					}
				}
			} catch (Exception $e) {
				// Do nothing
			}
			update_user_meta($user->ID, 'bbconnect_mailchimp_last_group_update', current_time('timestamp'));
			add_filter('update_user_metadata', 'bbconnect_mailchimp_update', 10, 5);
		}
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
			$mailchimp = bbconnect_mailchimp_get_client();
			if ($mailchimp) {
				try {
					$list_id = get_option('bbconnect_mailchimp_list_id');
					$mailchimp->lists->updateListMember($list_id, $email, array('merge_fields' => array('KEY' => $key)));
				} catch (Exception $e) {
					// Do nothing
				}
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

	$mailchimp_fields = apply_filters('bbconnect_mailchimp_synced_meta_fields', array());

	$mailchimp = bbconnect_mailchimp_get_client();
	if ($mailchimp) {
		$list_id = get_option('bbconnect_mailchimp_list_id');
		if ($meta_key == 'bbconnect_bbc_subscription') {
			if (empty($prev_value)) { // The existing value often doesn't get passed through so we'll grab it ourselves
				$prev_value = get_user_meta($user_id, $meta_key, true);
			}
			if ($meta_value != $prev_value) {
				if ($meta_value == 'false') {
					try {
						$mailchimp->lists->updateListMember($list_id, $email, array('status' => 'unsubscribed'));
					} catch (Exception $e) {
						// Do nothing
					}
				} elseif ($meta_value == 'true') {
					bbconnect_mailchimp_subscribe_user($user_id, true);
					// Now that they're subscribed, push their group membership to MailChimp too
					add_action('profile_update', 'bbconnect_mailchimp_push_user_groups', 99, 2);
				}
			}
		} elseif (strpos($meta_key, 'mailchimp_group') !== false) {
			if (empty($prev_value)) { // The existing value often doesn't get passed through so we'll grab it ourselves
				$prev_value = get_user_meta($user_id, $meta_key, true);
			}
			if ($meta_value != $prev_value) {
				add_action('profile_update', 'bbconnect_mailchimp_push_user_groups', 99, 2);
			}
		} elseif (in_array($meta_key, $mailchimp_fields)) {
			if ($meta_key == 'bbconnect_address_country_1') {
				$bbconnect_helper_country = bbconnect_helper_country();
				$meta_value = $bbconnect_helper_country[$meta_value];
			}
			try {
				$mailchimp->lists->updateListMember($list_id, $email, array('merge_fields' => array(array_search($meta_key, $mailchimp_fields) => $meta_value)));
			} catch (Exception $e) {
				// Do nothing
			}
		} elseif ($meta_key == 'bbconnect_personalisation_key') { // Send personalisation key to MC
			bbconnect_mailchimp_maybe_push_personalisation_key($user_id, $meta_value);
		}
	}

	return null; // Tells WP to continue with saving the meta data
}

// Push group membership for users newly created through the admin
add_action('admin_init', 'bbconnect_mailchimp_push_new_user_groups');
function bbconnect_mailchimp_push_new_user_groups() {
	global $pagenow;
	if ($pagenow == 'users.php' && $_GET['page'] == 'bbconnect_edit_user' && $_GET['msg'] == 'new') {
		bbconnect_mailchimp_push_user_groups($_GET['user_id']);
	}
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
	if (!empty($new_email) && !empty($old_email) && $new_email !== $old_email) {
		$mailchimp = bbconnect_mailchimp_get_client();
		if ($mailchimp) {
			try {
				$list_id = get_option('bbconnect_mailchimp_list_id');
				// If they are in the list at all, this will return their details
				// Otherwise it will throw a GuzzleHttp\Exception\ClientException which we catch below
				$subscriber = $mailchimp->lists->getListMember($list_id, $old_email);
				switch ($subscriber->status) {
					case 'subscribed':
						$body = array(
								'email_address' => $new_email,
						);
						$subscriber = $mailchimp->lists->updateListMember($list_id, $old_email, $body);
						break;
					case 'pending': // Old address was pending confirmation
					case 'cleaned': // Old address was invalid
						// If they're supposed to be subscribed, add them
						if ('true' == get_user_meta($user_id, 'bbconnect_bbc_subscription', true)) {
							bbconnect_mailchimp_subscribe_user($user_id);
						}
						return false;
						break;
				}
			} catch (Spark\MailChimp\Vendor\GuzzleHttp\Exception\ClientException $e) {
				if ($e->getResponse()->getStatusCode() == 404) {
					// Old address wasn't subscribed - if they're supposed to be subscribed, add them
					if ('true' == get_user_meta($user_id, 'bbconnect_bbc_subscription', true)) {
						bbconnect_mailchimp_subscribe_user($user_id);
					}
				}
			} catch (Exception $e) {
				// Do nothing
			}
		}
	}
}

add_filter('bbconnect_meta_tracked_fields', 'bbconnect_mailchimp_meta_tracked_fields');
function bbconnect_mailchimp_meta_tracked_fields($tracked_fields) {
	$mapped_category = get_option('bbconnect_mailchimp_channels_group');
	if (!empty($mapped_category)) {
		$groups = bbconnect_mailchimp_mapped_groups();
		if (is_array($groups)) {
			foreach ($groups as $group) {
				$tracked_fields['bbconnect_mailchimp_group_'.bbconnect_mailchimp_clean_group_name($mapped_category, $group->id)] = $group->name;
			}
		}
	}
	return $tracked_fields;
}

add_action('bbconnect_mailchimp_do_daily_updates', 'bbconnect_mailchimp_daily_updates');
/**
 * Checks mapped groups in MailChimp to make sure CRM fields still match.
 * Don't call this function directly - it's run as a daily WP cron at 3am (local time)
 */
function bbconnect_mailchimp_daily_updates() {
	$mapped_category = get_option('bbconnect_mailchimp_channels_group');
	if (!empty($mapped_category)) {
		$mailchimp = bbconnect_mailchimp_get_client();
		if ($mailchimp) {
			try {
				$list_id = get_option('bbconnect_mailchimp_list_id');
				$group_categories = $mailchimp->lists->getListInterestCategories($list_id);
				foreach ($group_categories->categories as $category) {
					if ($category->title == $mapped_category) {
						$groups = $mailchimp->lists->listInterestCategoryInterests($list_id, $category->id);
						if ($groups->interests !== bbconnect_mailchimp_mapped_groups()) { // Something has changed - remove the current fields and create new ones
							bbconnect_mailchimp_delete_group_fields($mapped_category);
							bbconnect_mailchimp_create_group_fields($mapped_category);
							update_option('bbconnect_mailchimp_last_group_update', current_time('timestamp'));
						}
						break;
					}
				}
			} catch (Exception $e) {
				// Do nothing
			}
		}
	}
}

add_action('bbconnect_mailchimp_do_hourly_updates', 'bbconnect_mailchimp_hourly_updates');
function bbconnect_mailchimp_hourly_updates() {
	bbconnect_mailchimp_pull_all_user_groups();
}

/**
 * Connect to MailChimp and get client object
 * @return \Spark\Connexions\MailChimp\Vendor\MailchimpMarketing\ApiClient|boolean False if connection failed, or client object on success
 */
function bbconnect_mailchimp_get_client() {
	$api_key = get_option('bbconnect_mailchimp_api_key');
	$server = get_option('bbconnect_mailchimp_server');
	if (empty($server) && strpos($api_key, '-') !== false) {
		$server = substr($api_key, strrpos($api_key, '-')+1);
	}

	try {
		$mailchimp = new \Spark\Connexions\MailChimp\Vendor\MailchimpMarketing\ApiClient();
		$mailchimp->setConfig(array(
				'apiKey' => $api_key,
				'server' => $server,
		));

		// Test the connection
		$mailchimp->ping->get();
	} catch (Exception $e) {
		return false;
	}

	return $mailchimp;
}
