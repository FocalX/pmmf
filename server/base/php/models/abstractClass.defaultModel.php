<?php
require_once realpath(dirname(__FILE__)).'/../lib/class.database.php';

abstract class defaultModel {

	protected $_db = NULL;
	protected $_db_ancillary = NULL;
	
	function __construct() {
		global $logging, $request;
		
		// setup database connection
		$this->_db = new Database();
		if(!$this->_db->connect()) {
			throw new pmmfException('Database Error: failed connecting to database', 500,
							array(logging::LOG_LEVEL_ERROR, 'Failed connecting to main database: '.$this->_db->get_error(), __FILE__));
		}
	}

	function __destruct() {

	}

	public function getDbConnection() {
		return $this->_db;
	}
	
	public function getAncillaryDbConnection() {
		return $this->_db_ancillary;
	}
	
	/**
	 * add deleting image into images_deleted table 
	 * @param unknown $resource_url -- resource URL of the deleting image
	 * @param unknown $deleted_datetime -- time deleted
	 */
	protected function register_deleted_image($deleted_resource_url) {
		
		$escaped_deleted_resource_url = $this->_db->escapeUserInput($deleted_resource_url);
		
		$querty_addDeletedImage = "INSERT INTO images_deleted (resource_url, deleted_datetime)
		VALUES
		('$escaped_deleted_resource_url', now())";
		if($this->_db->query($querty_addDeletedImage)) {
			return TRUE;
		} else {
			// not throw exception. Let the caller to decide what to do
			$logging->logMsg(logging::LOG_LEVEL_ERROR, 'insert deleted images query failed: '.$this->_db->get_error(), __FILE__);
			return FALSE;
		
		}
		
	}
	
	// set ancillary db connection
	protected function db_ancillary_init() {
		global $logging, $request;
		
		if(is_null($this->_db_ancillary)) {
			// setup database connection
			$this->_db_ancillary = new Database(config::$db_name_ancillary);
			if(!$this->_db_ancillary->connect(TRUE)) {
				throw new pmmfException('Database Error: failed to connect to database', 500,
								array(logging::LOG_LEVEL_ERROR, 'Failed to connect to ancillary database: '.$this->_db->get_error(), __FILE__));
			}
		}
		
	}
	
}

?>