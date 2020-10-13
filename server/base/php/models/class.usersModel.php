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
    

    function addUser($type, $email, $handle, $password, $first_name, $last_name, $status) {
    			global $logging, $request;
    			
    			$type_escaped = $this->_db->parseInputValue($type);
    			$handle_escaped = $this->_db->parseInputValue($handle);
    			$email_escaped = $this->_db->parseInputValue($email);
    			$password_escaped = $this->_db->parseInputValue($password);
    			$first_name_escaped = $this->_db->parseInputValue($first_name);
    			$last_name_escaped = $this->_db->parseInputValue($last_name);
    			$status_escaped = $this->_db->parseInputValue($status);
    			
    			$query_addUser = "INSERT INTO users
   							(handle, email, password, first_name, last_name, type, status, created_datetime, last_updated_datetime)
   						VALUES
   							($handle_escaped,$email_escaped,AES_ENCRYPT($password_escaped, $password_escaped),$first_name_escaped,$last_name_escaped, $type_escaped, $status_escaped, now(), now())";
   							
   				if($this->_db->query($query_addUser)) {
   						return $this->_db->get_last_insert_id();
   				} else {
   					if($this->_db->get_errno() == database::$MYSQL_ERRNO_DUPLICATE_ENTRY) {
   						// Not considered as error in model level.
   						// (should be handled by controller)
   						return 0;
   					} else {
   						throw new pmmfException('Database Error: query failed', 500,
   								array(logging::LOG_LEVEL_FATAL, 'insert users (addUser) query failed: '.$this->_db->get_error(), __FILE__));
   					}
   				}
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
    		throw new pmmfException('Internal error', 500,
    				array(logging::LOG_LEVEL_FATAL, 'Missing required parameters (getUser)', __FILE__));
    		
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
    		throw new pmmfException('Database Error: query failed', 500,
    				array(logging::LOG_LEVEL_FATAL, 'select users (getUser) query failed: '.$this->_db->get_error(), __FILE__));
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
    	$password_escaped = '';
    	if(array_key_exists('password', $changes_array)) {
    		$password_escaped = $this->_db->escapeUserInput($changes_array['password']);
    		$password_escaped = "password=AES_ENCRYPT('$password_escaped', '$password_escaped'),";
    	}
    	$query_update_string = "$handle_escaped $email_escaped $first_name_escaped $last_name_escaped $type_escaped $status_escaped last_updated_datetime=now()";
							
							$query_updateUser = "UPDATE users SET $query_update_string WHERE id=$id_escaped limit 1";
							
		if($this->_db->query($query_updateUser)) {
			return TRUE;
		} else {
			throw new pmmfException('Database Error: query failed', 500,
					array(logging::LOG_LEVEL_FATAL, 'update users (updateUser) query failed: '.$this->_db->get_error(), __FILE__));
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
    		throw new pmmfException('Internal error', 500,
    				array(logging::LOG_LEVEL_FATAL, 'Missing required parameters (checkPassword)', __FILE__));
    		
    		
    	}
    	
    	$escaped_password = $this->_db->escapeUserInput($password);
    	
    	$query_checkPassword = "SELECT id, type, status
                              FROM users
                              WHERE ".$where." AND AES_DECRYPT(password, '$escaped_password') = '$escaped_password' limit 1";
    	
    	if(!($result_checkPassword = $this->_db->query($query_checkPassword))) {
	   		throw new pmmfException('Database Error: query failed', 500,
    				array(logging::LOG_LEVEL_FATAL, 'select users (checkPassowrd) query failed: '.$this->_db->get_error(), __FILE__));
    	}
    	if ($this->_db->get_result_num_rows($result_checkPassword) == 1) { // this checks the password matching
    		return $this->_db->fetch_result_assoc_array($result_checkPassword);
    	} else {
    		return false;
    	}
    	
    }
    
    
}