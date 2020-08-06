<?php
require_once realpath(__DIR__) . '/../abstractClass.defaultController.php';

require_once realpath(__DIR__) . '/../../models/class.usersModel.php';

abstract class adminController extends defaultController {
	
	private $login_view = 'admin/default';
	private $default_view = 'admin/default';
	
	function __construct() {
		global $request, $logging;
		
		$this->exemptOperationFromAuthentication('panel', 'login');
		$this->exemptOperationFromAuthentication('panel', 'do_login');
		
		parent::__construct();
		
		// Set return format as HTML
		$request->setReturnFormat ( 'html' );
		
		
		$user_id = null;
		$input_auth_token = null;
		$resource_access_level = usersModel::USER_TYPE_ADMIN; // require ADMIN previlege
		
		$input_vars = $request->cookies;
		if (isset ( $input_vars ['user_id'] )) {
			$user_id = $input_vars ['user_id'];
		}
		if (isset ( $input_vars ['auth_token'] )) {
			$input_auth_token = $input_vars ['auth_token'];
		}
		
		// Check authentication
		$ac_classname = get_class ( $this->access_control );
		try {
			$this->check_access_control ( $user_id, $resource_access_level, $ac_classname::$area_admin, $input_auth_token );
		} catch ( pmmfException $je ) {
			// catch the authentication exception, set the view to login screen
			// then re-throw the exception
			$request->setView ( $this->login_view );
			
			throw $je;
		}
		
		// Access control check successful (Not Exception from check_access_control
		// extend the auth expiry every time after a successful access
		$this->extend_expiry_access_control ( $user_id, $input_auth_token, $resource_access_level );
		
	}
	
	function login() {
		global $request, $logging;
		
		if(!$request->getError()) {
			$request->setView($this->login_view);
		}
	}
	
	function do_login() {
		global $request, $logging;
		
		// login successful, set view to the default view
		$request->setView($this->default_view);
		
		if(!$request->getError()) {
			$input_vars = $request->variables;
			
			$handle ='';
			if(isset($input_vars['handle']) && !empty($input_vars['handle'])) {
				$handle = $input_vars['handle'];
			} else {
				$request->setError('Required fields missing');
				$request->setHTTPReturnCode(400);
				$logging->logMsg(2, 'Admin Panel: Required parameter missing or empty: handle');
				$request->setView($this->login_view);
			}
			
			$password = '';
			if(isset($input_vars['password']) && !empty($input_vars['password'])) {
				$password = $input_vars['password'];
			} else {
				$request->setError('Required fields missing');
				$request->setHTTPReturnCode(400);
				$logging->logMsg(2, 'Admin Panel: Required parameter missing or empty: password');
				$request->setView($this->login_view);
				
			}
			
			if(!$request->getError()) {
				$users_model = new usersModel();
				if($user_info = $users_model->checkPassword(NULL, NULL, $handle, $password)) {
					// check if user is an administrator
					if($user_info['type'] == usersModel::USER_TYPE_SYSADMIN || $user_info['type'] == usersModel::USER_TYPE_ADMIN) {
						// check for disabled user
						if($user_info['status'] == usersModel::USER_STATUS_ACTIVE) {
							$ac = $this->access_control;
							$ac_classname = get_class($ac);
							$access_info = $ac->set($user_info['id'], $user_info['type'], $ac_classname::$area_admin);
							// Use cookie authentication
							// cookie is valid for 30 days, but the actual session valid is defined by access control config
							$cookie_expire_time = time() + 2592000; //60 * 60 * 24 * 30  -- # of seconds of 30 days
							if(!setcookie('auth_token', $access_info['auth_token'], $cookie_expire_time, "/")) {
								$request->setError('Failed to setup authentication');
								$logging->logMsg(4, "Admin Panel: Failed to set authentication cookie [auth_token]");
								$request->setHTTPReturnCode(403);
								$request->setView($this->login_view);
							}
							if(!setcookie('user_id', $access_info['user_id'], $cookie_expire_time, "/")) {
								$request->setError('Failed to setup authentication');
								$logging->logMsg(4, "Admin Panel: Failed to set authentication cookie [user_id]");
								$request->setHTTPReturnCode(403);
								$request->setView($this->login_view);
							}
							$this->index(); // Go to Admin panel main page
							
						} else {
							$request->setError('Account disabled');
							$logging->logMsg(4, "Admin Panel: diabled user account login attempt ($handle/$user_info[id])");
							$request->setHTTPReturnCode(403);
							$request->setView($this->login_view);
						}
					} else {
						$request->setError('Permission Denied');
						$logging->logMsg(4, "Admin Panel: non-admin user login attempt ($handle/$user_info[id])");
						$request->setHTTPReturnCode(403);
						$request->setView($this->login_view);
						
					}
				} else {
					$request->setError('Invalid username and/or password');
					$logging->logMsg(3, "Admin Panel: Login Failed because invalid username/password ($handle)");
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
	
	protected function setLoginView($view) {
		$this->login_view = $view;
		
	}
	
	protected function setDefaultView($view) {
		$this->default_view = $view;
	}
	
}
