<?php

class database {
	
	// Which PHP MySQL API to use
	// 1 for mysql extension
	// 2 for mysqli
	// 3 for PDO_MySQL (not implemented)
	private $api = 1;
	
	// global config to determine if using real_escape_string function
	// if 1, escaping will be done according to the database connection character encoding.
	// Otherwise, escaping will be done assuming ISO-8859-1
	private $use_db_escape_string;
	
	// mysql db configuration
	private $dbhost;
	private $dbuser;
	private $dbpass;
	private $dbname;
	
	private $conn = NULL;
	private $in_transaction = 0;
	private $err_msg = "";
	private $errno = NULL;
	
	static public $MYSQL_ERRNO_DUPLICATE_ENTRY = 1062;
	
	// $dbname -- name of database; default using the main database name
	function __construct($db_info_key=NULL) {
		if(is_null($db_info_key)) {
			$this->dbhost = config::$db_info['_main']['host'];
			$this->dbuser = config::$db_info['_main']['user'];
			$this->dbpass = config::$db_info['_main']['password'];
			$this->dbname = config::$db_info['_main']['name'];
		}
		
		// 		$this->dbhost = config::$db_host;
		// 		$this->dbuser = config::$db_user;
		// 		$this->dbpass = config::$db_password;
		// 		if(is_null($dbname)) {
		// 			$this->dbname = config::$db_name_main;
		// 		} else {
		// 			$this->dbname = $dbname;
		// 		}
			$this->api = config::$db_api;
			$this->use_db_escape_string = config::$db_escape;
	}
	
	
	// open a mysql db connection
	// $new_link -- mysql ext only. Create a new database connect instead of re-using existing one
	function connect($new_link=FALSE) {
		if($this->api == 1) { // mysql ext
			$this->conn = mysql_connect($this->dbhost, $this->dbuser, $this->dbpass, $new_link);
			//mysql_set_charset('utf8');
		} else { // mysqli
			$this->conn = mysqli_connect($this->dbhost, $this->dbuser, $this->dbpass, $this->dbname);
			//mysqli_set_charset($this->conn, "utf8");
		}
		
		if(!$this->conn) {
			if($this->api == 1) { // mysql ext
				$this->err_msg = mysql_error();
				$this->errno = mysql_errno();
			} else { // mysqli
				$this->err_msg = 'Error creating mysqli connection';
				$this->errno = NULL;
			}
			return false;
		} else {
			if($this->api == 1 && !mysql_select_db($this->dbname)) { // mysql ext
				$this->err_msg = mysql_error();
				$this->errno = mysql_errno();
				return false;
			}
			return true;
		}
	}
	
	function get_connection() {
		if(isset($this->conn)) {
			return $this->conn;
		} else {
			return NULL;
		}
	}
	
	function query($query, $charset='utf8') {
		// Need php >=5.2.3
		//mysql_set_charset($charset);
		//$statement = 'SET NAMES utf8;'.$statement;
		
		$result = NULL;
		if($this->api == 1) { // mysql ext
			$result = mysql_query($query);
		} else { // mysqli
			$result = mysqli_query($this->conn, $query);
		}
		if(!$result) {
			if($this->api == 1) { // mysql ext
				$this->err_msg = mysql_error();
				$this->errno = mysql_errno();
			} else { // mysqli
				$this->err_msg = mysqli_error($this->conn);
				$this->errno = mysqli_errno($this->conn);
			}
		}
		return $result;
	}
	
	function transaction_start() {
		if($this->in_transaction) {
			$this->err_msg = "transaction_start called after a transaction has been started";
			return false;
		} else {
			if($this->query("START TRANSACTION")) {
				$this->in_transaction = 1;
				return true;
			} else {
				return false;
			}
		}
	}
	
	function transaction($query) {
		if(!$this->in_transaction) {
			$this->err_msg = "transaction_query called without a transaction";
			return false;
		} else {
			if(!$this->query($query)) {
				$this->transaction_rollback();
				return false;
			} else {
				return true;
			}
		}
	}
	
	
	function transaction_commit() {
		if(!$this->in_transaction) {
			$this->err_msg = "transaction_commit called without a transaction";
			return false;
		} else {
			return $this->query("COMMIT");
			$this->in_transaction = 0;
		}
	}
	
	
	function transaction_rollback() {
		if(!$this->in_transaction) {
			$this->err_msg = "transaction_rollback called without a transaction";
			return false;
		} else {
			return $this->query("ROLLBACK");
			$this->in_transaction = 0;
		}
	}
	
