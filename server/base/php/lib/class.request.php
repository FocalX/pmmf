<?php

class request {
	// HTTP request method (PUT/POST/GET etc...)
	public $method;
	
	// HTTP request headers
	public $headers;
	
	// HTTP user agent
	public $user_agent;
	
	// RESTful elements evaluated into these variables:
	// /<area>/<action>/<operation>/<parameter>
	// e.g. /api/user/get/154
	public $url_elements;
	public $area;
	public $action;
	public $operation;
	public $parameter;
	
	// Request input data
	public $variables = array(); // input variables include url params, cookies and POST data (JSON data will be evaulated)
	public $cookies = array();
	public $files = array();  // upload files info
	
	// redirect
	private $redirect_location = null;
	
	// return handling variables
	// To be set by controller properly before return 
	private $format;  // return format (http or json)
	private $view;    // view to use
	private $error;   // set if error
	private $json_return_data;  // json data to be returned
	private $html_reutrn_data;  // html data to be returned
	private $http_return_code;  // HTTP code to be returned
	
	public function __construct() {
		
		// initializing class variables
		$this->operation = 'index'; // default operation
		$this->parameter = null;
		$this->error = array();
		$this->view = 'default'; // default view handler
		$this->http_return_code = 200;
		$this->json_return_data = array();
		$this->html_reutrn_data = array();
		$this->format = 'json'; // json as default format

		// evaluating server request
		$this->method = $_SERVER['REQUEST_METHOD'];
		$this->user_agent = $_SERVER['HTTP_USER_AGENT'];
		$this->headers = apache_request_headers();
		
		$path_info = array();
		if(isset($_SERVER['PATH_INFO'])) {
		    $path_info = $_SERVER['PATH_INFO'];
		} else { // if PATH_INFO is not defined, we try to use REQUEST_URI
		    $path_info = explode('?', $_SERVER['REQUEST_URI'])[0];
		}
		if($path_info) {
		    $this->url_elements = explode('/', $path_info, 5);
			$this->area = $this->url_elements[1];
			if(count($this->url_elements) > 2 && !empty($this->url_elements[2])) {
				$this->action = $this->url_elements[2];
			}
			if(count($this->url_elements) > 3 && !empty($this->url_elements[3])) {
				$this->operation = $this->url_elements[3];
			}
			if(count($this->url_elements) > 4 && !empty($this->url_elements[4])) {
				$this->parameter = $this->url_elements[4];
			}
			
			// set efault view to <area>/<action>/<operation>, which could be overriden by user
			$this->view = $this->area.'/'.$this->action.'/'.$this->operation;
			
		} else {
			$this->url_elements = array();  // empty array if no PATH_INFO
		}
		$this->cookies = $_COOKIE;
		$this->variables = $this->_parseIncomingVariables();
		
		return true;
	}

	
	public function setHTTPReturnCode($code) {
		$this->http_return_code = $code;
	}
	
	public function getHTTPReturnCode() {
		return $this->http_return_code;
	}
	
	public function setError($msg) {
		$this->error[] = $msg;
	}
	
