<?php
require_once 'abstractClass.defaultModel.php';

class usersModel extends defaultModel {
    
    const USER_TYPE_SYSADMIN = 1;
    const USER_TYPE_ADMIN = 2;
    const USER_TYPE_REGULAR = 3;
    
    const USER_STATUS_DISABLED = 1;
    const USER_STATUS_ACTIVE = 2;
    const USER_STATUS_INACTIVE = 3;
    const USER_STATUS_BLOCKED = 4;
    
    
    function __construct() {
        parent::__construct();
    }
    
    function __destruct() {
        parent::__destruct();
    }
    
    /**
     *
     * Get User
     * @param int $id -user id
     * @param int $email - user's email
     * @param int $handle = user's handle
     * @param int $all - return user even user has been disabled
     * @return an associated array of user info;
     * 			an empty array if user is not found;
     * 			FALSE if error.
     * Note: only one of $id, $email or $handle need to be specified. Leaves unused as NULL
     */
    function getUser($id, $email, $handle, $all=FALSE) {
    	global $logging, $request;
    	
    	$where = '';
    	if(!empty($id)) {
    		$where = "id = ".$this->_db->escapeUserInput($id);
    	} else if(!empty($email)) {
    		$where = "email = '".$this->_db->escapeUserInput($email)."'";
    	} else if(!empty($handle)) {
    		$where = "handle = '".$this->_db->escapeUserInput($handle)."'";
    	} else {
    		$request->setError('Internal error');
    		$request->setHTTPReturnCode(500);
    		$logging->logMsg(4, 'Missing required parameters (getUser)', __FILE__);
    		return false;
    		
    	}
    	
    	$no_disabled = '';
    	if(!$all) { // check if we want to include disabled activities
    		$no_disabled = ' AND (status!='.self::USER_STATUS_DISABLED.' AND status!='.self::USER_STATUS_INACTIVE.') ';
    	}
    	$query_getUser = "SELECT *
    						FROM users
    						WHERE $where $no_disabled limit 1";
    	
    	if($result = $this->_db->query($query_getUser)) {
    		if($this->_db->get_result_num_rows($result) == 0){
    			return array(); // return an empty array if nothing found
    		} else {
    			return $this->_db->fetch_result_assoc_array($result);
    		}
    		
    	} else {
    		$request->setError('Database Error: query failed');
    		$request->setHTTPReturnCode(500);
    		$logging->logMsg(4, 'select users query (getUser) failed: '.$this->_db->get_error(), __FILE__);
    		return false;
    	}
    	
    }
    
