<?php

class user {
    
    private $conn;
    private $manageAccess;
    
    public function __construct($db) {
        
        $this->conn = $db;
        $this->manageAccess = array("superuser");
        
        
    }
    
    /**
    * Authenticate
    * This checks if the provided credentials are valid for an account
    * 
    * @param mixed $username
    * @param mixed $password
    * @param mixed $settings
    */
    
    public function authenticate($username, $password, $settings = array()) {
        
        // Get user encrypted password
        $r = $this->conn->query("SELECT user_pwd FROM gfc_users WHERE user_id = ?", $username)->fetchArray();
        
        if(count($r) < 1) {
            
            return array("status"=>false, "reason"=>"invalid_username");
            
        }
        
        // User password
        $pwd = $r["user_pwd"];
        
        // Checking if password matches
        if(password_verify($password, $pwd)) {
            
            return array("status" => true);
            
        } else {
            
            return  array("status"=>false, "reason"=>"invalid_password");
            
        }   
        
    }
    
    /**
    * Login
    * This function will log the user in and setup the session with the relevant information
    * 
    * @param mixed $username
    * @param mixed $password
    * @param mixed $redirect [Optional] - You can provide a string url that the function can redirect to on successful completion.
    */
    
    public function login($username, $password, $redirect = false) {
        
        // Check the credentials provided are in valid format
        $id = ((ctype_alnum($username)) ? $username : false);
        $pwd = ((ctype_alnum($password)) ? $password : false);
        
        if(!$id || !$pwd) {
            
            return array("status" => false, "reason" => "invalid_format", "reason_desc" => "Username or password not in a valid format");
            
        }
        
        // Check the credentials match an account and are valid
        $auth = $this->authenticate($id, $pwd);
        
       // var_dump($auth); exit;
        
        if(!$auth["status"]) {
            
            switch($auth["reason"]) {
                
                case "invalid_username":
                    $error = "Incorrect username or password [1]";
                    break;
                    
                case "invalid_password":
                    $error = "Incorrect username or password [2]";
                    break;
                    
                default :
                    $error = "An unknown error occurred [" . __LINE__ . "]";
                    break;       
                
            }
            
            return array("status" => false, "reason" => $auth["reason"], "reason_desc" => $error);
            
        }
        
        // Get users information
        $user = $this->conn->query("SELECT * FROM gfc_users WHERE user_id = ?", $id)->fetchArray();
        
        // Checking user has an account that is allowed to login
        if(!in_array($user["user_type"], $this->manageAccess)) {
            
            return array("status" => false, "reason" => "invalid_account_type", "reason_desc" => "Invalid account type for this area.");
            
        }
        
        // Setup session
        $_SESSION["user"] = array();
        $_SESSION["user"]["id"] = $user["id"];
        $_SESSION["user"]["type"] = $user["user_type"];
        $_SESSION["user"]["username"] = $user["user_id"];
        $_SESSION["user"]["name"] = $user["user_name"]; 
        
        // Logging login
        $this->logLogin($user["id"]);
        
        // Checking if redirect url has been provided
        
        if($redirect !== false) {
            
            // If so, redirect user to the provided URL
            
            $this->redirect($redirect);   
            
        } else {
            
            // If not, return successful message to caller
            
            return array("status" => true, "user" => $user["id"]);
            
        }
        
    }
    
    /**
    * Logout
    * Logs the user out of the system and can redirect them to the specified page.
    * 
    * @param mixed $redirect
    */
    
    public function logout($redirect = false) {
        
        // Destroying the user part of the session
        unset($_SESSION["user"]);
        
        if($redirect !== false) {
            
            $this->redirect($redirect);
            
        } else {
            
            return true;
  
        }
        
    }
    
