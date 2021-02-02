<?php

/**
 * Class user
 *
 * @author Liam McClelland
 * @property db $conn Reference to an instance of the database class.
 * @property array $manageAccess Array of valid management account types.
 * @property string $CustomerAccess Array of valid customer account types.
 */
class user {
    
    private $conn;
    private $manageAccess;
    private $CustomerAccess;

    /**
     * user constructor.
     * @param db $db
     */

    public function __construct($db) {
        
        $this->conn = $db;
        $this->manageAccess = array("superuser");
        $this->areas = array("manage", "customer");
        $this->CustomerAccess = "customer";
        $this->root = array("customer" => "/my-account", "manage" => "/Manage");
        
        
    }

    /**
     * Get User By Email
     *
     * Retrieve information about a user using their email.
     *
     * @param string $email
     * @return array
     */

    public function getUserByEmail($email) {

        return $this->conn->query("SELECT * FROM gfc_users WHERE user_email = ? LIMIT 1", $email)->fetchArray();

    }

    /**
     * Get User Info
     *
     * Retrieve certain or all pieces of information about a user.
     * @param array|bool $columns Specify which pieces of data you wish to retrieve.
     * @param int|bool $user Id of the user in question.
     * @return array
     */

    public function getUserInfo($columns = false, $user = false) {

        $allowed = array("id", "user_id", "user_name", "user_email", "user_type", "user_created","user_lastlogin");

        // Check if columns have been provided.
        if($columns !== false) {

            // Check that each requested column is allowed to be accessed.
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
     *
     * Validate the login details for a user using a usernam and password.
     *
     * @param string $username Username for the account.
     * @param string $password Password for the account.
     * @param array $settings Optional array of settings.
     * @return array
     */

    public function authenticate($username, $password, $settings = array()) {
        
        // Get user hashed password
        $r = $this->conn->query("SELECT user_pwd FROM gfc_users WHERE user_id = ?", $username)->fetchArray();

        // Check if a user account was found.
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
     *
     * Authenticate and create user's session
     *
     * @param string $username Username for the account.
     * @param string $password Password for the account.
     * @param string $area Area the login is for (Customer screen or Manage screen)
     * @param string|bool $redirect endpoint the user should be redirected to on completion.
     * @return array
     */

    public function login($username, $password, $area, $redirect = false) {
        
        // Check the credentials provided are in valid format
        $id = ((ctype_alnum($username)) ? $username : false);
        $pwd = $password;

        // Check the provided login area exists.
        if(!in_array($area, $this->areas)) {
            return array("status" => "false", "reason" => "invalid_login_area", "reason_desc" => "Invalid login area provided.");
        }

        // Check the username and password are in a validate format.
        if(!$id || !$pwd) {
            
            return array("status" => false, "reason" => "invalid_format", "reason_desc" => "Username or password not in a valid format");
            
        }
        
        // Check the credentials match an account and are valid
        $auth = $this->authenticate($id, $pwd);

        // Check account status
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

        // Check if user is a customer.
        if($this->isCustomer($user["user_type"])) {

            // If the area isn't for a customer, then they are logging in through the wrong area.
            if($area !== "customer") {

                return array("status" => false, "reason" => "invalid_account_type", "reason_desc" => "Invalid account type for this area.");

            }

            // Create the customer session.
            $this->createCustomerSession(array(
                "id" => $user["id"],
                "type" => $user["user_type"],
                "username" => $user["user_id"],
                "name" => $user["user_name"]
            ));
            

        } else {

            // If the area isn't for a Manager, then they are logging in through the wrong area.
            if($area !== "manage") {

                return array("status" => false, "reason" => "invalid_account_type", "reason_desc" => "Invalid account type for this area.");

            }

            // Create Manager session.
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

    /**
     * Is Manager
     *
     * Check if the logged in user is a Manager.
     *
     * @param string $userRole The role of the user.
     * @return bool
     */
    public function isManager($userRole) {

        return in_array($userRole, $this->ManageAccess);

    }

    /**
     * Is Customer
     *
     * Check if the logged in user is a Customer.
     *
     * @param string $userRole The role of the user.
     * @return bool
     */

    public function isCustomer($userRole) {

        return (($userRole == $this->CustomerAccess) ? true : false);

    }

    /**
     * Get Area Root
     *
     * Retrieve the root endpoint for a given area.
     *
     * @param string $area Area you wish to retrieve the root for.
     * @return string
     */
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
     *
     * Log the user out of the system.
     *
     * @param string|bool $redirect Endpoint you would like the user to be redirected to after they have been logged out.
     * @return bool
     */

    public function logout($redirect = false) {
        
        // Destroying the user part of the session
        unset($_SESSION["user"]);
        unset($_SESSION["_customer"]);

        // Check if a redirect value has been provided
        if($redirect !== false) {
            
            $this->redirect($redirect);
            
        } else {
            
            return true;
  
        }
        
    }

    /**
     * Hash Password
     *
     * Hash a password to prevent the true value from being viewed.
     *
     * @param string $pwd Password you wish to hash.
     * @return false|string|null
     */

    protected function hashPassword($pwd) {

        return password_hash($pwd, PASSWORD_DEFAULT);

    }

    /**
     * Update Password
     *
     * Update the password for the logged in user.
     *
     * @param cinema $cinema Reference to an instance of the cinema class.
     * @param int $user Id of the user.
     * @param string $pwd The new password.
     * @param bool $notifyUser Choose whether to let the user know their password has been changed.
     * @return bool
     * @throws Exception
     */

    public function updatePassword($cinema, $user, $pwd, $notifyUser = true) {

        // Hash the password.
        $hashedPwd = $this->hashPassword($pwd);

        // Update the accounts password.
        $update = $this->conn->query("UPDATE gfc_users SET user_pwd = '$hashedPwd' WHERE id = ?", $user)->affectedRows();

        // Check if update was successful
        if($update < 1) {

           return false;

        } else {

            // Check if we need to notify the user.
            if(!$notifyUser) {

                return true;

            } else {

                // Notify the user.

                // Get the user and cinema info.
                $user = $this->getUserInfo(array("user_email", "user_name"), $user);
                $cinemaName = $cinema->getCinemaInfo();

                // Add email to the queue.
                $emailQueue = new emailQueue($this->conn, $cinema);
                $emailQueue->add(
                    array(
                        "name" => $user["data"]["user_name"],
                        "email" => $user["data"]["user_email"]
                    ),
                    "password_updated",
                    "Password updated",
                    array(
                        "%CINEMANAME%" => $cinemaName["name"]
                    )
                );

            }

        }

    }

    /**
     * Register
     *
     * Register a new user to the platform.
     *
     * @param array|bool $user Data object for the new user.
     * @return array|bool
     */

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

    /**
     * Log User Login
     *
     * Log the login of the current user.
     *
     * @param int $user Id of the user.
     * @return bool
     */

    public function logLogin($user) {
        
        $time = time();
        
        $r = $this->conn->query("UPDATE gfc_users SET user_lastlogin = ? WHERE id = ?", $time, $user)->affectedRows();
        
        if($r < 1) {
            
            return false;
            
        } else {
            
            return true;
            
        }
        
    }

    /**
     * Create Manager Session
     *
     * Create a new Manager session using the provided data.
     *
     * @param array $data Data for the session.
     */

    private function createManagerSession($data) {

        $_SESSION["user"] = $data;

    }

    /**
     * Create Customer Session
     *
     * Create a new Customer session using the provided data.
     *
     * @param array $data Data for the session.
     */

    private function createCustomerSession($data) {

        $_SESSION["_customer"] = $data;

    }

    /**
     * Validate Password
     *
     * Check if the provided password passes the system password requirements.
     *
     * @param string $password Password you wish to validate.
     * @return array
     */

    public function validPassword($password) {
        
        // Get the requirements from the system config
        $r = $this->conn->query("SELECT value AS 'settings' FROM gfc_config WHERE `key` = 'password_settings'")->fetchArray();
        
        $settings = json_decode($r["settings"], true);

        $error = array("status" => false);

        // Loop through each setting and ensure the param passes the requirements.
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

            // Check if an error has occurred.
            if(!$error["status"]) {
                
                break;
                
            } 
            
        }

        // Check if an error has occurred.
        if(!$error["status"]) {
            
            return array("status" => false, "error" => "invalid_password", "error_desc" => $error["reason"]);
            
        } else {
            
            return array("status" => true);
            
        }
        
    }

    /**
     * Logged In
     *
     * Check if a user is logged in.
     *
     * @param string $area Area you wish to check the user is logged in for.
     * @return bool
     */

    public function loggedIn($area = "manage") {

        if($area == "manage") {

            $status = ((isset($_SESSION["user"])) ? true : false);

        } else {

            $status = ((isset($_SESSION["_customer"])) ? true : false);

        }

        return $status;
        
    }

    /**
     * Login Required
     *
     * Ensures the user is logged in. If not it will prevent the script from going any further and redirect the user.
     */

    public function loginRequired() {

        // Checking if the user is logged in.
        if(!$this->loggedIn()) {

            notifications::add("info", "Please login to access this page.");
            
            header("Location: /Manage/authenticate/login");
            exit;
            
        }
        
    }

    /**
     * Lockout
     *
     * Lock the user out from signing in for a set time.
     *
     * @return bool
     */

    public function lockout() {

        $_SESSION["lockOut"] = time() + 900;
        return true;

    }

    /**
     * Generate Reset code
     *
     * Generate a reset code for a password reset request.
     *
     * @param int $userId Id of the user.
     * @return string
     * @throws Exception
     */

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

    /**
     * Validate Reset code
     *
     * Validate that the provided reset code is valid code.
     *
     * @param string $code Code provided in the reset Password request.
     * @return bool
     */

    public function validateResetCode($code) {

        $decrypted =  cipher::decrypt($code);
        $decrypted = explode(":", $decrypted);

        // Validating the code contains valid data.
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

        // Checking the code matches the user id and expiry set in the database.
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

    /**
     * Send Reset Password Link
     *
     * Send the reset link to the user to enable them to reset their password.
     *
     * @param cinema $cinema Reference to an instance of the cinema class.
     * @param int $userId Id of the user you wish to send the email to.
     * @return array
     * @throws Exception
     *
     */
    public function sendResetPasswordLink($cinema, $userId) {

        $userInfo = $this->getUserInfo(array("user_email", "user_name"), $userId);

        // Checking if there is a user with the provided id.
        if(!$userInfo["status"]){

            return $userInfo;

        }

        $recipient = array(
            "email" => $userInfo["data"]["user_email"],
            "name" => $userInfo["data"]["user_name"]
        );

        // Generate reset code.
        $secret = $this->generateResetCode($userId);

        // Get cinema info
        $cinemaName = $cinema->getCinemaInfo();
        $cinemaName = $cinemaName["name"];

        // Add email to the emailQueue
        $emailQueue = new emailQueue($this->conn, $cinema);
        $email = $emailQueue->add($recipient, "reset-password", "Reset Password", array(
            "%RESETLINK%" => "https://" . $_SERVER['HTTP_HOST'] . "/auth/reset-password/$secret",
            "%CINEMANAME%" => $cinemaName
        ));

        // Check the email has been successfully added to the queue.
        if(!$email["status"]){

            http_response_code(400);
            print "<pre>"; print_r($email); print "</pre>";
            exit;

        }

        return array("status" => true);

    }
    
    /**
    * Redirect
     *
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