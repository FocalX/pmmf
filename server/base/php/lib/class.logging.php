<?php

/**
 * log messages can be logged to the file, printed to stdout, and/or saved to/retrived from memory.
 * @author peter
 *
 */
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
	private $_save_level; // minimum level to saved; if null, no messages will be saved
	private $_log_label; // label used in the log
	private $_log_level_string = array('DEBUG','INFO','WARN','ERROR','FATAL');
	private $_use_sys_log = false;
	private $_verbal = false;
	private $_show_trace = false;
	private $_saved_msgs = '';
	
	/**
	 * Constructor to create logging service.
	 * @param string $logfile - log file to save to. NULL to save to syslog
	 * @param string $label - a text string added to the front of all messages
	 * @param number $log_level - minimum log level of message to log. Default LOG_LEVEL_WARN(2)
	 * @param boolean $verbal - should message be printed to stdout. Default false.
	 * @param number $save_level - minumum log level of message to save to memory. NULL to not save to memory. Default NULL.
	 * @param boolean show_trace - show function back trace (if any)
	 */
	function __construct($logfile, $label, $log_level=2, $verbal=false, $save_level=null, $show_trace=false) {
		date_default_timezone_set('UTC');
		$this->_log_level = $log_level;
		$this->_save_level = $save_level;
		$this->_log_label = $label;
		$this->_verbal = $verbal;
		$this->_show_trace=$show_trace;
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
	 * int $level -- error level of this error message (See above)
	 * string $msg -- the error message to log
	 * string $location -- the (file) location where the error happens. NULL for no location. Default is NULL.
	 * string $tace -- the function back trace. NULL for no trace. Default is NULL.
	 */
	function logMsg($level, $msg, $location=NULL, $trace=NULL) {
		date_default_timezone_set('UTC');
		
		// log and print message
		if(!is_null($this->_log_level) && $level >= $this->_log_level) {
			if($location) {
				$msg .= " at ".$location;
			}
			if($this->_show_trace && $trace) {
				$msg .= " | Back trace: ".$trace;
			}
			$msg = '['.$this->_log_label.' '.$this->_log_level_string[$level].'] '.$msg; // add label
			$dated_msg = date("Y-m-d H:i e")." $msg\n"; // add date
			if($this->_use_sys_log) {
				error_log($msg, 0);
			} else {
				fwrite($this->_LOGFILE, $dated_msg);
				
			}
			if($this->_verbal) { // print message to output
				print $dated_msg;
			}
		}
		// save message
		if(!is_null($this->_save_level) && $level >= $this->_save_level) {
			$this->_saved_msgs .= $dated_msg;
		}
	}
	
	function getSavedMsgs() {
		return $this->_saved_msgs;
	}
	
	
}