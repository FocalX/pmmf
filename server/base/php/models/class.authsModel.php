<?php
require_once 'abstractClass.defaultModel.php';

class authsModel extends defaultModel {

	function __construct() {
        parent::__construct();
    }

    function __destruct() {
        parent::__destruct();
    }

    // Replace (add or update)
 	function replaceAuth($user_id, $area, $user_type, $auth_token, $previous_refresh_token, $current_refresh_token, 
 				$auth_token_expiry,  $refresh_token_expiry, $last_logged_datetime, $this_logged_datetime=null) {
    	global $logging, $request;
 		
    	$user_id_input_escaped = $this->_db->escapeUserInput($user_id);
    	$area_input_escaped = $this->_db->escapeUserInput($area);
		$user_id_escaped = database::getProperInsertQueryValue($user_id_input_escaped);
		$area_escaped = database::getProperInsertQueryValue($area_input_escaped);
		$user_type_escaped = database::getProperInsertQueryValue($this->_db->escapeUserInput($user_type));
		$auth_token_escaped = database::getProperInsertQueryValue($this->_db->escapeUserInput($auth_token));
		$previous_refresh_token_escaped = database::getProperInsertQueryValue($this->_db->escapeUserInput($previous_refresh_token));
		$current_refresh_token_escaped = database::getProperInsertQueryValue($this->_db->escapeUserInput($current_refresh_token));
		$auth_token_expiry_escaped = database::getProperInsertQueryValue($this->_db->escapeUserInput($auth_token_expiry));
		$refresh_token_expiry_escaped = database::getProperInsertQueryValue($this->_db->escapeUserInput($refresh_token_expiry));
		$last_logged_datetime_escaped = database::getProperInsertQueryValue($this->_db->escapeUserInput($last_logged_datetime));
		$this_logged_datetime_escaped = 'now()';
		if(!is_null($this_logged_datetime)) {
			$this_logged_datetime_escaped = database::getProperInsertQueryValue($this->_db->escapeUserInput($this_logged_datetime));
		}
		
		$user_type_update_escaped = database::getProperUpdateQueryValue('user_type', $this->_db->escapeUserInput($user_type));
		$auth_token_update_escaped = database::getProperUpdateQueryValue('auth_token', $this->_db->escapeUserInput($auth_token));
		$previous_refresh_token_update_escaped = database::getProperUpdateQueryValue('previous_refresh_token', $this->_db->escapeUserInput($previous_refresh_token));
		$current_refresh_token_update_escaped = database::getProperUpdateQueryValue('current_refresh_token', $this->_db->escapeUserInput($current_refresh_token));
		$auth_token_expiry_update_escaped = database::getProperUpdateQueryValue('auth_token_expiry', $this->_db->escapeUserInput($auth_token_expiry));
		$refresh_token_expiry_update_escaped = database::getProperUpdateQueryValue('refresh_token_expiry', $this->_db->escapeUserInput($refresh_token_expiry));
		$last_logged_datetime_update_escaped = database::getProperUpdateQueryValue('last_logged_datetime', $this->_db->escapeUserInput($last_logged_datetime));
		$this_logged_datetime_update_escaped = 'this_logged_datetime=now(),';
		if(!is_null($this_logged_datetime)) {
			$this_logged_datetime_update_escaped = database::getProperUpdateQueryValue('this_logged_datetime', $this->_db->escapeUserInput($this_logged_datetime));
		}
		
		
		$query_addAuth = "INSERT INTO auths
   							(user_id, area, user_type, auth_token, previous_refresh_token, current_refresh_token, auth_token_expiry, refresh_token_expiry, this_logged_datetime,last_logged_datetime) 
   						VALUES
   							($user_id_escaped,$area_escaped,$user_type_escaped,$auth_token_escaped,$previous_refresh_token_escaped,$current_refresh_token_escaped,$auth_token_expiry_escaped,$refresh_token_expiry_escaped,$this_logged_datetime_escaped,$last_logged_datetime_escaped)
   						ON DUPLICATE KEY UPDATE
   							$user_type_update_escaped $auth_token_update_escaped $previous_refresh_token_update_escaped $current_refresh_token_update_escaped $auth_token_expiry_update_escaped $refresh_token_expiry_update_escaped $this_logged_datetime_update_escaped $last_logged_datetime_update_escaped";
   							
   		$query_addAuth = rtrim($query_addAuth, " ,"); // strip trailing whitespaces and comma	

   		if($this->_db->query($query_addAuth)) {
   			return true;
		} else {
			$request->setError('Database Error: query failed');
			$request->setHTTPReturnCode(500);
			$logging->logMsg(4, 'replace auths query failed: '.$this->_db->get_error(), __FILE__);
			return false;
		}
 	}
 	
 	function replaceAuthTransactionBegin() {
 		$this->_db->transaction_start();
 	}
 	
