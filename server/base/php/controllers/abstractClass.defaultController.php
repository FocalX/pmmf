<?php
// Choose between 2 access control models
//require_once realpath(__DIR__) . '/../lib/class.accessControl.php';
require_once realpath(__DIR__) . '/../lib/class.auth.php';

abstract class defaultController {
	
	protected $access_control = NULL;
	protected $access_control_classname = NULL;
	
	// list of actions and operations which are exempted from authentication
	// an array of action-operation tuple
	// e.g. (('action1','operation1'), 'action1','operation2'), ('action2', 'operation3'))
	private $authentication_exempted_list = array();
	
	// list of actions and operations which are required secured (SSL/TLS) connections
	// an array of action-operation tuple
	// e.g. (('action1','operation1'), 'action1','operation2'), ('action2', 'operation3'))
	private $secured_connection_required = array();
	
	private $authentication_exempted = FALSE; // indicating if the current operation has been exempted from authentication
	
	
	function __construct() {
		// Choose between 2 access control models
		//$this->access_control = new accessControl();
		$this->access_control = new auth();
				
	}
	
	/**
	 * 
	 * Check access control
	 * @param int $user_id - Leave this empty if using class accessControl
	 * @param int $resource_access_level - resource minimum access level
	 * @param int $area - Leave this empty if using class accessControl
	 * @param string $input_auth_token - Leave this empty if using class accessControl
	 */
	protected function check_access_control($user_id, $resource_access_level, $area, $input_auth_token='') {
		global $request, $logging;
		
   		//$logging->logMsg(logging::LOG_LEVEL_DEBUG, 'Current user id:'.$user_id.' / Incoming parameters:'.print_r($request->variables, TRUE));
   		//$logging->logMsg(logging::LOG_LEVEL_DEBUG, 'Incoming HTTP headers:'.print_r($request->headers, TRUE));
   		
   		// Check authentication exempt list
   		$this->authentication_exempted = FALSE;
   		foreach($this->authentication_exempted_list as $exempted_action_operation) {
   			if(($exempted_action_operation[0] == '*' && !$exempted_action_operation[1]) || // matching all actions
   					($exempted_action_operation[0] == '*' && $exempted_action_operation[1] == $request->operation) || // matchinv any actions with a specific operation 
   					($exempted_action_operation[0] == $request->action  && $exempted_action_operation[1] == '*') || // matching specific action with all operations
   					($exempted_action_operation[0] == $request->action &&  $exempted_action_operation[1] == $request->operation)) { // matching particular action and operation
   						$this->authentication_exempted = TRUE;
   						$logging->logMsg(logging::LOG_LEVEL_DEBUG, 'Authentication exempted operation ('.$request->action.'->'.$request->operation.')');
   			}
   		}
   		
   		
   		if(!$this->authentication_exempted &&
   				!$this->access_control->check($user_id, $resource_access_level, $area, $input_auth_token)) { // invalid session
			$ac_classname = get_class($this->access_control);
			$ac_audit = $this->access_control->getAudit();
			if($ac_audit == $ac_classname::$audit_credential_mismatched) { // credential not matched with what we know
				throw new pmmfException('Not authenticated', 401, array(logging::LOG_LEVEL_WARN, "(check_access_control) Credentials not matching with what the server knows."));
			} else if($ac_audit == $ac_classname::$audit_credential_expired) { // session expired
				throw new pmmfException('Session time out', 401, array(logging::LOG_LEVEL_INFO, '(check_access_control) Authentication session expired'));
			} else if($ac_audit == $ac_classname::$audit_access_level_failed) { // access level not sufficient
				throw new pmmfException('Permission denied', 401, array(logging::LOG_LEVEL_FATAL, '(check_access_control) Insufficient authentication previleges.'));
			} else {
				throw new pmmfException('Authentication error', 401, array(logging::LOG_LEVEL_FATAL, '(check_access_control) Unknown authentication error:'.$ac_audit));
			}

		}

		// check for those action/operation needed to be secured connection only
		$secured_connection_required = FALSE;
		foreach ( $this->secured_connection_required as $secured_action => $secured_operations ) {
			if (($request->action == $secured_action && $request->operation == $secured_operations)) {
				$secured_connection_required = TRUE;
				break;
			}
		}
		if ($secured_connection_required && ! $request->isSecureConnection ()) {
			
			throw new pmmfException('Secure connection required', 403,
					array(logging::LOG_LEVEL_FATAL, 'Secure connection required (action=' . $request->action . ' / operation=' . $request->operation . ')'));
		}
		
		return TRUE;

	}
	
	/**
	 * Exempt an request operation from authentication requirement 
	 * @param unknown $action
	 * @param unknown $operation
	 */
	protected function exemptOperationFromAuthentication($action, $operation) {
		$this->authentication_exempted_list[] = array($action, $operation); 
	}
	
	
	/**
	 * 
	 * Extend the authentication expiry according to current time
	 */
	protected function extend_expiry_access_control($user_id) {
		global $request, $logging;
		
		if($this->authentication_exempted) {  // if the current operation is authentication excempted
			return TRUE;
		}
		
		// make sure user is authenticated before updating the time
		$ac_classname = get_class($this->access_control);
		if($this->access_control->getAudit() == $ac_classname::$audit_authenticated) {
			return $this->access_control->extendTime();
		} else {
			$request->setError('Authentication error');
			$logging->logMsg(logging::LOG_LEVEL_FATAL, '(extend_expiry_access_control) Try to extend authentication expiry on unauthenticated user.');
			return false;
		}
	}
	
	/**
	 * Check if at least one of the keys exists in changesArray.
	 * changeArrays is an array contains parameters that should be changed.
	 * @param unknown $changesArray
	 * @param unknown $keysArray
	 * @deprecated Deprecated and unsafe. Replaced by checkChangesArrayKeys()
	 */
	protected function checkChangesArrayKeysExist($changesArray, $keysArray) {
		$result = FALSE;
	
		foreach($keysArray as $key) {
			if(array_key_exists($key, $changesArray)) {
				return TRUE;
				break;
			}
		}
	}
	
	/**
	 * Sanitized the changesArray to only containing keys (and its value) as specified in keysArray.
	 * This makes sure the result changesArray does not contain information that model should not handle.
	 * E.g. input changesArray contain "status" change which controller update function should not handle. But
	 * it could slip into the model and make the change without controller proper control.
	 * 
	 * @param unknown $changesArray - an associated array contains parameters that should be changed
	 * @param unknown $keysArray - an indexed array which specified which keys should be handled
	 * 
	 * @return the sanitized version of changesArray.
	 *         Empty array means the changesArray does not contain any parameters that should be handled 
	 */
	protected function checkChangesArrayKeys($changesArray, $keysArray) {
		$sanitizedChangedArray = array();
		foreach($changesArray as $changesKey=>$chagnesValue) {
			if(in_array($changesKey, $keysArray)) {
				$sanitizedChangedArray[$changesKey] = $chagnesValue;
			}
		}
		
		return $sanitizedChangedArray;
		
	}
}