    public function register($user = false) {
        
        $required = array(
            "user_id",
            "user_email",
            "user_pwd",
            "user_type",
            "user_name"
        );
        
        $numeric = array(
            "user_created",
        );
        
        // Checking all required items are present and are in the correct format
        foreach($required as $item) {
            
            if(!isset($user[$item])) {
                
                return array("status" => false, "reason" => "missing_info", "item" => $item);
                
            } elseif(!ctype_alnum($user[$item]) && !in_array($item, array("user_email"))) {
                
                return array("status" => false, "reason" => "invalid_format", "item" => $item);
                
            }
            
        }
        
        // Check the provided password matches system requirements
        $passCheck = $this->validPassword($user["user_pwd"]);
        
        if(!$passCheck["status"]) {
            
            return $passCheck;
            
        }
        
        // Hash password
        $user["user_pwd"] = password_hash($user["user_pwd"], PASSWORD_DEFAULT);
        
        // Additional info
        $user["user_created"] = time();
        
        // Creating query
        $columns = "";
        $values = "";
        $count = 0;
            
        foreach($user as $item => $value) {
            
            $columns .= (($count >= 1) ? "," : "") . $item;
            
            if(!in_array($item, $numeric)) {
                
                $values .= (($count >= 1) ? "," : "") . "'" . $value . "'";
                
            } else {
                
              $values .= (($count >= 1) ? "," : "") . $value;  
                
            }
            
            $count++;
            
        }

        // Running query
        $r = $this->conn->query("INSERT INTO gfc_users ($columns) VALUES ($values)");
        
        return true;
        
        
    }
    
    public function logLogin($user) {
        
        $time = time();
        
        $r = $this->conn->query("UPDATE gfc_users SET user_lastlogin = ? WHERE id = ?", $time, $user)->affectedRows();
        
        if($r < 1) {
            
            return false;
            
        } else {
            
            return true;
            
        }
        
    }
    
    public function validPassword($password) {
        
        // Get the requirements from the system config
        $r = $this->conn->query("SELECT value AS 'settings' FROM gfc_config WHERE `key` = 'password_settings'")->fetchArray();
        
        $settings = json_decode($r["settings"], true);
        
        $settings = array("specialchar" => 1, "capitalchar" => 1, "number", "minlen" => 6, "maxlen" => 16);
        
        $error = array("status" => false);
        
        foreach($settings as $setting => $param) {
            
            switch($setting) {
                
                case "specialchar":
                
                    if($param == 1) {
                    
                        if (!preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $password)) {  
                            
                            // No special character
                            $error["status"] = true;
                            $error["reason"] = "Password must contain at least one special character";
                            
                            break;
                        
                        }
                    
                    }
                    break;
                    
                case "capitalchar":
                
                    if($param == 1) {
                        
                        if(!preg_match('/[A-Z]/', $password)){
                        
                             // No Capital letter
                            $error["status"] = true;
                            $error["reason"] = "Password must contain at least one capital letter";
                            
                            break;
                            
                        }
                        
                    }
                    break;
                    
                case "number":
                
                    if($param == 1) {
                        
                        if(!preg_match('/[0-9]/', $password)){
                        
                             // No Number
                            $error["status"] = true;
                            $error["reason"] = "Password must contain at least one number";
                            
                            break;
                            
                        }
                        
                    }
                    break;
                    
                case "minlen":
                
                    if(strlen($password) < $param) {
                        
                         // No special character
                        $error["status"] = true;
                        $error["reason"] = "Password must be at least $param characters long";
                        
                        break;
    
                    }
                    break;
                    
                case "maxlen":
                
                    if(strlen($password) > $param) {
                        
                         // No special character
                        $error["status"] = true;
                        $error["reason"] = "Password cannot be longer than $param characters";
                        
                        break;
    
                    }
                    break;
                    
                default:
                
                    die("INVALID SETTING PROVIDED [$setting]");
                    break;                
                    
                
            }
            
            if(!$error["status"]) {
                
                break;
                
            } 
            
        }
        
        if(!$error["status"]) {
            
            return array("status" => false, "error" => "invalid_password", "error_desc" => $error["reason"]);
            
        } else {
            
            return array("status" => true);
            
        }
        
    }
    
    public function loggedIn() {
        
        $status = ((isset($_SESSION["user"])) ? true : false);
        
        return $status;
        
    }
    
    public function loginRequired() {
        
        if(!$this->loggedIn()) {

            notifications::add("info", "Please login to access this page.");
            
            header("Location: /Manage/authenticate/login");
            exit;
            
        }
        
    }

    public function lockout() {

        $_SESSION["lockOut"] = time() + 900;
        return true;

    }
    
    /**
    * Redirect
    * Redirects user to the provided URL
    * 
    * @param mixed $url
    */
    
    public function redirect($url) {
        
        header("Location: " . $url . "");
        exit;
        
    }   
    
}