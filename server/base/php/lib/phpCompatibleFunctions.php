<?php
/**
 * Replacing PHP built-in functions for backward compatible to earlier PHP versions
 * 
 * Newer PHP core functions used by framework but may not be defined in older PHP versions.
 * 
 * This library is used to ensure backward compatibility to back to PHP v5.4 and other webserver environments
 *   
 */

/**
 * return json error message
 * (PHP 5 >= 5.5.0, PHP 7)
 */
if (!function_exists('json_last_error_msg')) {
	function json_last_error_msg() {
		static $ERRORS = array(
			JSON_ERROR_NONE => 'No error',
			JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
			JSON_ERROR_STATE_MISMATCH => 'State mismatch (invalid or malformed JSON)',
			JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
			JSON_ERROR_SYNTAX => 'Syntax error',
			JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
		);

		$error = json_last_error();
		return isset($ERRORS[$error]) ? $ERRORS[$error] : 'Unknown error';
	}
}

/**
 * This file is part of the array_column library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) Ben Ramsey (http://benramsey.com)
 * @license http://opensource.org/licenses/MIT MIT
 * 
 * (PHP 5 >= 5.5.0, PHP 7)
 */

if(! function_exists('array_column')) {

	/**
	 * Returns the values from a single column of the input array, identified by
	 * the $columnKey.
	 *
	 * Optionally, you may provide an $indexKey to index the values in the returned
	 * array by the values from the $indexKey column in the input array.
	 *
	 * @param array $input
	 *        	A multi-dimensional array (record set) from which to pull
	 *        	a column of values.
	 * @param mixed $columnKey
	 *        	The column of values to return. This value may be the
	 *        	integer key of the column you wish to retrieve, or it
	 *        	may be the string key name for an associative array.
	 * @param mixed $indexKey
	 *        	(Optional.) The column to use as the index/keys for
	 *        	the returned array. This value may be the integer key
	 *        	of the column, or it may be the string key name.
	 * @return array
	 */
	function array_column($input = null, $columnKey = null, $indexKey = null) {
		// Using func_get_args() in order to check for proper number of
		// parameters and trigger errors exactly as the built-in array_column()
		// does in PHP 5.5.
		$argc = func_num_args();
		$params = func_get_args();
		
		if($argc < 2) {
			trigger_error("array_column() expects at least 2 parameters, {$argc} given", E_USER_WARNING);
			return null;
		}
		
		if(! is_array($params[0])) {
			trigger_error('array_column() expects parameter 1 to be array, ' . gettype($params[0]) . ' given', E_USER_WARNING);
			return null;
		}
		
		if(! is_int($params[1]) && ! is_float($params[1]) && ! is_string($params[1]) && $params[1] !== null && ! (is_object($params[1]) && method_exists($params[1], '__toString'))) {
			trigger_error('array_column(): The column key should be either a string or an integer', E_USER_WARNING);
			return false;
		}
		
		if(isset($params[2]) && ! is_int($params[2]) && ! is_float($params[2]) && ! is_string($params[2]) && ! (is_object($params[2]) && method_exists($params[2], '__toString'))) {
			trigger_error('array_column(): The index key should be either a string or an integer', E_USER_WARNING);
			return false;
		}
		
		$paramsInput = $params[0];
		$paramsColumnKey = ($params[1] !== null) ? (string) $params[1] : null;
		
		$paramsIndexKey = null;
		if(isset($params[2])) {
			if(is_float($params[2]) || is_int($params[2])) {
				$paramsIndexKey = (int) $params[2];
			} else {
				$paramsIndexKey = (string) $params[2];
			}
		}
		
		$resultArray = array();
		
		foreach($paramsInput as $row) {
			$key = $value = null;
			$keySet = $valueSet = false;
			
			if($paramsIndexKey !== null && array_key_exists($paramsIndexKey, $row)) {
				$keySet = true;
				$key = (string) $row[$paramsIndexKey];
			}
			
			if($paramsColumnKey === null) {
				$valueSet = true;
				$value = $row;
			} elseif(is_array($row) && array_key_exists($paramsColumnKey, $row)) {
				$valueSet = true;
				$value = $row[$paramsColumnKey];
			}
			
			if($valueSet) {
				if($keySet) {
					$resultArray[$key] = $value;
				} else {
					$resultArray[] = $value;
				}
			}
		}
		
		return $resultArray;
	}
}

