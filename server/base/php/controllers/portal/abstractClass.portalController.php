<?php
require_once realpath(__DIR__) . '/../abstractClass.defaultController.php';

require_once realpath(__DIR__) . '/../../models/class.usersModel.php';

require_once realpath(__DIR__) . '/../../lib/class.accessControl.php';

abstract class portalController extends defaultController {
		
	function __construct() {
   		global $request, $logging;
   		   		
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
		$this->check_access_control ( $user_id, $resource_access_level, $ac_classname::$area_portal, $input_auth_token );
		
		//If you have an Login screen, you can catch the exception from access control and set view to the Login screen
		// try {
		// $this->check_access_control($user_id, $resource_access_level, $ac_classname::$area_portal, $input_auth_token);
		// } catch(pmmfException $je) {
		// // catch the authentication exception, set the view to login screen
		// // then re-throw the exception
		// $request->setView('portal/login');
		// throw $je;
		// }
		
		// Access control check successful (No Exception from check_access_control)
		// extend the auth expiry every time after a successful access
		$this->extend_expiry_access_control ( $user_id, $input_auth_token, $resource_access_level );

   		   		
	}

}