	function statement($query, $param_types, $params_array) {
		if($this->api == 1) { // mysql ext
			$this->err_msg = 'No prepare statement support using MySQL Extension API';
			return false;
		} else {
			// prepare statement
			$statement = mysqli_prepare($this->conn, $query);
			if($statement === FALSE) {
				$this->err_msg = mysqli_error($this->conn);
				$this->errno = mysqli_errno($this->conn);
			}
			// bind params
			$refs = array($statement, $param_types);
			foreach($params_array as $key => $value) {
				$refs[] = &$params_array[$key];
			}
			call_user_func(mysqli_stmt_bind_param, $refs);
			// execute
			if(mysqli_stmt_execute($statement) === FALSE) {
				$this->err_msg = mysqli_error($this->conn);
				$this->errno = mysqli_errno($this->conn);
			}
			// get results
			$result = mysqli_stmt_get_result($statement);
			// close statement
			mysqli_stmt_close($statement);
			
			return $result;
			
		}
	}
	
	/**
	 *
	 * Fetch query results in associate array
	 * @param object $result -- valid result object return by query()
	 * @return An associate array of one row, or NULL if no more result to fetch
	 * Note: can be called repeatly to fetch multiple rows, until it returns NULL
	 */
	function fetch_result_assoc_array($result) {
		if($this->api == 1) { // mysql ext
			return mysql_fetch_array($result, MYSQL_ASSOC);
		} else { // mysqli
			return mysqli_fetch_array($result, MYSQLI_ASSOC);
		}
	}
	
	/**
	 *
	 * Fetch query results in numerical array
	 * @param object $result -- valid result object return by query()
	 * @return an simple (numeric array of one row, or NULL if no more result to fetch
	 * Note: can be called repeatly to fetch multiple rows, until it returns NULL
	 */
	function fetch_result_num_array($result) {
		if($this->api == 1) { // mysql ext
			return mysql_fetch_row($result);
		} else { // mysqli
			return mysqli_fetch_row($result);
		}
	}
	
	/**
	 *
	 * Fetch a single query result (first data cell of row 0)
	 * @param object $result -- valid result object return by query()
	 * @return result of first data cell of row 0 in corresponding type
	 */
	function fetch_result_single($result) {
		if($this->api == 1) { // mysql ext
			return mysql_result($result, 0);
		} else { // mysqli
			// no direct equivalent in mysqli
			$row = mysqli_fetch_row($result);
			if($row !== FALSE) {
				return $row[0];
			} else {
				return FALSE;
			}
		}
	}
	
	function get_affected_rows() {
		if($this->api == 1) { // mysql ext
			return mysql_affected_rows();
		} else {
			return mysqli_affected_rows($this->conn);
		}
	}
	
	function get_result_num_rows($result) {
		if($this->api == 1) { // mysql ext
			return mysql_num_rows($result);
		} else {
			return mysqli_num_rows($result);
		}
	}
	
	/**
	 *
	 * Get last insert ID
	 */
	function get_last_insert_id() {
		if($this->api == 1) { // mysql ext
			return mysql_insert_id();
		} else {
			return mysqli_insert_id($this->conn);
		}
	}
	
	function get_error() {
		return $this->err_msg;
	}
	
	function get_errno() {
		return $this->errno;
	}
	
	function close() {
		if($this->in_transaction) {
			$this->err_msg = "Database connection not closed due to outstanding transaction";
			return false;
		} else {
			mysql_close($this->conn);
			$this->conn = NULL;
			$this->err_msg = "";
			$this->errno = NULL;
			return true;
		}
	}
	
