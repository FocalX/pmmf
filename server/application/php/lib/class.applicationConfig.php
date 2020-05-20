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
	static public $server_name = 'localhost'; // server name used to generate various URLs
    
	// Paths //////////////////////////////////////////////////////
	static public $path_base = "/local";  // base path to the server index.php
	
	// Short URL ////////////////////////////////////////////
	static public $short_url_use = FALSE;  // whether using short URL
	static public $short_url_domain = ''; // domain name of short url

	// DB configuration ///////////////////////////////////////////
	static public $db_host = 'localhost';
	static public $db_user = '';
	static public $db_password = '';
	static public $db_name_main = 'pmmf_local'; // main database name
	static public $db_name_ancillary = '';   // an optional 2nd ancillary database can be set up
	static public $db_api = 2; // 1:mysql / 2:mysqli
	static public $db_escape = 0; // 1:using / 0:not using real_escape_string function

	// Error logging ////////////////////////////////////////////////
	static public $log_file = '';   // log filename to output. Empty will use default logging (web server log)
	static public $log_level = 0;   // minimum log message type. Values are defined in class logging
	
	// User session /////////////////////////////////////////////////
	static public $session_time_valid = 1800; // session time valid in seconds (i.e auth_token valid time) 30 mins = 60*30 = 1800 seconds
	static public $session_reauth_time_valid = 1209600; // how long user need to re-authenticate (i.e. refresh_token valid time) 14 days = 60*60*24*14 seconds
	
	// Security
	static public $security_whitelisted_ips = array(/*'192.168.1.1', '182.168.2.2' */); // IP address allowed. This is checked when $request->isClientIPAllowed() is called
	
	/*** End of base config variables ***************************************************************************************/
	
	/** Add your own configuration below here **/
	
	
	
	
	/* Override settings by individual environments *************************************************************/
	protected static function defineProd() {
		// Example to override base configuration
		//
		// self::$db_host = 'production_db.com';
		// self::$db_user = 'prod_db_user';
		// self::$db_password = '12345';
		// self::$db_name_main = 'my_db';
	}
	
	protected static function defineStaging() {
	
	}
	
	protected static function defineQA() {
	
	}
	
	protected static function defineDev() {
	
	}
	
	protected static function defineLocal() {
		 
	    // Local environment has the privilege to load configuration from a separated file 'class.myConfig.php'
	    // This 'class.myConfig.php' file can be a respository ignored file without being checked in
	    if(file_exists(realpath(dirname(__FILE__)) . '/class.myConfig.php')) include 'class.myConfig.php';
	}
		
}	
	