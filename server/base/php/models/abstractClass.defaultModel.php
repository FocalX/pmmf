<?php
require_once realpath(dirname(__FILE__)).'/../lib/class.database.php';

abstract class defaultModel {

	protected $_db = NULL;
	protected $_db_ancillary = NULL;
	
	function __construct($db_info_key=NULL, $db_name=NULL) {
		global $logging, $request;
		
		// setup database connection
		$this->_db = new Database($db_info_key);
		if(!$this->_db->connect($db_name)) {
			$db_info_key = is_null($db_info_key)?'main':$db_info_key;
			throw new pmmfException('Database Error: failed connecting to database', 500,
							array(logging::LOG_LEVEL_ERROR, "Failed connecting to $db_info_key database: ".$this->_db->get_error(), __FILE__));
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
		
}

?>