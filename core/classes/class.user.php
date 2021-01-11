<?php

class user {
    
    private $conn;
    private $manageAccess;
    
    public function __construct($db) {
        
        $this->conn = $db;
        $this->manageAccess = array("superuser");
        $this->areas = array("manage", "customer");
        $this->CustomerAccess = "customer";
        $this->root = array("customer" => "/my-account", "manage" => "/Manage");
        
        
    }

    public function getUserByEmail($email) {

        $result = $this->conn->query("SELECT * FROM gfc_users WHERE user_email = ? LIMIT 1", $email)->fetchArray();

        return $result;

    }

    public function getUserInfo($columns = false, $user = false) {

        $allowed = array("id", "user_id", "user_name", "user_email", "user_type", "user_created","user_lastlogin");

        if($columns !== false) {

            foreach($columns as $column) {

                if(!in_array($column, $allowed)) {

                    return array(
                        "status" => false,
                        "error" => "invalid_column",
                        "error_desc" => "$column is not an allowed column."
                    );

                }

            }

            $columns = implode(",", $columns);

        } else {

            $columns = "*";

        }

        $user = (($user === false) ? $_SESSION["user"]["id"] : $user);

        $query = $this->conn->query("SELECT $columns FROM gfc_users WHERE id = ?", $user)->fetchArray();

        return array(
            "status" => true,
            "data" => $query
        );

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
    
    public function login($username, $password, $area, $redirect = false) {
        
        // Check the credentials provided are in valid format
        $id = ((ctype_alnum($username)) ? $username : false);
        $pwd = $password;

        if(!in_array($area, $this->areas)) {
            return array("status" => "false", "reason" => "invalid_login_area", "reason_desc" => "Invalid login area provided.");
        }
        
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

        if($this->isCustomer($user["user_type"])) {

            if($area !== "customer") {

                return array("status" => false, "reason" => "invalid_account_type", "reason_desc" => "Invalid account type for this area.");

            }

            $this->createCustomerSession(array(
                "id" => $user["id"],
                "type" => $user["user_type"],
                "username" => $user["user_id"],
                "name" => $user["user_name"]
            ));
            

        } else {

            if($area !== "manage") {

                return array("status" => false, "reason" => "invalid_account_type", "reason_desc" => "Invalid account type for this area.");

            }

            $this->createManagerSession(array(
                "id" => $user["id"],
                "type" => $user["user_type"],
                "username" => $user["user_id"],
                "name" => $user["user_name"]
            ));

        }
        
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

    public function isManager($userRole) {

        return in_array($userRole, $this->ManageAccess);

    }

    public function isCustomer($userRole) {

        return (($userRole == $this->CustomerAccess) ? true : false);

    }

    public function getAreaRoot($area) {

        if($area == "customer") {
            return $this->root["customer"];
        } elseif($area == "manage") {
            return $this->root["manage"];
        } else {

            die("AREA ISSUE:<pre>AREA: $area</pre>");

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
        unset($_SESSION["_customer"]);
        
        if($redirect !== false) {
            
            $this->redirect($redirect);
            
        } else {
            
            return true;
  
        }
        
    }

    protected function hashPassword($pwd) {
        return password_hash($pwd, PASSWORD_DEFAULT);
    }

    public function updatePassword($cinema, $user, $pwd, $notifyUser = true) {

        $hashedPwd = $this->hashPassword($pwd);

        $update = $this->conn->query("UPDATE gfc_users SET user_pwd = '$hashedPwd' WHERE id = ?", $user)->affectedRows();

        if($update < 1) {

           return false;

        } else {

            if(!$notifyUser) {

                return true;

            } else {

                $user = $this->getUserInfo(array("user_email", "user_name"), $user);
                $cinemaName = $cinema->getCinemaInfo();

                $email = new email($cinema);
                $email->setTemplate("password_updated");
                $email->setSubject("Password updated");
                $email->addRecipient($user["data"]["user_email"], $user["data"]["user_name"]);
                $email->addContent(array(
                    "%CINEMANAME%" => $cinemaName["name"]
                ));

                $send = $email->send();

                    return true;

            }

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
        $user["user_pwd"] = $this->hashPassword($user["user_pwd"]);
        
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

    private function createManagerSession($data) {

        $_SESSION["user"] = $data;

    }

    private function createCustomerSession($data) {

        $_SESSION["_customer"] = $data;

    }
    
    public function validPassword($password) {
        
        // Get the requirements from the system config
        $r = $this->conn->query("SELECT value AS 'settings' FROM gfc_config WHERE `key` = 'password_settings'")->fetchArray();
        
        $settings = json_decode($r["settings"], true);

        $error = array("status" => false);
        
        foreach($settings as $setting => $param) {
            
            switch($setting) {
                
                case "specialchar":
                
                    if($param == 1) {
                    
                        if (!preg_match('/[\'^�$%&*()}{@#~?><>,|=_+�-]/', $password)) {  
                            
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

                case "lowerchar":

                    if($param == 1) {

                        if(!preg_match('/[a-z]/', $password)){

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
    
    public function loggedIn($area = "manage") {

        if($area == "manage") {

            $status = ((isset($_SESSION["user"])) ? true : false);

        } else {

            $status = ((isset($_SESSION["_customer"])) ? true : false);

        }

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

    public function generateResetCode($userId) {

        $time = time()+3600;
        $salt = random_bytes(10);
        $user = $userId;
        $ip = $_SERVER["REMOTE_ADDR"];

        $secret = $salt . ":" . $user . ":". $time . ":" . $ip;
        $secret = cipher::encrypt($secret);

        $result = $this->conn->query("INSERT INTO gfc_users_reset (secret, user_id, expiry) VALUES ('" . $secret . "', $userId, $time)");

        return $secret;

    }

    public function validateResetCode($code) {

        $decrypted =  cipher::decrypt($code);
        $decrypted = explode(":", $decrypted);


        if(count($decrypted) > 4 || count($decrypted) < 1 || !ctype_digit($decrypted[1]) || time() > $decrypted[2]) {
            notifications::add("danger", "Invalid reset code. Please try again later.");
            $this->redirect("/");
            return false;
        }

        // Checking code is in database
        $data = $this->conn->query("SELECT * FROM gfc_users_reset WHERE secret = ?", $code);

        if($data->numRows() < 1) {
            notifications::add("danger", "Invalid reset code. Please try again later.");
            $this->redirect("/");
            return false;
        }

        $data = $data->fetchArray();

        if($data["user_id"] !== intval($decrypted[1]) || $data["expiry"] !== intval($decrypted[2])) {
            notifications::add("danger", "Invalid reset code. Please try again later.");
            $this->redirect("/");
            return false;
        }

        $_SESSION["resetUser"] = $data["user_id"];

        // Delete any outstanding codes for this user to prevent further resets.
        $data = $this->conn->query("DELETE FROM gfc_users_reset WHERE user_id = ?", $data["user_id"]);

        return true;

    }

    public function sendResetPasswordLink($cinema, $userId) {

        $userInfo = $this->getUserInfo(array("user_email", "user_name"), $userId);

        if(!$userInfo["status"]){

            return $userInfo;

        }

        $userEmail = $userInfo["data"]["user_email"];
        $userName = $userInfo["data"]["user_name"];

        $secret = $this->generateResetCode($userId);
        $cinemaName = $cinema->getCinemaInfo();
        $cinemaName = $cinemaName["name"];

        $email = new email($cinema);
        $email->setTemplate("reset-password");
        $email->setSubject("Reset Password");
        $email->addRecipient($userEmail, $userName);
        $email->addContent(array(
            "%RESETLINK%" => "https://" . $_SERVER['HTTP_HOST'] . "/auth/reset-password/$secret",
            "%CINEMANAME%" => $cinemaName
        ));

        $emailSent =  $email->send();

        if(!$emailSent["status"] && $emailSent["error"] !== null) {
            return $emailSent;
        } else {
            return array("status" => true);
        }

    }
    
    /**
    * Redirect
    * Redirects user to the provided URL
    * 
    * @param mixed $url
    */
    
    public function redirect($url) {

        if(strlen($url) < 1) {
            die("ERROR URL");
        }
        
        header("Location: " . $url . "");
        exit;
        
    }   
    
}