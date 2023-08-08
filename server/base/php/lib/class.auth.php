<?php
require_once $pmmf_base_location . 'models/class.authsModel.php';

/* Database schema for auths table
#--- create table for authentication
CREATE TABLE auths (
user_id int(8) unsigned not null,
area tinyint(1) unsigned not null default 0,
user_type tinyint(1) unsigned not null,
auth_token varchar(128),
current_refresh_token varchar(128),
previous_refresh_token varchar(128),
expiry datetime,
PRIMARY KEY(user_id, area)
)TYPE=InnoDB;
*/

/**
 * 
 * Class auth
 * 
 * Authentication class for authentication using token authorization.
 * An auth token will be created for use in authentication. 
 * 
 * This authentication model use token authentication, where a token is generated and passed between
 * clients and servers.
 * Advantages:
 * 	1. Work for both web application and API implementation.
 * 	2. Work well with multiple web nodes as using database to store persisitent information.
 * Disadvantages:
 * 	1. Using shared database where efficiency may suffer.
 *  2. Require controller to determine how token is stored and pass on the client side (through
 *  	API parameters or browser cookies, etc).
 *  
 * set() to return create and return an auth token. The generated auth token will
 * be stored in server for later verification.
 * check() to verify the auth token validility.
 * getAudit() to get the authentication audit result after called set() or check().
 * 
 * getAudit() should be called after set() or check() called, else result will 
 * be $audit_unknown
 *
 * @author peter
 *
 */
class auth {
	
	// authentication audit results
	public static $audit_authenticated = 0;
	public static $audit_credential_mismatched = 1;
	public static $audit_credential_expired = 2;
	public static $audit_access_level_failed = 3;
	public static $audit_unknown = 4;
	
	// authentication areas
	public static $area_unknown = 0;
	public static $area_admin = 1;
	public static $area_api = 2;
	public static $area_portal = 3;
	
	private $_audit = 4;   // authentication audit status
	private $_user_id = NULL;   // user Id os this auth belonging to
	private $_area = 0;         // area authenticated
	private $_auth_data = NULL;  // auth data from database

	public function __construct($user_id=NULL, $resource_access_level=NULL, $area=0, $input_auth_token=NULL) {
	    
		if(!empty($user_id) && !empty($input_auth_token) && !empty($resource_access_level)) {
						
			// Create new auth session and check authentication
			$this->_user_id = $user_id;
			$this->_area = $area;
			$this->_fetchAuthData();
			$this->check($user_id, $input_auth_token, $resource_access_level, $area);
		}
		
		// Otherwise, this create an empty (unauthenticated) auth session
		// Caller should eventually invoke check() or set() to authenticate this session
	}
	
	/**
	 * 
	 * Check authentication.
	 * Input auth data will be check against the stored auth data.
	 * @return Boolean. TRUE if input auth data is valid. FALSE otherwise.
	 * $this->_audit will be set. Check this variable for detail results (call getAudit())
	 */
	public function check($user_id, $resource_access_level, $area=0, $input_auth_token) {
		if(empty($user_id)) { // if user_id is empty, nothing to check against, just return FALSE
			$this->_audit = self::$audit_credential_mismatched;
			return FALSE;
		}
		
		$this->_user_id = $user_id;
		$this->_area = $area;
		
		if(!$this->_checkToken($input_auth_token)){
			$this->_audit = self::$audit_credential_mismatched;
			return FALSE;
		}
		
		// check session expire
		if(!$this->_checkExpiry()) {
			$this->_audit = self::$audit_credential_expired;
			return FALSE;
		}
		
		// check access level
		if(!$this->_checkAccessLevel($resource_access_level)) {
			$this->_audit = self::$audit_access_level_failed;
			return FALSE;
		}

		$this->_audit = self::$audit_authenticated;
		return TRUE;

	}
		