	/**
	 *
	 * Return a datetime value acceptable by MySQL.
	 * Input value can be a unix timestamp or a datetime string acceptable
	 * by php strtotime()
	 * @param string $datetime_input - datetime input string
	 * @return string of MySQL datetime value
	 */
	static function getDatetimeValue($datetime_input) {
		global $logging;
		
		// make sure we deal with datetime in UTC
		// and we store datetime in UTC
		date_default_timezone_set('UTC');
		
		// Check if this is a timestamp string
		$datetime_ts = NULL;
		if(is_null($datetime_input)) {
			return NULL;
		} else if(is_numeric($datetime_input) && (int)$datetime_input == $datetime_input) {
			// if input var is an integer, assume it is a unix timestamp
			$datetime_ts = $datetime_input;
		} else {
			// TODO: validate ISO date string?
			date_default_timezone_set('UTC');
			$datetime_ts = strtotime($datetime_input);
		}
		return date("Y-m-d H:i:s", $datetime_ts);
		
	}
	
	
	/**
	 *  Escape user input data to protect from SQL Injection Attack
	 *  Paremeters:
	 *  $input -- user data input
	 */
	function escapeUserInput($input) {
		if(empty($input) || !is_string($input)) { // if $input is empty, just return it
			return $input;
		}
		if($this->use_db_escape_string) {
			if($this->api == 1) { // mysql ext
				return mysql_real_escape_string($input, $this->conn);
			} else { // mysqli
				return mysqli_real_escape_string($this->conn, $input);
			}
		} else {
			// assume iso-8859-1
			$search = array("\\", "\x00", "\n", "\r", "'", "\"", "\x1a");
			$replace = array("\\\\" ,"\\x00", "\\n", "\\r", "\'", "\\\"", "\\\x1a");
			return str_replace($search, $replace, $input);
		}
	}
	
	/**
	 *
	 * @deprecated Use parseInputValue().
	 *
	 * Get a proper string which can be used in MySQL insert query value
	 * If input value is NULL, it will return a literal NULL string.
	 * If input is a numeric, it will return a literal numeric string.
	 * If input is an array, it will json-encode it into a quoted JSON string.
	 * For other non-numeric value, it will return a quoted string.
	 *
	 * NOTE: This is an important routine to escape all input value (not just insert) to ensure no SQL injection
	 *
	 * @param mixed $var -- input value
	 * @param boolean $use_double_quote -- use double quote instead of single quote
	 * @param mixed $default_value -- default value if $var is FALSE
	 * @param string $force_type -- force the return type
	 * 					'string' - force to return a quoted string
	 * 					'numeric' - force to return an unquoted value
	 * 					No effect if input is NULL or an array
	 * @return string value
	 *
	 */
	static function getProperInsertQueryValue($var, $use_double_quote=FALSE, $default_value=NULL, $force_type=NULL) {
		global $logging;
		
		if($var === FALSE && !is_null($default_value)) {
			$var = $default_value;
		}
		
		if(is_null($var)) {
			return 'NULL';
		} else if($force_type == 'numeric' || (is_null($force_type) && is_numeric($var))) {
			return $var;
		} else {
			$result_string = '';
			if(is_array($var)) {  // interpret all array as JSON data
				
				$result_string = json_encode($var); // encode it into a json string
				if($result_string === FALSE) {
					$result_string = '';
				}
			} else {
				$result_string = $var;
			}
			if($use_double_quote) {
				return "\"$result_string\"";
			} else {
				return "'$result_string'";
			}
		}
	}
	
	/**
	 *
	 * @deprecated Use parseInputValueForUpdate().
	 *
	 * Get a proper string which can be used in MySQL update query value based on input variable type
	 * If input value is NULL, it will return an empty string.
	 * If input is a numeric, it will reutrn a string key=num.
	 * If input is a non-numeric value, it will return a string key='string'.
	 * @param string $key -- database column key to update
	 * @param mixed $var -- input value
	 * @param boolean $use_double_quote -- use double quote instead of single quote when escaping string
	 * @return string -- the proper value
	 *
	 */
	static function getProperUpdateQueryValue($key, $var, $use_double_quote=FALSE) {
		if($var === FALSE) {
			return '';
		} else if(is_null($var)) {
			return "$key=null,";
		} else if(is_numeric($var)) {
			return "$key=$var,";
		} else {
			$result_string = '';
			if(is_array($var)) {  // interpret all array as JSON data
				
				$result_string = json_encode($var); // encode it into a string
				if($result_string === FALSE) {
					$result_string = '';
				}
			} else {
				$result_string = $var;
			}
			if($use_double_quote) {
				return "$key=\"$result_string\",";
			} else {
				return "$key='$result_string',";
			}
		}
	}
	