/**
 * apache_request_headers replicement for nginx (or other environment where it is not defined)
 * used in class.request.php
 */ 
if (!function_exists('apache_request_headers')) {
	function apache_request_headers() {
		foreach($_SERVER as $key=>$value) {
			if (substr($key,0,5)=="HTTP_") {
				$key=str_replace(" ","-",ucwords(strtolower(str_replace("_"," ",substr($key,5)))));
				$out[$key]=$value;
			}else{
				$out[$key]=$value;
			}
		}
		return $out;
	}
}

/**
 * password_hash (PHP 5 >= 5.5.0, PHP 7)
 * Credit: https://stackoverflow.com/questions/4795385/how-do-you-use-bcrypt-for-hashing-passwords-in-php
 */
if (!function_exists('password_hash')) {
	
	class bcrypt_password {
		private $rounds;
		
		public function __construct($rounds = 10) {
			if (CRYPT_BLOWFISH != 1) {
				throw new Exception("bcrypt not supported in this installation. See http://php.net/crypt");
			}
			
			$this->rounds = $rounds;
		}
		
		public function hash($input){
			$hash = crypt($input, $this->getSalt());
			
			if (strlen($hash) > 13)
				return $hash;
				
				return false;
		}
		
		public function verify($input, $existingHash){
			$hash = crypt($input, $existingHash);
			
			return $hash === $existingHash;
		}
		
		private function getSalt(){
			$salt = sprintf('$2a$%02d$', $this->rounds);
			
			$bytes = $this->getRandomBytes(16);
			
			$salt .= $this->encodeBytes($bytes);
			
			return $salt;
		}
		
		private $randomState;
		private function getRandomBytes($count){
			$bytes = '';
			
			if (function_exists('openssl_random_pseudo_bytes') &&
					(strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')) { // OpenSSL is slow on Windows
						$bytes = openssl_random_pseudo_bytes($count);
			}
			
			if ($bytes === '' && is_readable('/dev/urandom') &&
					($hRand = @fopen('/dev/urandom', 'rb')) !== FALSE) {
						$bytes = fread($hRand, $count);
						fclose($hRand);
					}
					
					if (strlen($bytes) < $count) {
						$bytes = '';
						
						if ($this->randomState === null) {
							$this->randomState = microtime();
							if (function_exists('getmypid')) {
								$this->randomState .= getmypid();
							}
						}
						
						for ($i = 0; $i < $count; $i += 16) {
							$this->randomState = md5(microtime() . $this->randomState);
							
							if (PHP_VERSION >= '5') {
								$bytes .= md5($this->randomState, true);
							} else {
								$bytes .= pack('H*', md5($this->randomState));
							}
						}
						
						$bytes = substr($bytes, 0, $count);
					}
					
					return $bytes;
		}
		
		private function encodeBytes($input){
			// The following is code from the PHP Password Hashing Framework
			$itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
			
			$output = '';
			$i = 0;
			do {
				$c1 = ord($input[$i++]);
				$output .= $itoa64[$c1 >> 2];
				$c1 = ($c1 & 0x03) << 4;
				if ($i >= 16) {
					$output .= $itoa64[$c1];
					break;
				}
				
				$c2 = ord($input[$i++]);
				$c1 |= $c2 >> 4;
				$output .= $itoa64[$c1];
				$c1 = ($c2 & 0x0f) << 2;
				
				$c2 = ord($input[$i++]);
				$c1 |= $c2 >> 6;
				$output .= $itoa64[$c1];
				$output .= $itoa64[$c2 & 0x3f];
			} while (true);
			
			return $output;
		}
	}
	
	/*
	 * $password -- password string to hash (with 10 rounds of cost)
	 * $ignored_algo -- ignored. Always bcrypt.
	 * $ignored_options -- any options are ignored.
	 */
	function password_hash($password, $ignored_algo, $ignored_options) {
		$bcrypt = new bcrypt_password();
		
		return $bcrypt->hash($password);
	}
	
	function password_verify($password, $hash) {
		$bcrypt = new bcrypt_password();
		
		return $bcrypt->verify($password, $hash);
	}
}
