<?php
require_once realpath(__DIR__) . '/../abstractClass.defaultController.php';

require_once realpath(__DIR__) . '/../../models/class.usersModel.php';



abstract class apiController extends defaultController {
		
	const TOP_LEVEL_GET_LIMIT = 64; // max number of top level data objects on each call (Top level objects = the data directly queries by API)
	const SUB_LEVEL_GET_LIMIT = 8; // max number of sub level data objects to get on each call (Sub level objects = the data accompanied to top level object)

	
	function __construct() {
   		global $request, $logging;
   		   		
   		// exempt these login operations from authentication
   		$this->exemptOperationFromAuthentication('*', 'login');
   		
   		parent::__construct();

		// Check access control
		$user_id = null;
		$input_auth_token = null;
		$resource_access_level = usersModel::USER_TYPE_REGULAR; // require REGULAR user previlege
		
		$input_vars = $request->variables;
		if (isset ( $input_vars ['user_id'] )) {
			$user_id = $input_vars ['user_id'];
		} else if (isset ( $request->headers ['X-Pmmf-User-Id'] )) { // try http header
			$user_id = $request->headers ['X-Pmmf-User-Id'];
		}
		if (isset ( $input_vars ['auth_token'] )) {
			$input_auth_token = $input_vars ['auth_token'];
		} else if (isset ( $request->headers ['X-Pmmf-Auth-Token'] )) { // try http header
			$input_auth_token = $request->headers ['X-Pmmf-Auth-Token'];
		}
		
		$ac_classname = get_class($this->access_control);
		$this->check_access_control ( $user_id, $resource_access_level, $ac_classname::$area_api, $input_auth_token );

		// **Options**
		//
		// *** For API, We NORMALLY DO NOT extend the expiry for API. Clients need to re-authenticate before expiry
		
		// If you want to extend the auth expiry every time after a successful access, uncomment this line
		// $this->extend_expiry_access_control($user_id, $input_auth_token, $resource_access_level);
		
   		   		
 		$request->setReturnFormat('json');
 		
	}
	
	
	function login() {
		global $request, $logging;
		
		if(!$request->getError()) {
			$input_vars = $request->variables;
			
			$user_id = '';
			$email = '';
			$handle ='';
			$facebook_id = '';
			if(isset($input_vars['user_id']) && !empty($input_vars['user_id'])) {
				$user_id = $input_vars['user_id'];
			} else {
				if(isset($input_vars['email']) && !empty($input_vars['email'])) {
					$email = $input_vars['email'];
				} else {
					if(isset($input_vars['handle']) && !empty($input_vars['handle'])) {
						$handle = $input_vars['handle'];
					} else {
						$request->setError('Required parameter missing');
						$request->setHTTPReturnCode(400);
						$logging->logMsg(3, 'Required parameter missing or empty: user_id, email and handle');
					}
				}
			}
			$password = '';
			if(isset($input_vars['password']) && !empty($input_vars['password'])) {
				$password = $input_vars['password'];
			} else if(empty($facebook_id)) {
				$request->setError('Required parameter missing');
				$request->setHTTPReturnCode(400);
				$logging->logMsg(3, 'Required parameter missing or empty: password');
			}
			
			if(!$request->getError()) {
				$users_model = new usersModel();
				$user_info = array();
				$user_info = $users_model->checkPassword($user_id, $email, $handle, $password);
				
				
				if(!empty($user_info)) {
					if($user_info['status'] == usersModel::USER_STATUS_ACTIVE) { // this checks for disabled user
						$access_info = $this->access_control->set($user_info['id'], $user_info['type'], auth::$area_api);
						if($access_info !== FALSE) {
							$access_info['refresh_token'] = $access_info['current_refresh_token'];
							$access_info['session_time_valid'] = config::$session_time_valid;
							unset($access_info['current_refresh_token']);
							unset($access_info['auth_token_expiry']);
							unset($access_info['refresh_token_expiry']);
							unset($access_info['user_type']);
							unset($access_info['this_logged_epoch_time']);
							unset($access_info['last_logged_epoch_time']);
							$request->setJsonReturnData($access_info);
							
						} else {
							$request->setError('Authentication error');
							$logging->logMsg(2, "Error set up authentication session  (login) (user_id=$user_info[id]))");
							$request->setHTTPReturnCode(401);
							
						}
					} else {
						$request->setError('Account not active');
						$logging->logMsg(3, "Non-active user account login attempt (by password) (user_id=$user_id / email=$email / handle=$handle)");
						$request->setHTTPReturnCode(403);
					}
				} else {
					$request->setError('Invalid credential');
					$logging->logMsg(2, "Wrong user identifier and token/password combination (login) (user_id=$user_id / email=$email / handle=$handle / facebook_id=$facebook_id)");
					$request->setHTTPReturnCode(401);
				}
			}
		}
	}
	