	/**
	 * update the authentication session expiry time. Auth session should be authenticated first.
	 * @param int $time -- timestamp. Expiry will be updated to the time given plus configured session time
	 */
	public function extendTime() {
		// Do not extend expiry if user is not authenticated first
		if($this->_audit !== self::$audit_authenticated) {
			return FALSE;
		}
		
		$expiry = date("Y-m-d H:i:s", time() + (config::$session_time_valid));
		
		$auths_model = new authsModel();
		if($auths_model->updateAuthExpiry($this->_user_id, $this->_area, $expiry)) {
			return true;
		} else {
			return false;
		}
				
	}

	/**
	 * 
	 * Clear the authentication session
	 */
	public function clear() {
		$auths_model = new authsModel();
		if($auths_model->invalidateAuth($this->_user_id, $this->_area)) {
			$this->authenticated = FALSE;
			$this->_auth_data = NULL;
			return TRUE;
		} else {
			return FALSE;
		}
		
	}

	/**
	 * 
	 * Set new authentication session.
	 * Note that user validation is the responsibility of caller controller. Caller should validate user 
	 * before calling this function. Calling this function will automatically create a valid auth session and
	 * 'authenticated' user ($this->_audit will be set to 'authenticated').
	 * 
	 * $param int $user_id -- user's Id
	 * @param int $user_type -- user's type as defined by UserModel
	 * @param int $area -- authenticated area (admin, api, etc)
	 * @return Authentication data. An assoc. array of ['user_id', 'user_type', 'auth_token', 'expiry']
	 *
	 */
	public function set($user_id, $user_type, $area=0) {
		if(empty($user_id)) { // no user_id, no authentication
			$this->_audit = self::$audit_unknown;
			$this->_auth_data = NULL;
			return FALSE;
		}
		
		$this->_user_id = $user_id;
		$this->_area = $area;
		
		// generate the tokens
		$auth_token = $this->_secureUniqid(64);
		$auth_token_expiry = date("Y-m-d H:i:s", time() + (config::$session_time_valid));
		$current_refresh_token = $this->_secureUniqid(64);
		$refresh_token_expiry = date("Y-m-d H:i:s", time() + (config::$session_reauth_time_valid));
		
		
		$auths_model = new authsModel();
		
		// Get the old data
		$old_auth_data =$auths_model->getAuth($this->_user_id, $this->_area);
		$last_logged_epoch_time = NULL;
		if(isset($old_auth_data['this_logged_epoch_time'])) {
			$last_logged_epoch_time = $old_auth_data['this_logged_epoch_time'];  // last logged time is the this logged time of old data
		}
		$previous_refresh_token = NULL;

		// Set the new data
		if($auths_model->replaceAuth($this->_user_id, $this->_area, $user_type, $auth_token, $previous_refresh_token, $current_refresh_token, $auth_token_expiry, $refresh_token_expiry, database::getDatetimeValue($last_logged_epoch_time))) {
			$this->_auth_data= array('user_id'=>$this->_user_id,
								'user_type'=>$user_type,
								'auth_token'=>$auth_token,
								'current_refresh_token'=>$current_refresh_token,
								'auth_token_expiry'=>$auth_token_expiry,
								'refresh_token_expiry'=>$refresh_token_expiry,
								'this_logged_epoch_time'=>time(),  // note: this isn't exactly the same db recorded. Maybe off for minimal time. Close enough to be acceptable.
								'last_logged_epoch_time'=>$last_logged_epoch_time);
			$this->_audit = self::$audit_authenticated;
			return $this->_auth_data;
		} else {
			$this->_audit = self::$audit_unknown;
			$this->_auth_data = NULL;
			return FALSE;
		}
	}
	
