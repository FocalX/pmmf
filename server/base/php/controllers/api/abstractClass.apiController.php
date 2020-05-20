<?php
require_once realpath(__DIR__) . '/../abstractClass.defaultController.php';

require_once realpath(__DIR__) . '/../../models/class.usersModel.php';



abstract class apiController extends defaultController {
		
	const TOP_LEVEL_GET_LIMIT = 64; // max number of top level data objects on each call (Top level objects = the data directly queries by API)
	const SUB_LEVEL_GET_LIMIT = 8; // max number of sub level data objects to get on each call (Sub level objects = the data accompanied to top level object)

	
	function __construct() {
   		global $request, $logging;
   		   		
   		parent::__construct();

		// Check access control
		$user_id = null;
		$input_auth_token = null;
		$resource_access_level = usersModel::USER_TYPE_REGULAR; // require REGULAR user previlege
		
		$input_vars = $request->variables;
		if (isset ( $input_vars ['user_id'] )) {
			$user_id = $input_vars ['user_id'];
		} else if (isset ( $request->headers ['X-Joiiin-User-Id'] )) { // try http header
			$user_id = $request->headers ['X-Joiiin-User-Id'];
		}
		if (isset ( $input_vars ['auth_token'] )) {
			$input_auth_token = $input_vars ['auth_token'];
		} else if (isset ( $request->headers ['X-Joiiin-Auth-Token'] )) { // try http header
			$input_auth_token = $request->headers ['X-Joiiin-Auth-Token'];
		}
		
		$ac_classname = get_class($this->access_control);
		$this->check_access_control ( $user_id, $resource_access_level, $ac_classname::$area_api, $input_auth_token );

		// **Options**
		//
		// *** For API, We NORMALLY DO NOT extend the expiry for API. Clients need to re-authenticate before expiry
		
		// If you want to extend the auth expiry every time after a successful access, uncomment this line
		// $this->extend_expiry_access_control($user_id, $input_auth_token, $resource_access_level);
		
		// some action/operation needs to be secured connection only
		$secured_connection_required = FALSE;
		foreach ( $this->secured_connection_required as $secured_action => $secured_operations ) {
			if (($request->action == $secured_action && $request->operation == $secured_operations)) {
				$secured_connection_required = TRUE;
				break;
			}
		}
		if ($secured_connection_required && ! $request->isSecureConnection ()) {
			
			$request->setError ( 'Secure connection required' );
			$request->setHTTPReturnCode ( 403 );
			$logging->logMsg ( logging::LOG_LEVEL_FATAL, 'Secure connection required (action=' . $request->action . ' / operation=' . $request->operation . ')' );
		}
   			
   		   		
 		$request->setReturnFormat('json');
 		
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