	/**
	 *
	 * Get a proper string which can be used in MySQL query value
	 * If input value is NULL, it will return a literal NULL string.
	 * If input is a numeric, it will return a literal numeric string.
	 * If input is an array, it will json-encode it into a quoted JSON string.
	 * For other non-numeric value, it will return a quoted string.
	 *
	 * NOTE: This is an important routine to escape all input value (not just insert) to prevent SQL injection.
	 * This escape the value, then make sure all string are quoted. Preventing cases like this:
	 *
	 *    $id = “0; DELETE FROM users”;
	 *    $id = mysql_real_escape_string($id); // 0; DELETE FROM users
	 *    mysql_query(“SELECT * FROM users WHERE id=$id”);
	 *
	 *
	 * @param mixed $var -- input value
	 * @param boolean $use_double_quote -- use double quote instead of single quote
	 * @param mixed $default_value -- default value if $var is FALSE
	 * @param string -- force the return type
	 * 					'string' - force to return a quoted string
	 * 					'numeric' - force to return an unquoted value
	 * 					No effect if input is NULL or an array
	 * @return string value
	 *
	 */
	function parseInputValue($var, $use_double_quote=FALSE, $default_value=NULL, $force_type=NULL) {
		
		return self::getProperInsertQueryValue($this->escapeUserInput($var), $use_double_quote, $default_value, $force_type);
	}
	
	/**
	 *
	 * Get a proper string which can be used in MySQL update query value based on input variable type
	 * If input value is NULL, it will return an empty string.
	 * If input is a numeric, it will reutrn a string key=num.
	 * If input is a non-numeric value, it will return a string key='string'.
	 * @param string $key -- database column key to update
	 * @param mixed $var -- input value
	 * @param boolean $use_double_quote -- use double quote instead of single quote when escaping string
	 * @return string -- the proper value
	 *
	 */
	function parseInputValueForUpdate($key, $var, $use_double_quote=FALSE) {
		
		return self::getProperUpdateQueryValue($key, $this->escapeUserInput($var), $use_double_quote);
	}
	
	/**
	 * Get a proper string which can be used in MySQL query IN function.
	 * Takes an array of input values and return a bracketed string imploded with comma with each values properly escaped.
	 * e.g. ('a', 'b', 'c')
	 *
	 * If input value is NULL, it will return a literal NULL string.
	 * If input is a numeric, it will return a literal numeric string.
	 * If input is an array, it will json-encode it into a quoted JSON string.
	 * For other non-numeric value, it will return a quoted string.
	 *
	 * @param array $values_array -- array of input values
	 * @param string $use_double_quote -- use double quote instead of single quote (if input value is a string)
	 *
	 * @return string -- a bracketed string with comma imploded
	 */
	function parseInputValueForIn($values_array, $use_double_quote=FALSE) {
		
		$values_array_escaped = array();
		foreach($values_array as $value) {
			$values_array_escaped[] = $this->parseInputValue($value, $use_double_quote);
		}
		
		return '('.implode(',', $values_array_escaped).')';
		
	}
	
	
	/**
	 * Get a proper string which can be used in MySQL update query value based on input value type, using changes array,
	 * and escaping the input as well.
	 * For details of getting proper string, See getProperUpdateQueryValue()
	 *
	 * @param unknown $key -- database column key to update
	 * @param unknown $changes_array -- an associated array of input keys and values
	 * @param string $use_double_quote -- use double quote instead of single quote when escaping string
	 * @return string -- the proper escaped value
	 */
	function parseChangesArrayForUpdate($key, $changes_array, $use_double_quote=FALSE) {
		
		$var = FALSE;
		if(array_key_exists($key, $changes_array)) {
			$var = $changes_array[$key];
		}
		return self::parseInputValueForUpdate($key, $var, $use_double_quote);
		
	}
}

?>