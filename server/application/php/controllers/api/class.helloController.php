<?php
require_once 'base/php/controllers/api/abstractClass.apiController.php';

/***
 * 
 * A Hello World example API controller class
 *
 */
class helloController extends apiController {
    
    function __construct() {
        global $request, $logging;
        
        // Exempt hello->world API from authentication
        // Note: should be called before parent constructor where authentication is checked
        $this->exemptOperationFromAuthentication('hello', 'world');
        
        parent::__construct();
        
        
    }
    
    /*
     * This is an example API controller method.
     * By calling <server_path>/api/hello/world/param?input=123,
     * this method will return the input param, HTTP method used, build environment, and a result string 'successful' in JSON
     * 
     * This also demotrate an input checking and failed if the query string does not contain key 'input'
     */
    function world($param) {
        global $request, $logging;
        
        $input_vars = $request->variables;
        
        $input = NULL;
        if(array_key_exists('input', $input_vars)) {
            $input = $input_vars['input'];
        } else {
            throw new pmmfException('Required parameter missing', 400,
                array(logging::LOG_LEVEL_ERROR, 'Required parameter missing: input'));
            
        }
        
        $request->addJsonReturnData('query_string', $request->variables);
        $request->addJsonReturnData('http_method', $request->method);
        $request->addJsonReturnData('env', config::$build_env);
        $request->addJsonReturnData('result', 'successful');
        
    }
        
}