<?php
require_once 'abstractClass.defaultModel.php';

class usersModel extends defaultModel {
    
    const USER_TYPE_SYSADMIN = 1;
    const USER_TYPE_ADMIN = 2;
    const USER_TYPE_REGULAR = 3;
    
    const USER_STATUS_DISABLED = 1;
    const USER_STATUS_ACTIVE = 2;
    const USER_STATUS_INACTIVE = 3;
    const USER_STATUS_BLOCKED = 4;
    
    
    function __construct() {
        parent::__construct();
    }
    
    function __destruct() {
        parent::__destruct();
    }
    
    
}