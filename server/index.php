<?php

$pmmf_base_location = 'base/php/';
$pmmf_application_location = 'application/php/';

require_once $pmmf_base_location.'lib/class.config.php';
require_once $pmmf_base_location.'lib/class.logging.php';
require_once $pmmf_base_location.'lib/class.request.php';
require_once $pmmf_base_location.'lib/phpCompatibleFunctions.php';

require_once $pmmf_base_location.'controllers/class.pmmfException.php';



/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// global variables /////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
$logging = new logging(config::$log_file, config::$path_base, config::$log_level, false, null, config::$log_show_trace);
$request = new request();


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// determine and call the controller  ///////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
try { // a big try to catch pmmfException
	
	// Sanity check: Area and Action must be specified
	if(empty($request->area) || empty($request->action)) {
		$request->setError('No area/action specified');
		$request->setHTTPReturnCode(400);
		$logging->logMsg(3, 'No area/action specified', __FILE__);
		throw new pmmfException('No area/action specified', 400,
				array(logging::LOG_LEVEL_FATAL, 'No area/action specified', __FILE__));
	}
	
	// Uncomment these codes to take server down from all actions, except admin access /////////////
	// TODO: modify this to be controlled by config parameter
	// 	if($area != 'admin') {  // allow admin panel access
	//     throw new pmmfException('Server is down for maintenance', 503,
	//         array(logging::LOG_LEVEL_ERROR,"Server was called when it is down for maintenance ($area/$action/$operation)", __FILE__));
	// 	}
	////////////////////////////////////////////////////////////////////////////////////////////////
	
	
	$area = $request->area;
	$action = $request->action;
	$operation = $request->operation;
	$parameter = $request->parameter;
	$controller_name = $action.'Controller';
	$controller_file = 'controllers/'.$area.'/class.' . $controller_name . '.php';
	
	$controller_path = $pmmf_application_location.$controller_file;
	// check if application controller file exists
	if(!file_exists($controller_path)) {
		// try again with the base controllers
		$controller_path = $pmmf_base_location.$controller_file;
		if(!file_exists($controller_path)) {
			// No Action Controller file
			throw new pmmfException("Area/action not existed: $area/$action", 501,
					array(logging::LOG_LEVEL_FATAL, 'Controller file not existed for area/action: '.$controller_path, __FILE__));
		}
	}
	
	include_once $controller_path;
	// check if controller class exists
	if (!class_exists($controller_name)) {
		// No Action Controller class
		throw new pmmfException("Action not existed: $action", 501,
				array(logging::LOG_LEVEL_FATAL, 'Controller class not defined or existed for action: '.$controller_name, __FILE__));
	}
	
	$controller = new $controller_name();
	// check if controller method exists
	if(!method_exists($controller, $operation)) {
		throw new pmmfException("Operation not existed: $operation", 501,
				array(logging::LOG_LEVEL_FATAL, 'Controller class method not existed: '.$controller_name.'::'.$operation, __FILE__));
	}
	
	// now call the controller handling method
	$controller->$operation($parameter);
	
} catch(pmmfException $je) {
	////////////////////////////////////////////////
	// Catch any error thrown by application, then set the error and http return code,  
	// and create the corresponding log message
	// The pmmfException would be handled here and would not be re-thrown
	
	$request->setError($je->getMessage());
	$request->setHTTPReturnCode($je->getCode());
	
	// insert back trace information to call parameters of logMsg()
	$call_func_params = $je->getLogDataset();
	if(!array_key_exists(2, $call_func_params)) {
		$call_func_params[2] = null;
	}
	$call_func_params[3] = $je->getTraceAsString();
	
	// call logging logMsg() to log a message
	call_user_func_array(array($logging, 'logMsg'),$call_func_params);
}



/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// route to view ////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// First check if re-direction has been set
if($request->getRedirect()) { // if redirect is set, do redirection (ignoring any view)
	include $pmmf_base_location.'views/defaultRedirectView.php';
	
	// Check if error, use error view
} else if($request->getError()) { // if error, find error view
	// Try to application 'operation' specific error view
	$error_view_file = $pmmf_application_location.'views/'.$request->getView().'ErrorView.'.$request->getReturnFormat().'.php';
	if(file_exists($error_view_file)) {
		include $error_view_file;
	} else {
		// else use application 'area' default error view
		$view_dir = dirname($request->getView());
		while($view_dir && $view_dir != '.') { // loop through all area/sub-areas (subdirectory in areas)
			$error_view_file = $pmmf_application_location.'views/'.$view_dir.'/defaultErrorView.'.$request->getReturnFormat().'.php';
			if(file_exists($error_view_file)) {
				include $error_view_file;
				break;
			} else {
				$view_dir = dirname($view_dir);
			}
		}
		
		if(!$view_dir || $view_dir == '.'){
			// Try the application default error view
			$error_view_file = $pmmf_application_location.'views/defaultErrorView.'.$request->getReturnFormat().'.php';
			if(file_exists($error_view_file)) {
				include $error_view_file;
			} else {
				// if still nothing found, use the base default error view
				$logging->logMsg(logging::LOG_LEVEL_DEBUG, 'No error view found for '.$request->getView().'('.$request->getReturnFormat().'). Using base default error view. Recommended to set up an application default error view.');
				include $pmmf_base_location.'views/defaultErrorView.'.$request->getReturnFormat().'.php. Recommend to create you own error view.';
			}
		}
	}
	
} else { // route to application specific success view
	$success_view_file = $pmmf_application_location.'views/'.$request->getView().'SuccessView.'.$request->getReturnFormat().'.php';
	if(file_exists($success_view_file)) {
		include $success_view_file;
	} else {
		// else use application 'area' default success view
		$view_dir = dirname($request->getView());
		while($view_dir && $view_dir != '.') { // loop through all area/sub-areas (subdirectory in areas)
			$success_view_file = $pmmf_application_location.'views/'.$view_dir.'/defaultSuccessView.'.$request->getReturnFormat().'.php';
			if(file_exists($success_view_file)) {
				include $success_view_file;
				break;
			} else {
				$view_dir = dirname($view_dir);
			}
		}
		
		if(!$view_dir || $view_dir == '.'){
			// Try the application default success view
			$success_view_file = $pmmf_application_location.'views/defaultSuccessView.'.$request->getReturnFormat().'.php';
			if(file_exists($success_view_file)) {
				include $success_view_file;
			} else {
				// if still nothing found, use base default success view
				$logging->logMsg(logging::LOG_LEVEL_INFO, 'No success view found for '.$request->getView().'('.$request->getReturnFormat().'). Using base default success view. Recommended to set up an application default success view.');
				include $pmmf_base_location.'views/defaultSuccessView.'.$request->getReturnFormat().'.php. Recommend to create you own success view.';
			}
		}
	}
}



?>