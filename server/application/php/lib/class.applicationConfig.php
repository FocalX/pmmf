<?php
/***
 * 
 * Application Configurations
 *
 */

class applicationConfig {
	/* Default environemnt settings (These settings will be used if not being overriden) ********************************/
	
    /********************************************************************************************************************
     PMMF Base Config variables
     Do not removed any of these variables.
     Must be defined and customized for the base system to work.
     
     Can be overrided by individual environments using define<env>() functions below
    *********************************************************************************************************************/
    // Build Environment //////////////////////////////////////////
    // Pre-defined environments: 'local', 'dev', 'qa', 'staging', 'prod'
    static public $build_env = 'local';
    
	// Server names, paths, etc //////////////////////////////////////////////////////
    static public $server_name = 'localhost'; // server name. Mainly used to generate various URLs
    static public $path_base = "/local";  // base path to the server index.php. E.g. for https://yoursite.com/dir1/index.php, /dir1 is the path base.
    									  // If index.php sits at the base on the site, leave this empty.
	
	// DB configuration ///////////////////////////////////////////
	static public $db_info = array(
			'_main' => array( // main db info. Key name should always be '_main'
					'host' => 'localhost',
					'user' => '',
					'password' => '',
					'name' => 'pmmf_local'  // schema name
			),
			// additional databases can be defined here
// 			'db1' => array( // additioanl db info key name can be any string.
// 					'host' => 'localhost',
// 					'user' => '',
// 					'password' => '',
// 					'name' => 'pmmf_local_ex'
// 			),
	);
	static public $db_api = 2; // 1:mysql / 2:mysqli
	static public $db_escape = 0; // 1:using / 0:not using real_escape_string function

	// Error logging ////////////////////////////////////////////////
	static public $log_file = '';   // log filename to output. Empty will use default logging (web server log)
	static public $log_level = 0;   // minimum log message type. Values are defined in class logging
	static public $log_show_trace = true;   // show call trace in log message
	
	
	// User session /////////////////////////////////////////////////
	static public $session_time_valid = 1800; // session time valid in seconds (i.e auth_token valid time) 30 mins = 60*30 = 1800 seconds
	static public $session_reauth_time_valid = 1209600; // how long user need to re-authenticate (i.e. refresh_token valid time) 14 days = 60*60*24*14 seconds
	
	// Security
	static public $security_whitelisted_ips = array(/*'192.168.1.1', '182.168.2.2' */); // IP address allowed. This is checked when $request->isClientIPAllowed() is called
	
	/*** End of base config variables ***************************************************************************************/
	
	/** Add your own configuration below here **/
	
	
	
	
	/* Override settings by individual environments *************************************************************/
	/* Environment should be defined by a Server configuration defined environment variable called PMMF_ENV
	   See .htaccess.example
	 */
	
	protected static function defineProd() {
		self::$build_env = 'prod';
		
		// Example to override base configuration
		//
		// self::$server_name = 'prod.yoursite.com';
		// self::$path_base = "";
		// self::$db_info = array(
		// 				'_main' => array( // main db info. Key name should always be '_main'
		// 						'host' => 'prod.yoursite.com',
		// 						'user' => 'dbuser',
		// 						'password' => '12345',
		// 						'name' => 'pmmf_prod'
		//        );
       
	}
	
	protected static function defineStaging() {
		self::$build_env = 'staging';
		
	}
	
	protected static function defineQA() {
		self::$build_env = 'qa';
		
	}
	
	protected static function defineDev() {
		self::$build_env = 'dev';
		
	}
	
	protected static function defineLocal() {
		self::$build_env = 'local';
		
	    // Local environment has the privilege to load configuration from a separated file 'class.myConfig.php'
	    // This 'class.myConfig.php' file can be a respository ignored file without being checked in
	    if(file_exists(realpath(dirname(__FILE__)) . '/class.myConfig.php')) include 'class.myConfig.php';
	}
		
}	
	