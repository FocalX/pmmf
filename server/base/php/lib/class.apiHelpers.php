<?php 

class apiHelpers {

	// Convert data from DB to proper values to return
	static function convertDatetimeFromDB(&$info_array) {
		
		// make sure we deal with datetime in UTC
		// and datetime stored in DB is in UTC
		date_default_timezone_set('UTC');
		
		foreach($info_array as $key=>$value) {
			// convert birthdate
			if($key == 'birthdate') {
				if($info_array['birthdate'] == '0000-00-00' || is_null($info_array['birthdate'])) {
					$info_array['birthdate'] = NULL;
				} else {
					$info_array['birthdate'] = date('o-m-d', strtotime($info_array['birthdate'])); // get ISO 8601 date string
				}
			} else {
				// convert *_datetime
				$strpos = mb_strpos($key, '_datetime');  // match any element key that ends with '_datetime'
				if ($strpos !== false) {
					$key_epoch_time = mb_substr($key, 0, $strpos).'_epoch_time'; // takes the first portion and added '_epoch_time';
					if($value == '0000-00-00 00:00:00' || is_null($value)) {
						$info_array[$key_epoch_time] = NULL;
						$info_array[$key] = NULL;
					} else {
						$info_array[$key_epoch_time] = strtotime($value); // get epoch time
						$info_array[$key] = date(DATE_ISO8601, $info_array[$key_epoch_time]); // get ISO 8601 date string
					}
					
				}
			}
		}
	}
	

	/**
	 * Set user access to an auth area. Return with access information (auth_token, refresh_token, etc); false otherwise.
	 * @param unknown $access_control -- access control module instance
	 * @param int $user_id
	 * @param int $user_type
	 * @param int $auth_area
	 * @return array|boolean - array of access info; false otherwise 
	 */
	static function setAccess($access_control, $user_id, $user_type, $auth_area) {
		$access_info = $access_control->set($user_id, $user_type, $auth_area);
		if($access_info !== FALSE) {
			$access_info['refresh_token'] = $access_info['current_refresh_token'];
			$access_info['session_time_valid'] = config::$session_time_valid;
			unset($access_info['current_refresh_token']);
			unset($access_info['auth_token_expiry']);
			unset($access_info['refresh_token_expiry']);
			unset($access_info['user_type']);
			unset($access_info['this_logged_epoch_time']);
			unset($access_info['last_logged_epoch_time']);
			
			return $access_info;

			
		}
		return false;
		
	}

	/**
	 * Refresh user access to an auth area. Return with refreshed access information (auth_token, refresh_token, etc); false otherwise.
	 * @param unknown $access_control -- access control module instance
	 * @param int $user_id
	 * @param int $auth_area
	 * @param string $refresh_token
	 * @return array|boolean - array of access info; false otherwise 
	 */
	static function refreshAccess($access_control, $user_id, $auth_area, $refresh_token) {
		$access_info = $access_control->refresh($user_id, $auth_area, $refresh_token);
		if($access_info !== FALSE) {
			$access_info['user_id'] = $user_id;
			$access_info['refresh_token'] = $access_info['current_refresh_token'];
			$access_info['session_time_valid'] = config::$session_time_valid;
			unset($access_info['current_refresh_token']);
			unset($access_info['auth_token_expiry']);
			unset($access_info['refresh_token_expiry']);
			unset($access_info['user_type']);
			unset($access_info['this_logged_epoch_time']);
			unset($access_info['last_logged_epoch_time']);
			
			return $access_info;
			
			
		}
		return false;
		
	}
	
}