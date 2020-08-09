<?php
require_once realpath(__DIR__) . '/../abstractClass.defaultController.php';

require_once realpath(__DIR__) . '/../../models/class.usersModel.php';

require_once realpath(__DIR__) . '/../../lib/class.accessControl.php';

abstract class portalController extends defaultController {
		
	private $login_view = '';
	private $login_success_view = '';
	private $login_success_redirect_location = '';
	
	function __construct() {
   		global $request, $logging;
   		
   		// exempt these login operations from authentication
   		$this->exemptOperationFromAuthentication('*', 'login');
   		$this->exemptOperationFromAuthentication('*', 'do_login');
   		
   		parent::__construct();
   		
   		// Set default return format as HTML
   		$request->setReturnFormat('html');
   		
   		
		$user_id = null;
		$input_auth_token = null;
		$resource_access_level = usersModel::USER_TYPE_REGULAR;
		
		$input_vars = $request->cookies;
		if (isset ( $input_vars ['user_id'] )) {
			$user_id = $input_vars ['user_id'];
		}
		if (isset ( $input_vars ['auth_token'] )) {
			$input_auth_token = $input_vars ['auth_token'];
		}
		
		// Check authentication
		$ac_classname = get_class ( $this->access_control );
		
		//If you have an Login screen, you can catch the exception from access control and set view to the Login screen
		try {
			$this->check_access_control($user_id, $resource_access_level, $ac_classname::$area_portal, $input_auth_token);
		} catch(pmmfException $je) {
			// catch the authentication exception, set the view to login screen
			// then re-throw the exception
			$request->setView( $this->login_view );

			throw $je;
		}
		
		// Access control check successful (No Exception from check_access_control)
		// extend the auth expiry every time after a successful access
		$this->extend_expiry_access_control ( $user_id, $input_auth_token, $resource_access_level );

   		   		
	}
	
	function login() {
		global $request, $logging;
		
		if($this->login_view) {
			$request->setView($this->login_view);
		} else {
			throw new pmmfException('Not Allowed', 403,
					array($logging::LOG_LEVEL_DEBUG, 'Disabled login operation called from '.get_called_class(), __FILE__));
		}
	}
	
	function do_login() {
		global $request, $logging;
		
		
		if(!$request->getError()) {
			$input_vars = $request->variables;
			
			$handle ='';
			if(isset($input_vars['handle']) && !empty($input_vars['handle'])) {
				$handle = $input_vars['handle'];
			} else {
				$request->setError('Required fields missing');
				$request->setHTTPReturnCode(400);
				$logging->logMsg(2, 'do_login: Required parameter missing or empty: handle');
				$request->setView($this->login_view);
			}
			
			$password = '';
			if(isset($input_vars['password']) && !empty($input_vars['password'])) {
				$password = $input_vars['password'];
			} else {
				$request->setError('Required fields missing');
				$request->setHTTPReturnCode(400);
				$logging->logMsg(2, 'do_login: Required parameter missing or empty: password');
				$request->setView($this->login_view);
				
			}
			
			if(!$request->getError()) {
				$users_model = new usersModel();
				if($user_info = $users_model->checkPassword(NULL, NULL, $handle, $password)) {
					// check for disabled user
					if($user_info['status'] == usersModel::USER_STATUS_ACTIVE) {
						$ac = $this->access_control;
						$ac_classname = get_class($ac);
						$access_info = $ac->set($user_info['id'], $user_info['type'], $ac_classname::$area_portal);
						// Use cookie authentication
						// cookie is valid for 30 days, but the actual session valid is defined by access control config
						$cookie_expire_time = time() + 2592000; //60 * 60 * 24 * 30  -- # of seconds of 30 days
						if(!setcookie('auth_token', $access_info['auth_token'], $cookie_expire_time, "/")) {
							$request->setError('Failed to setup authentication');
							$logging->logMsg(4, "do_login: Failed to set authentication cookie [auth_token]");
							$request->setHTTPReturnCode(403);
							$request->setView($this->login_view);
						}
						if(!setcookie('user_id', $access_info['user_id'], $cookie_expire_time, "/")) {
							$request->setError('Failed to setup authentication');
							$logging->logMsg(4, "do_login: Failed to set authentication cookie [user_id]");
							$request->setHTTPReturnCode(403);
							$request->setView($this->login_view);
						}
						// login successful, set view to the default view
						$request->setView($this->default_view);
						// if login_success_redirect_location is set, do re-direct instead
						// (redirection has precedence)
						if($this->login_success_redirect_location) {
							$request->setRedirect($this->login_success_redirect_location);
						}
						//$this->index(); // Successful login. Go to default main page
						
					} else {
						$request->setError('Account disabled');
						$logging->logMsg(4, "do_login: diabled user account login attempt ($handle/$user_info[id])");
						$request->setHTTPReturnCode(403);
						$request->setView($this->login_view);
					}
				} else {
					$request->setError('Invalid username and/or password');
					$logging->logMsg(3, "do_login: Login Failed because invalid username/password ($handle)");
					$request->setHTTPReturnCode(401);
					$request->setView($this->login_view);
					
				}
				
			}
			
		}
	}
	
	function do_logout() {
		global $request, $logging;
		
		if(!$request->getError()) {
			$this->access_control->clear();  // clear auth
			
			$request->setError('Logout successful');
			$logging->logMsg(1, '(admin panel) Logout successful');
			$request->setView($this->login_view);
		}
	}
	
	
	function restrictedOperationCheck($func_name, $min_user_type) {
		// check user type of current user if it has permission
		$current_user_type = $this->access_control->getUserType();
		if($current_user_type <= $min_user_type) {
			return TRUE;
		} else {
			throw new pmmfException('Insufficient privileges', 401,
					array(logging::LOG_LEVEL_FATAL, "Non-privilleged user tried to execute an restricted operation: $func_name"));
		}
	}
	
	/**
	 * The view for login page
	 * @param string $view e.g. "protal/login"
	 */
	protected function setLoginView($view) {
		$this->login_view = $view;
		
	}
	
	/**
	 * The default view after successful login
	 * @param string $view -- view string e.g. "protal/panel"
	 */
	protected function setLoginSuccessView($view) {
		$this->$login_success_view = $view;
	}
	
	/**
	 * Set the redirect location after a successful login.
	 * If this is set, LoginSuccessView will be ignored
	 * @param string $location -- URL to re-direct e.g. "/portal/panel/dashboard'
	 */
	protected function setLoginSuccessRedirectLocation($location) {
		$this->login_success_redirect_location = $location;
	}
}