	function logout() {
		global $request, $logging;
		
		if(!$request->getError()) {
			// Get user_id from access_control to
			// make sure user_id is current authenticated user
			// and not logging out some other user
			$user_id = $this->access_control->getUserId();
			$access_info = $this->access_control->clear($user_id);
			$request->addJsonReturnData('user_id', $user_id);
			
		}
	}
	
	function refreshSession() {
		global $request, $logging;
		
		if(!$request->getError()) {
			$input_vars = $request->variables;
			
			$user_id = NULL;
			if(isset($input_vars['user_id']) && !empty($input_vars['user_id'])) {
				$user_id = $input_vars['user_id'];
			} else {
				$request->setError('Required parameter missing');
				$request->setHTTPReturnCode(400);
				$logging->logMsg(3, 'Required parameter missing or empty: user_id');
				
			}
			$refresh_token = NULL;
			if(isset($input_vars['refresh_token']) && !empty($input_vars['refresh_token'])) {
				$refresh_token = $input_vars['refresh_token'];
			} else if(empty($facebook_id)) {
				$request->setError('Required parameter missing');
				$request->setHTTPReturnCode(400);
				$logging->logMsg(3, 'Required parameter missing or empty: refresh_token');
			}
			
			if(!$request->getError()) {
				$users_model = new usersModel();
				$user_info = array();
				$user_info = $users_model->getUser($user_id, NULL, NULL);
				
				if(!empty($user_info)) {
					if($user_info['status'] == usersModel::USER_STATUS_ACTIVE) { // this checks for disabled user
						$access_info = $this->access_control->refresh($user_id, auth::$area_api, $refresh_token);
						if($access_info !== FALSE) {
							$access_info['refresh_token'] = $access_info['current_refresh_token'];
							$access_info['session_time_valid'] = config::$session_time_valid;
							$access_info['user_id'] = $user_id;
							unset($access_info['current_refresh_token']);
							unset($access_info['previous_refresh_token']);
							unset($access_info['auth_token_expiry']);
							unset($access_info['refresh_token_expiry']);
							unset($access_info['user_type']);
							unset($access_info['this_logged_epoch_time']);
							unset($access_info['last_logged_epoch_time']);
							
							$request->setJsonReturnData($access_info);
							$request->addJsonReturnData('version_compatibility', $this->version_control->getAudit());
							
						} else {
							$request->setError('Invalid credential');
							if($this->access_control->getAudit() == auth::$audit_credential_mismatched) {
								$logging->logMsg(2, "Wrong user identifier and token combination (refreshSession) (user_id=$user_id / refresh_token=$refresh_token)");
								$request->setHTTPReturnCode(401);
							} else if($this->access_control->getAudit() == auth::$audit_credential_expired) {
								$logging->logMsg(2, "Refresh token expired (refreshSession) (user_id=$user_id / refresh_token=$refresh_token)".$this->access_control->getAudit());
								$request->setHTTPReturnCode(401);
							}
						}
					} else {
						$request->setError('Account disabled');
						$logging->logMsg(3, "Diabled user account session refresh attempt (user_id=$user_id)");
						$request->setHTTPReturnCode(403);
					}
				} else {
					$request->setError('No such user');
					$logging->logMsg(2, "No such user found (refreshSession) (user_id=$user_id)");
					$request->setHTTPReturnCode(401);
				}
				
				
			}
		}
		
		
	}
	
	// Convert data from DB to proper values to return
	function _convertDatetimeFromDB(&$info_array) {
   		global $request, $logging;
   		
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

}