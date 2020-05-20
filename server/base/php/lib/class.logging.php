<?php


class logging {

	private $_LOGFILE; // log file handle

	/* log level:
	 0-debug
	 1-info
	 2-warning
	 3-error
	 4-fatal
	 */
	const LOG_LEVEL_DEBUG = 0;
	const LOG_LEVEL_INFO = 1; 
	const LOG_LEVEL_WARN = 2;
	const LOG_LEVEL_ERROR = 3;
	const LOG_LEVEL_FATAL = 4;
	
	private $_log_level; // minimum log level to print
	private $_log_label; // label used in the log
	private $_log_level_string = array('DEBUG','INFO','WARN','ERROR','FATAL');
	private $_use_sys_log = false;

	function __construct($logfile, $label, $log_level=2) {
		date_default_timezone_set('UTC');
		$this->_log_level = $log_level;
		$this->_log_label = $label;
		$real_logfile = '';
		if(isset($logfile) && strlen($logfile) > 0) {
			$real_logfile = $logfile;
			$this->_LOGFILE = fopen($real_logfile, 'a');

			if(!$this->_LOGFILE) {
				echo "error opening log file: $real_logfile";
			}

		} else {
			// use php system log mechanism
			$this->_use_sys_log = true;
		}
	}

	/* log error message
	 * $level -- error level of message (See above)
	 * $msg -- the error message to log
	 * $location -- the (file) location where the error happens
	 */
	function logMsg($level, $msg, $location=NULL) {
		if($level >= $this->_log_level) {
			if($location) {
				$msg .= " at ".$location;
			}
			if($this->_use_sys_log) {
				error_log('['.$this->_log_label.' '.$this->_log_level_string[$level].'] '.$msg, 0);
			} else {
				date_default_timezone_set('UTC');
				$msg = date("Y/m/d-H:i UTC").' ['.$this->_log_label.' '.$this->_log_level_string[$level].'] '.$msg."\n";
				fwrite($this->_LOGFILE, $msg);
			}
		}
	}

	function getLatestMsgs($type, $how_many_lines=20, $unfiltered=FALSE) {
		if($this->_use_sys_log) {
			// assume using Apache log. (It may not, but assuming for now)
			$webserver_log_file = '';
			if($type == 1) {
				$filter = $this->_log_label;
				$webserver_log_file = '/var/log/httpd/error_log';
			} else {
				$filter = config::$path_base;
				$webserver_log_file = '/var/log/httpd/access_log';
			}
				$grep_filter = '';
			if(!$unfiltered) {  // take away the grep filter
				$grep_filter = '| grep \''.$filter.'\'';
			} 
				return `tail -$how_many_lines $webserver_log_file $grep_filter`;
			
		}
	}
}