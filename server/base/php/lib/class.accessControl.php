<?php
/**
 *
 * Class accessControl
 * This authentication model use PHP session.
 * Advantages:
 * 	1. Good for web application
 * 	2. Do not require DB accession (except PHP session requirement). Quick and efficient.
 *  3. Pretty transparent to the controller (as PHP session has taken care most of the passing and storage issues).
 * Disadvantages:
 * 	1. Don't work with API implementation where client does not support cookies.
 * 	2. Do not go well with multiple web nodes as PHP session is not shared between nodes by default
 *  3. Authentication 'area' is not supported (user can be independently authenticated into different
 *  	areas without affecting each others)
 *
 * @author peter
 *
 */
class accessControl {
    
    // authentication audit results
    public static $audit_authenticated = 0;
    public static $audit_credential_mismatched = 1;
    public static $audit_credential_expired = 2;
    public static $audit_access_level_failed = 3;
    public static $audit_unknown = 4;
    
    private $_audit = 4;   // authentication audit status
    private $_user_id = NULL;   // user Id os this auth belonging to
    
    // authentication areas
    public static $area_unknown = 0;
    public static $area_admin = 1;
    public static $area_api = 2;
    public static $area_portal = 3;
    
    public function __construct($user_id=NULL, $input_auth_token=NULL, $resource_access_level=NULL, $area=0) {
        global $logging;
        
        session_start(); // start a php session. This should be before everything.
        
        // if auth session has already been set, do a check to make sure it's ok
        if(!empty($user_id) && !empty($input_auth_token) && !empty($resource_access_level)) {
            $this->check($user_id, $resource_access_level, $area);
        }
        // Otherwise, this create an empty (unauthenticated) auth session
        // Caller call eventually invoke check() or set() to authenticate this session
    }
    
    /**
     *
     * Check authentication
     * @return number
     */
    public function check($user_id, $resource_access_level, $area=0) {
        
        if(empty($_SESSION['id'])) {
            $this->_audit = self::$audit_credential_mismatched;
            return FALSE;
        }
        
        // check session expire
        if((time() - $_SESSION['last_access_time']) > config::$session_time_valid) {
            $this->_audit = self::$audit_credential_expired;
            return FALSE;
        }
        
        // check access level
        if($_SESSION['type'] > $resource_access_level) {
            $this->_audit = self::$audit_access_level_failed;
            return FALSE;
        }
        
        $this->_audit = self::$audit_authenticated;
        return TRUE;
        
    }
    
    /**
     * update the last access time
     */
    public function extendTime() {
        // Do not extend expiry if user is not authenticated first
        if($this->_audit !== self::$audit_authenticated) {
            return FALSE;
        }
        
        $_SESSION['last_access_time'] = time();
        
    }
    
    
    public function clear() {
        $_SESSION['id'] = '';
        $_SESSION['type'] = '';
        $_SESSION['area'] = 0;
        $_SESSION['this_logged_time'] = null;
        $_SESSION['last_access_time'] = null;
    }
    
    public function set($id, $type, $area) {
        
        $_SESSION['id'] = $id;
        $_SESSION['type'] = $type;
        $_SESSION['area'] = $area;
        if(isset($_SESSION['this_logged_time'])) {
            $_SESSION['last_logged_time'] = $_SESSION['this_logged_time'];
        }
        $_SESSION['this_logged_time'] = time();
        $_SESSION['last_access_time'] = time();
        
    }
    
    /**
     *
     * Return the authentication audit result
     */
    public function getAudit() {
        return $this->_audit;
    }
    
    public function getUserId() {
        return $_SESSION['id'];
    }
    
    public function getUserType() {
        return $_SESSION['type'];
    }
    
    public function getThisLoggedTime() {
        return $_SESSION['this_logged_time'];
    }
    
    public function getLastLoggedTime() {
        if(isset($_SESSION['last_logged_time'])) {
            return $_SESSION['last_logged_time'];
        } else {
            return NULL;
        }
    }
    
}