 	function replaceAuthTransactionEnd() {
 		$this->_db->transaction_commit();
 	}
 	
	// $for_update -- add 'FOR UPDATE' (mysql row lock). Must user within a transaction.
 	function getAuth($user_id, $area, $for_update=FALSE) {
    	global $logging, $request;
    	
    	$for_update_statement = '';
    	if($for_update) {
    		$for_update_statement = ' FOR UPDATE';
    	}
    	$query_getAuth = "SELECT user_type, auth_token, current_refresh_token, previous_refresh_token, auth_token_expiry, refresh_token_expiry, 
    							UNIX_TIMESTAMP(this_logged_datetime) AS this_logged_epoch_time, UNIX_TIMESTAMP(last_logged_datetime) AS last_logged_epoch_time
    						FROM auths
    						WHERE user_id = ".$this->_db->escapeUserInput($user_id)."
    						AND area = ".$this->_db->escapeUserInput($area)." limit 1 $for_update_statement";

    	if($result = $this->_db->query($query_getAuth)) {
    		return $this->_db->fetch_result_assoc_array($result);
    			
    	} else {
    		$request->setError('Database Error: query failed');
    		$request->setHTTPReturnCode(500);
    		$logging->logMsg(4, 'select auths query (getAuth) failed: '.$this->_db->get_error(), __FILE__);
    		return FALSE;
    	}
 	}
 	
 	function getAllAuths($user_id) {
    	global $logging, $request;
    	
    	$query_getAuth = "SELECT user_type, area, auth_token, current_refresh_token, auth_token_expiry, refresh_token_expiry, last_logged_datetime, 
    						this_logged_datetime, UNIX_TIMESTAMP(auth_token_expiry) AS auth_token_expiry_epoch_time, UNIX_TIMESTAMP(refresh_token_expiry) AS refresh_token_expiry_epoch_time
    						FROM auths
    						WHERE user_id = ".$this->_db->escapeUserInput($user_id);
		
		$return_result = array();
    	if($result = $this->_db->query($query_getAuth)) {
      		while($row = $this->_db->fetch_result_assoc_array($result)) {
      			$return_result[] = $row;
      		}
      		return $return_result;
    		    			
    	} else {
    		$request->setError('Database Error: query failed');
    		$request->setHTTPReturnCode(500);
    		$logging->logMsg(4, 'select auths query (getAllAuths) failed: '.$this->_db->get_error(), __FILE__);
    		return false;
    	}
 	}
 	
 	
 	
 	function deleteAuth($user_id, $area) {
    	global $logging, $request;
    	
    	$query_deleteAuth = "DELETE IGNORE FROM auths
    						WHERE user_id = ".$this->_db->escapeUserInput($user_id)."
    						AND area = ".$this->_db->escapeUserInput($area)." limit 1";
    	$logging->logMsg(0, $query_deleteAuth);
 		if($this->_db->query($query_deleteAuth)) {
			return true;
		} else {
			$request->setError('Database Error: query failed');
			$request->setHTTPReturnCode(500);
			$logging->logMsg(4, 'delete auths query failed: '.$this->_db->get_error(), __FILE__);
			return false;
		}
 		
 	}
 	
 	function invalidateAuth($user_id, $area=NULL) {
    	global $logging, $request;
    	
    	$query_deleteAuth = "UPDATE auths SET
    							auth_token = null,
    							previous_refresh_token = null,
    							current_refresh_token = null,
    							auth_token_expiry = null,
    							refresh_token_expiry = null
    						WHERE user_id = ".$this->_db->escapeUserInput($user_id);
    	if(!is_null($area)) {
    		$query_deleteAuth .= " AND area = ".$this->_db->escapeUserInput($area)." limit 1";
    	}
    	
 		if($this->_db->query($query_deleteAuth)) {
			return true;
		} else {
			$request->setError('Database Error: query failed');
			$request->setHTTPReturnCode(500);
			$logging->logMsg(4, 'invalidate auths query failed: '.$this->_db->get_error(), __FILE__);
			return false;
		}
 		
 	}
 	
 	
 	
 	function updateAuthExpiry($user_id, $area, $expiry) {
    	global $logging, $request;
    	
 		$user_id_escaped = $this->_db->escapeUserInput($user_id);
 		$area_escaped = $this->_db->escapeUserInput($area);
 		$expiry_escaped = $this->_db->escapeUserInput($expiry);
		
		$query_updateAuth = "UPDATE auths
   								SET auth_token_expiry = '$expiry_escaped' 
   								WHERE user_id = $user_id_escaped AND area = $area_escaped";
		
		if($this->_db->query($query_updateAuth)) {
			return true;
		} else {
			$request->setError('Database Error: query failed');
			$request->setHTTPReturnCode(500);
			$logging->logMsg(4, 'update auths query failed: '.$this->_db->get_error(), __FILE__);
			return false;
		}
 		
 	}
}