	// locking version
// 	public function refresh($user_id, $area, $input_refresh_token) {

// 		if(empty($user_id)) { // no user_id, no authentication
// 			$this->_audit = self::$audit_unknown;
// 			$this->_auth_data = NULL;
// 			return FALSE;
// 		}
// 		$this->_user_id = $user_id;
// 		$this->_area = $area;
		
// 		$auths_model = new authsModel();
		
// 		// Get the old data
// 		$auths_model->replaceAuthTransactionBegin();  // lock the data row
// 		$old_auth_data =$auths_model->getAuth($this->_user_id, $this->_area, TRUE);
		
// 		// Check valid refresh token
// 		$refresh_check = 'invalid';
// 		if($input_refresh_token == $old_auth_data['current_refresh_token']) {
// 			if(time() < strtotime($old_auth_data['refresh_token_expiry'])) {
// 				$refresh_check = 'new'; // a new refresh token is needed
// 			} else {
// 				$refresh_check = 'curr_expired'; // curr refresh token expired
// 			}
// 		} else if($input_refresh_token == $old_auth_data['previous_refresh_token']) { // using previous refresh token
// 			if(time() < $old_auth_data['this_logged_epoch_time'] + 300) { // we allow previous refresh token valid for 5 more minutes
// 				$refresh_check = 'resend'; // resend existing refresh token
// 			} else {
// 				$refresh_check = 'prev_expired'; // previous refresh token expired
// 			}
// 		}
		
// 		if($refresh_check == 'resend') {
// 			$this->_auth_data= $old_auth_data;
// 			$this->_audit = self::$audit_authenticated;
// 			return $this->_auth_data;
// 		} else if($refresh_check == 'new') {
// 			// generate new tokens
// 			$auth_token = $this->_secureUniqid(64);
// 			$auth_token_expiry = date("Y-m-d H:i:s", time() + (config::$session_time_valid));
// 			$current_refresh_token = $this->_secureUniqid(64);
// 			$refresh_token_expiry = date("Y-m-d H:i:s", time() + (config::$session_reauth_time_valid));
					
// 			$last_logged_epoch_time = NULL;
// 			if(isset($old_auth_data['this_logged_epoch_time'])) {
// 				$last_logged_epoch_time = $old_auth_data['this_logged_epoch_time'];  // last logged time is the this logged time of old data
// 			}
// 			$previous_refresh_token = NULL;
// 			if(isset($old_auth_data['current_refresh_token'])) {
// 				$previous_refresh_token = $old_auth_data['current_refresh_token'];  // previous refresh otken is the this current refresh token of old data
// 			}
// 			$user_type = $old_auth_data['user_type'];
			
// 			// Set the new data
// 			if($auths_model->replaceAuth($this->_user_id, $this->_area, $user_type, $auth_token, $previous_refresh_token, $current_refresh_token, $auth_token_expiry, $refresh_token_expiry, database::getDatetimeValue($last_logged_epoch_time))) {
// 				$this->_auth_data= array(
// 									'user_type'=>$user_type,
// 									'auth_token'=>$auth_token,
// 									'current_refresh_token'=>$current_refresh_token,
// 									'auth_token_expiry'=>$auth_token_expiry,
// 									'refresh_token_expiry'=>$refresh_token_expiry,
// 									'this_logged_epoch_time'=>time(),  // note: this isn't exactly the same db recorded. Maybe off for minimal time. Close enough to be acceptable.
// 									'last_logged_epoch_time'=>$last_logged_epoch_time);
// 				$this->_audit = self::$audit_authenticated;
// 				$auths_model->replaceAuthTransactionEnd();
// 				return $this->_auth_data;
				
// 			} else if($refresh_check == 'prev_expired') {
// 				$this->_audit = self::$audit_credential_expired;
// 				$this->_auth_data = NULL;
// 				$auths_model->replaceAuthTransactionEnd();
// 				return FALSE;
// 			} else if($refresh_check == 'curr_expired') {
// 				$this->_audit = self::$audit_credential_expired;
// 				$this->_auth_data = NULL;
// 				$auths_model->replaceAuthTransactionEnd();
// 				return FALSE;
// 			} else {
// 				$this->_audit = self::$audit_unknown;
// 				$this->_auth_data = NULL;
// 				$auths_model->replaceAuthTransactionEnd();
// 				return FALSE;
// 			}
// 		} else { // invalid refresh token
// 			$this->_audit = self::$audit_credential_mismatched;
// 			$this->_auth_data = NULL;
// 			$auths_model->replaceAuthTransactionEnd();
// 			return FALSE;
				
// 		}
// 		$auths_model->replaceAuthTransactionEnd(); // just in case things fall in here. Remove the databsae lock before leaving
// 	}

// non-locking version
	public function refresh($user_id, $area, $input_refresh_token) {
		global $logging;
		if(empty($user_id)) { // no user_id, no authentication
			$this->_audit = self::$audit_unknown;
			$this->_auth_data = NULL;
			return FALSE;
		}
		$this->_user_id = $user_id;
		$this->_area = $area;
		
		$auths_model = new authsModel();
		
		// Get the old data
		$old_auth_data =$auths_model->getAuth($this->_user_id, $this->_area);
		
		// Check valid refresh token
		$refresh_check = 'invalid';
		if($input_refresh_token == $old_auth_data['current_refresh_token']) {
			if(time() < strtotime($old_auth_data['refresh_token_expiry'])) {
				$refresh_check = 'new'; // a new refresh token is needed
			} else {
				$refresh_check = 'curr_expired'; // curr refresh token expired
			}
		} else if($input_refresh_token == $old_auth_data['previous_refresh_token']) { // using previous refresh token
			if(time() < $old_auth_data['this_logged_epoch_time'] + 300) { // we allow previous refresh token valid for 5 more minutes
				$refresh_check = 'resend'; // resend existing refresh token
			} else {
				$refresh_check = 'prev_expired'; // previous refresh token expired
			}
		}
		$logging->logMsg(logging::LOG_LEVEL_DEBUG, "Refresh check:$refresh_check/input_rt:$input_refresh_token/curr_rt:".$old_auth_data['current_refresh_token'].'/prev_rt:'.$old_auth_data['previous_refresh_token']);
		if($refresh_check == 'resend') {
			$this->_auth_data= $old_auth_data;
			$this->_audit = self::$audit_authenticated;
			return $this->_auth_data;
		} else if($refresh_check == 'new') {
			// generate new tokens
			$auth_token = $this->_secureUniqid(64);
			$auth_token_expiry = date("Y-m-d H:i:s", time() + (config::$session_time_valid));
			$current_refresh_token = $this->_secureUniqid(64);
			$refresh_token_expiry = date("Y-m-d H:i:s", time() + (config::$session_reauth_time_valid));
					
			$last_logged_epoch_time = NULL;
			if(isset($old_auth_data['this_logged_epoch_time'])) {
				$last_logged_epoch_time = $old_auth_data['this_logged_epoch_time'];  // last logged time is the this logged time of old data
			}
			$previous_refresh_token = NULL;
			if(isset($old_auth_data['current_refresh_token'])) {
				$previous_refresh_token = $old_auth_data['current_refresh_token'];  // previous refresh otken is the this current refresh token of old data
			}
			$user_type = $old_auth_data['user_type'];
			
			// Set the new data
			if($auths_model->replaceAuth($this->_user_id, $this->_area, $user_type, $auth_token, $previous_refresh_token, $current_refresh_token, $auth_token_expiry, $refresh_token_expiry, database::getDatetimeValue($last_logged_epoch_time))) {
				$this->_auth_data= array(
									'user_type'=>$user_type,
									'auth_token'=>$auth_token,
									'current_refresh_token'=>$current_refresh_token,
									'auth_token_expiry'=>$auth_token_expiry,
									'refresh_token_expiry'=>$refresh_token_expiry,
									'this_logged_epoch_time'=>time(),  // note: this isn't exactly the same db recorded. Maybe off for minimal time. Close enough to be acceptable.
									'last_logged_epoch_time'=>$last_logged_epoch_time);
				$this->_audit = self::$audit_authenticated;
				return $this->_auth_data;
			} else {
				$this->_audit = self::$audit_unknown;
				$this->_auth_data = NULL;
				return FALSE;

			}

		} else if($refresh_check == 'prev_expired') {
			$this->_audit = self::$audit_credential_expired;
			$this->_auth_data = NULL;
			return FALSE;

		} else if($refresh_check == 'curr_expired') {
			$this->_audit = self::$audit_credential_expired;
			$this->_auth_data = NULL;
			return FALSE;

		} else { // invalid refresh token
			$this->_audit = self::$audit_credential_mismatched;
			$this->_auth_data = NULL;
			return FALSE;
				
		}
	}
	
