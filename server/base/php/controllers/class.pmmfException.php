<?php

/**
 * pmmf Framework Exception
 * @author peter
 *
 */
class pmmfException extends Exception {
	
	private $log_dataset = null; // A 3-element array of array($logLevel, $logMsg, $Location)
	
	// Redefine the exception so message isn't optional
	public function __construct($message, $http_code, $log_dataset, Exception $previous = null) {
		// some code
	
		// make sure everything is assigned properly
		parent::__construct($message, $http_code, $previous);
		
		$this->log_dataset = $log_dataset;
	}
	
	public function getLogDataset() {
		return $this->log_dataset;
	}
	
	
}

