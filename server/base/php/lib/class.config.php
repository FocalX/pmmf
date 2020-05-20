<?php
require_once $pmmf_application_location . 'lib/class.applicationConfig.php';
class config extends applicationConfig {
	
	protected static function defineProd() {
		config::$build_env = 'prod';
		parent::defineProd ();
	}
	
	protected static function defineStaging() {
		config::$build_env = 'staging';
		parent::defineStaging ();
	}
	
	protected static function defineQA() {
		config::$build_env = 'qa';
		parent::defineQA ();
	}
	
	protected static function defineDev() {
		config::$build_env = 'dev';
		parent::defineDev ();
	}
	
	protected static function defineLocal() {
		parent::defineLocal ();
	}
	
	/**
	 * ***********************************************************************************************************
	 */
	
	/**
	 * Configuration and Environment initialization
	 * system environment 'PMMF_ENV' should be defined as:
	 * prod, qa, staging, dev, or local.
	 */
	static function init() {
		
		// Here goes configuration set at RUNTIME ///////////////////////////////////////////////////////
		
		// $server_anme:
		// either set it automatically here, if available
		// or set it manually for each environment
		if (isset ( $_SERVER ['SERVER_NAME'] )) {
			config::$server_name = $_SERVER ['SERVER_NAME'];
		}
		
		// Layer IDs from their uuid
		
		// if there defined an system environment 'PMMF_ENV' (should be done by apache mod_env) ////////
		if (isset ( $_SERVER ['PMMF_ENV'] )) {
			switch ($_SERVER ['PMMF_ENV']) {
				case 'prod' :
					self::defineProd ();
					break;
				case 'qa' :
					self::defineQA ();
					break;
				case 'staging' :
					self::defineStaging ();
					break;
				case 'dev' :
					self::defineDev ();
					break;
				case 'local' :
					self::defineLocal ();
					break;
				default :
					print 'Unknown Environment:' . $_SERVER ['PMMF_ENV'];
					exit ( 0 );
			}
		} else {
			print 'No environment defined!';
			exit ( 0 );
		}
	}
}

/* Run config init ************************************************************************************* */
config::init();
	
	