	/**
	 * 
	 * Return the authentication audit result
	 */
	public function getAudit() {
		return $this->_audit;
	}
	
	public function getUserId() {
		return $this->_user_id;
	}
	
	public function getUserType() {
		if(isset($this->_auth_data['user_type'])) {
			return $this->_auth_data['user_type'];
		} else {
			return NULL;
		}
	}

	public function getThisLoggedTime() {
		if(isset($this->_auth_data['this_logged_epoch_time'])) {
			return $this->_auth_data['this_logged_epoch_time'];
		} else {
			return NULL;
		}
	}
	
	public function getLastLoggedTime() {
		if(isset($this->_auth_data['last_logged_epoch_time'])) {
			return $this->_auth_data['last_logged_epoch_time'];
		} else {
			return NULL;
		}
		
	}
	
	
///////////////////// Private functions
/////////////////////

	private function _checkToken($input_auth_token) {
		$this->_fetchAuthData();
		
		if(!$this->_auth_data) { // No auth found in DB, always failed
			return FALSE;
		}

		// auth_token should not be null
		if($input_auth_token === $this->_auth_data['auth_token']) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	private function _checkExpiry() {
		$this->_fetchAuthData();
		
		if(!$this->_auth_data) {
			return FALSE;
		}
		
		$expiry_ts = strtotime($this->_auth_data['auth_token_expiry']);
		if($expiry_ts > time()) {
			return TRUE;
		} else {
			return FALSE;
		}
		
	}
	
	/**
	 * 
	 * Check access level
	 * @param int $resource_access_level -- resouce access level to check
	 * Note: access level is defined by the user type as defined in UserModel
	 * @see usersModel
	 * 
	 */
	private function _checkAccessLevel($resource_access_level) {
		$this->_fetchAuthData();
		
		if(!$this->_auth_data) {
			return FALSE;
		}
		
		// User type must be at least reach (smaller or equal) the resource access level
		if($this->_auth_data['user_type'] <= $resource_access_level) {
			return TRUE;
		} else {
			return FALSE;
		}
		
	}
	
	/**
	 * 
	 * Fetch authentication data from database. $this->_auth_data will be set.
	 */
	private function _fetchAuthData() {
		if(!$this->_auth_data) {
			$auths_model = new authsModel();
			$this->_auth_data = $auths_model->getAuth($this->_user_id, $this->_area);
			if(!$this->_auth_data['auth_token']) { // user has been logged out
				$this->_auth_data = NULL;
			}
		}
	}
	
	/**
	 * 
	 * Get a security sounds, unpredictable unique Id.
	 * This is a better, more secure version of uniqid() (when ssl enabled and use with ssl option)
	 * 
	 * Reference: http://www.php-security.org/2010/05/09/mops-submission-04-generating-unpredictable-session-ids-and-hashes/
	 * 
	 * @param int $maxLength - length of the unique Id return. Use NULL for default is 128 characters.
	 */
	static private function _secureUniqid($maxLength=null, $use_ssl=FALSE) {
		$entropy = '';

		// try ssl first
		if ($use_ssl && function_exists('openssl_random_pseudo_bytes')) {
			$entropy = openssl_random_pseudo_bytes(64, $strong);
			// skip ssl since it wasn't using the strong algo
			if($strong !== true) {
				$entropy = '';
			}
		}

		// add some basic mt_rand/uniqid combo
		$entropy .= uniqid(mt_rand(), true);

		/* We are not using Windows, don't worry about checking it
		// try to read from the windows RNG
		if (class_exists('COM')) {
			try {
				$com = new COM('CAPICOM.Utilities.1');
				$entropy .= base64_decode($com->GetRandom(64, 0));
			} catch (Exception $ex) {
			}
		}
		*/
		
		// try to read from the unix RNG
		if (is_readable('/dev/urandom')) {
			$h = fopen('/dev/urandom', 'rb');
			$entropy .= fread($h, 64);
			fclose($h);
		}

		$hash = hash('whirlpool', $entropy);
		if ($maxLength) {
			return substr($hash, 0, $maxLength);
		}
		return $hash;
	}


}