    function updateUser($id, $changes_array) {
    	global $logging, $request;
    	
    	$id_escaped = $this->_db->escapeUserInput($id);
    	$type_escaped = $this->_db->parseChangesArrayForUpdate('type', $changes_array);
    	$status_escaped = $this->_db->parseChangesArrayForUpdate('status', $changes_array);
    	$handle_escaped = $this->_db->parseChangesArrayForUpdate('handle', $changes_array);
    	$email_escaped = $this->_db->parseChangesArrayForUpdate('email', $changes_array);
    	$first_name_escaped = $this->_db->parseChangesArrayForUpdate('first_name', $changes_array);
    	$last_name_escaped = $this->_db->parseChangesArrayForUpdate('last_name', $changes_array);
    	$mobile_phone_number_escaped = $this->_db->parseChangesArrayForUpdate('mobile_phone_number', $changes_array);
    	$mobile_phone_country_code_escaped = $this->_db->parseChangesArrayForUpdate('mobile_phone_country_code', $changes_array);
    	$facebook_id_escaped = $this->_db->parseChangesArrayForUpdate('facebook_id', $changes_array);
    	$about_escaped = $this->_db->parseChangesArrayForUpdate('about', $changes_array);
    	$url_escaped = $this->_db->parseChangesArrayForUpdate('url', $changes_array);
    	$birthdate_escaped = $this->_db->parseChangesArrayForUpdate('birthdate', $changes_array);
    	$gender_escaped = $this->_db->parseChangesArrayForUpdate('gender', $changes_array);
    	$chat_id_escaped = $this->_db->parseChangesArrayForUpdate('chat_id', $changes_array);
    	$location_city_escaped = $this->_db->parseChangesArrayForUpdate('location_city', $changes_array);
    	$location_province_escaped = $this->_db->parseChangesArrayForUpdate('location_province', $changes_array);
    	$location_country_escaped = $this->_db->parseChangesArrayForUpdate('location_country', $changes_array);
    	$location_lat_escaped = $this->_db->parseChangesArrayForUpdate('location_lat', $changes_array);
    	$location_long_escaped = $this->_db->parseChangesArrayForUpdate('location_long', $changes_array);
    	$application_restriction_escaped = $this->_db->parseChangesArrayForUpdate('application_restriction', $changes_array);
    	$mphone_confirmed_escaped = $this->_db->parseChangesArrayForUpdate('mphone_confirmed', $changes_array);
    	$email_confirmed_escaped = $this->_db->parseChangesArrayForUpdate('email_confirmed', $changes_array);
    	$chat_data_channel_escaped = $this->_db->parseChangesArrayForUpdate('chat_data_channel', $changes_array);
    	$password_escaped = '';
    	if(array_key_exists('password', $changes_array)) {
    		$password_escaped = $this->_db->escapeUserInput($changes_array['password']);
    		$password_escaped = "password=AES_ENCRYPT('$password_escaped', '$password_escaped'),";
    	}
    	$query_update_string = "$handle_escaped $email_escaped $first_name_escaped $last_name_escaped $mobile_phone_number_escaped $mobile_phone_country_code_escaped
   							$facebook_id_escaped $about_escaped $url_escaped $birthdate_escaped $gender_escaped $chat_id_escaped
   							$location_city_escaped $location_province_escaped $location_country_escaped
   							$location_lat_escaped $location_long_escaped $application_restriction_escaped $mphone_confirmed_escaped $email_confirmed_escaped $chat_data_channel_escaped
							$password_escaped $type_escaped $status_escaped last_updated_datetime=now()";
							
							$query_updateUser = "UPDATE users SET $query_update_string WHERE id=$id_escaped limit 1";
							
							if($this->_db->query($query_updateUser)) {
								return TRUE;
							} else {
								$request->setError('Database Error: query failed');
								$request->setHTTPReturnCode(500);
								$logging->logMsg(4, 'update users query failed: '.$this->_db->get_error(), __FILE__);
								return FALSE;
							}
    }
    
    function checkPassword($user_id, $email, $handle, $password) {
    	global $request, $logging;
    	
    	$where = '';
    	if(!empty($user_id)) {
    		$where = "id = ".$this->_db->escapeUserInput($user_id);
    	} else if(!empty($email)) {
    		$where = "email = '".$this->_db->escapeUserInput($email)."'";
    	} else if(!empty($handle)) {
    		$where = "handle = '".$this->_db->escapeUserInput($handle)."'";
    	} else {
    		$request->setError('Internal error');
    		$request->setHTTPReturnCode(500);
    		$logging->logMsg(4, 'Missing required parameters (checkPassword)', __FILE__);
    		return false;
    		
    	}
    	
    	$escaped_password = $this->_db->escapeUserInput($password);
    	
    	$query_checkPassword = "SELECT id, type, status
                              FROM users
                              WHERE ".$where." AND AES_DECRYPT(password, '$escaped_password') = '$escaped_password' limit 1";
    	
    	if(!($result_checkPassword = $this->_db->query($query_checkPassword))) {
    		$request->setError('Database Error: query failed');
    		$request->setHTTPReturnCode(500);
    		$logging->logMsg(4, 'select users query (checkPassowrd) failed: '.$this->_db->get_error(), __FILE__);
    		return false;
    	}
    	if ($this->_db->get_result_num_rows($result_checkPassword) == 1) { // this checks the password matching
    		return $this->_db->fetch_result_assoc_array($result_checkPassword);
    	} else {
    		return false;
    	}
    	
    }
    
    
}