	public function getError() {
		if(count($this->error) > 0) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	public function getLastError() {
		return end($this->error);
	}
	
	public function getAllErrors() {
		return $this->error;
	}
	
	// set JSON return data (this will replace any previous data)
	public function setJsonReturnData($array_data) {
		$this->json_return_data = $array_data;
	}
	
	// Add a key->value pair data into JSON return data
	public function addJsonReturnData($key, $value) {
		$this->json_return_data[$key] = $value;
	}
	
	public function getJsonReturnData() {
		return $this->json_return_data;
	}
	
	// set JSON return data (this will replace any previous data)
	public function setHtmlReturnData($array_data) {
		$this->html_reutrn_data = $array_data;
	}
	
	public function addHtmlReturnData($key, $value) {
		$this->html_reutrn_data[$key] = $value;
	}
	
	public function getHtmlReturndata() {
		return $this->html_reutrn_data;
	}

	public function setView($view) {
		$this->view = $view;
	}
	
	public function getView() {
		return $this->view;
	}
	
	public function setReturnFormat($format) {
		$this->format = $format;
	}
	
	public function getReturnFormat() {
		return $this->format;
	}
	
	public function setRedirect($location) {
		$this->redirect_location = $location;
	}
	
	public function getRedirect() {
		return $this->redirect_location;
	}
	
	public function isSecureConnection() {
		// Some older server might not set HTTPS variable. Check port as an addtional check
		return (isset($_SERVER['HTTPS']) && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) || 
					(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
	}

	public function isClientIPAllowed() {
		$client_ip = $this->getClientIP();

		if($client_ip !== FALSE) {			
			foreach(config::$security_whitelisted_ips as $whitelisted_ip) {
				if($client_ip == $whitelisted_ip) {
					return TRUE;
				}
			}
		}
		
		return FALSE;
		
	}

	public function getClientIP() {
		$ipaddress = FALSE;
		if(isset($_SERVER['HTTP_CLIENT_IP'])) {
			$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
		} else if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else if(isset($_SERVER['HTTP_X_FORWARDED'])) {
			$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
		} else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
			$ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
		} else if(isset($_SERVER['HTTP_FORWARDED_FOR'])) {
			$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
		} else if(isset($_SERVER['HTTP_FORWARDED'])) {
			$ipaddress = $_SERVER['HTTP_FORWARDED'];
		} else if(isset($_SERVER['REMOTE_ADDR'])) {
			$ipaddress = $_SERVER['REMOTE_ADDR'];
		}
		
		return $ipaddress;
	}
	
	/**
	 * Parse upload file. Call this function if there are files uploading in the request.
	 * Upload files data will be stored in Class array $files for retrieval. 
	 * @param unknown $input_name - the value of the name attribute of the 
	 * 								HTML input element used to upload the file
	 * @param string $is_image - if this is an image file. (will skip image check if not image file)
	 * 
	 */
	function parseUploadFiles($input_name, $is_image=TRUE) {

 		if(!isset($_FILES[$input_name]['error'])) {
            $this->setError('File upload integrity check failed');
            return;  // possible corrupted $_FILES attack
        }

		if(is_array($_FILES[$input_name]['error'])) {  // multiple files uploaded
			$len = count($_FILES[$input_name]['error']);
			for($i=0;$i<$len;$i++) {
				$this->_doParseUploadFile($i, $_FILES[$input_name]['error'][$i], $_FILES[$input_name]['size'][$i],
                                    $_FILES[$input_name]['tmp_name'][$i]);
			}

		} else { // single file uploaded
		  	$this->_doParseUploadFile(0, $_FILES[$input_name]['error'], $_FILES[$input_name]['size'],
									$_FILES[$input_name]['tmp_name'], $is_image);
		}
	}
	
	private function _doParseUploadFile($num, $error, $size, $tmp_name, $is_image) {

		if($size != 0 && !empty($tmp_name)) {

			$media_type = '';
		  	// check if this file is really an image
			if($is_image) {
				$image_info = getimagesize($tmp_name);
	    		if($image_info === FALSE) {
					$this->setError('Image integrity check failed');
				}
				$media_type =  $image_info['mime'];
			} else {
			    $media_type =  mime_content_type($tmp_name);
			}

			$image_data = file_get_contents(($tmp_name));
			$this->files[$num] = array($media_type, $image_data);
		
		} else {
		  $this->setError('No file uploaded or file is empty');
		}
	}
	
	private function _parseIncomingVariables() {
		global $logging;
		
		$variables = array();

		// first pull all GET/PUT/POST vars in urlencoded query string format
		// which is done automatically by PHP global $_REQUEST
		// add COOKIES as well
		$variables = $_REQUEST;
		
		// now check the content type for JSON or other data type and pull 
		// data from PUT/POST bodies. These override what we got from
		// above if duplicated
		$content_type = false;
		if(isset($_SERVER['CONTENT_TYPE'])) {
			$content_type = $_SERVER['CONTENT_TYPE'];
		}
				
		switch($content_type) {
			// JSON data
			case "application/json":
				$body = file_get_contents("php://input"); // pull data from PUT/POST body
				$body_params = json_decode($body, true);
				if(!is_null($body_params)) {
					foreach($body_params as $param_name => $param_value) {
						$variables[$param_name] = $param_value;
					}
				} else if(json_last_error() != JSON_ERROR_NONE) {  // null does not necessary be an error
					require_once 'class.miscHelpers.php';
					$logging->logMsg(logging::LOG_LEVEL_FATAL, "Bad http request input parameters. Error decoding input json data. PHP JSON error code: ".miscHelpers::getJsonError(json_last_error()));
					$logging->logMsg(logging::LOG_LEVEL_INFO, "Error decoding input json data. Problematic original data body: ".$body);
				}
				break;
			// We don't need pull data in case of POST as
			// this is already done automatically by PHP $_REQUEST
			case "application/x-www-form-urlencoded":
				if($_SERVER['REQUEST_METHOD'] != 'POST') {
					$body = file_get_contents("php://input"); // pull data from PUT/POST body
					parse_str($body, $postvars);
					foreach($postvars as $field => $value) {
						$variables[$field] = $value; // add to or override existing keys
	
					}
				}
				break;
			default:
				// we could parse other supported formats here
				break;
		}

		return $variables;
	}

}