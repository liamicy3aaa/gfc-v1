<?php

$app->group("/auth", function(){
    
    $this->get("/account", function($request, $response, $args) {

        die("unavailable.");
        $user = $this->get("user");
        
        if($user->loggedIn()) {
        
            print "<pre>"; print_r($_SESSION["user"]); print "</pre>";
            exit;
        
        } else {
            
            die("NOT LOGGED IN");
            
        }
        
    });

    $this->get("/ajax/reset-password", function($request, $response, $args){

        $authSalt = cipher::encrypt(time() + rand(14));
        $auth = cipher::encrypt("reset:" . $authSalt);
        $_SESSION["resetToken"] = $auth;
        $_SESSION["resetTokenSalt"] = $authSalt;

        $html = str_replace(array("%AUTHTOKEN%"), array($auth), file_get_contents("../templates/authentication/partial.reset-password.phtml"));

        return $response->withJson(array("html" => $html), 200);
    });

    $this->post("/ajax/reset-password", function($request, $response, $args){

        $user = $this->get("user");
        $cinema = $this->get("cinema");

        if(!isset($_SESSION["resetErrors"])) {
            $_SESSION["resetErrors"] = 0;
        } else {

            if($_SESSION["resetErrors"] > 5 && !isset($_SESSION["lockOut"])) {

                $user->lockout();
                unset($_SESSION["resetToken"]);
                $error = array("error" => "attempts_exceeded", "error_desc" => "Maximum number of attempts exceeded. Please try again later.");

                return $response->withJson($error, 423);
            }

        }

        $body = $request->getParsedBody();
        $required = array("auth", "email");

        foreach($required as $item) {

            if(!isset($body[$item]) || strlen($body[$item]) < 1) {

                $error = array("error" => "missing_info", "error_desc" => "$item missing from request.");
                return $response->withJson($error, 400);

            }
        }

        // Check auth token is valid
        if($body["auth"] !== $_SESSION["resetToken"]) {

            $_SESSION["resetErrors"] += 1;
            $error = array("error" => "invalid_auth", "error_desc" => "Invalid auth token provided. Please try again later.");
            return $response->withJson($error, 401);

        }

        // Check email is a valid email
        if(!filter_var($body["email"], FILTER_SANITIZE_EMAIL)) {

            $_SESSION["resetErrors"] += 1;
            $error = array("error" => "invalid_email", "error_desc" => "Invalid email provided.");
            return $response->withJson($error, 400);

        }

        // Check if email matches a user account
        $account = $user->getUserByEmail($body["email"]);

        if(count($account) < 1) {

            $_SESSION["resetErrors"] += 1;
            $error = array("error" => "invalid_email", "error_desc" => "Invalid email provided.");
            return $response->withJson($error, 400);

        } else {

            $email = $user->sendResetPasswordLink($cinema, $account["id"]);

            if(!$email["status"]) {

                $error = array("error" => "server_error", "error_desc" => $email["error"]);
                return $response->withJson($error, 401);

            } else {

                unset($_SESSION["resetToken"]);
                return $response->withStatus(200);

            }

        }
    });

    $this->get("/reset-password/{code}", function($request, $response, $args){

        if(!isset($args["code"]) || strlen($args["code"]) < 15) {
            return $response->withStatus(400);
        }

        $user = $this->get("user");

        if(!$user->validateResetCode($args["code"])){
            return $response->withJson(array("error"=>"invalid_reset_code"),400);
        } else {
            $cinema = $this->get("cinema");
            $authSalt = cipher::encrypt(time() + rand(14));
            $auth = cipher::encrypt("reset:" . $authSalt);
            $_SESSION["resetToken"] = $auth;
            $_SESSION["resetTokenSalt"] = $authSalt;

            $requirements = $cinema->getConfigItem("password_settings");
            $r = json_decode($requirements["value"], true);

            $html = str_replace(
                array("%AUTHTOKEN%", "%REQUIREMENTS%", "%minlen%", "%maxlen%"),
                array($auth,$requirements["value"], $r["minlen"], $r["maxlen"]),
                file_get_contents("../templates/authentication/reset-password.phtml"));

            return $response = $this->view->render($response, "blank.phtml", ["_title" => "New Password", "html" => $html]);

        }

    });

    $this->post("/reset-password", function($request, $response, $args){
        try {
            $user = $this->get("user");
            $cinema = $this->get("cinema");
            $body = $request->getParsedBody();
            $required = array("auth", "pwd1", "pwd2");

            foreach ($required as $item) {
                if (!isset($body[$item])) {
                    return $response->withJson(array("error" => "missing_info", "error_desc" => "$item missing from request."), 400);
                }
            }

            if ($body["pwd1"] !== $body["pwd2"]) {
                return $response->withJson(array("error" => "pwd_mismatch", "error_desc" => "Passwords don't match."), 400);
            }

            $validate = $user->validPassword($body["pwd1"]);
            if (!$validate["status"]) {
                return $response->withJson(array("error" => "pwd_invalid", "error_desc" => "Password not valid."), 400);
            }

            if(!isset($_SESSION["resetUser"])) {
                return $response->withJson(array("error" => "server_error", "error_desc" => "An error occurred. Please try again later. "), 500);
            }

            //Update password
            $update = $user->updatePassword($cinema, $_SESSION["resetUser"], $body["pwd1"], true);

            if($update) {
                notifications::add("success", "Password successfully reset. You can now login with your new details.");
                return $response->withJson(array("status" => 200),200);
            } else {
                return $response->withJson(array("error" => "server_error", "error_desc" => "An error occurred while updating password. Please try again later. "), 500);
            }

        } catch(Error $e){
            die($e->getMessage());
        }

    });
    
    $this->post("/login", function($request, $response, $args) {

        $user = $this->get("user");
        $area = false;

        if(!in_array($_SERVER["REMOTE_ADDR"],array("86.3.187.72", "62.252.28.218"))) {

            notifications::add("danger", "Platform is currently under maintenance. Please try again later.");

            $user->redirect("/");

        }

        // Checking if user is locked out. If so, prevent authentication
        if(isset($_SESSION["lockOut"]) && $_SESSION["lockOut"] > time()) {

            notifications::add("danger", "You have been temporarily locked out. Please try again later.");
            $user->redirect("/");

        } elseif(isset($_SESSION["lockOut"]) && time() > $_SESSION["lockOut"]) {

            unset($_SESSION["lockOut"]);

        }

        
        $body = $request->getParsedBody();
        
        $required = array("username", "password");
        
        foreach($required as $item) {
            
            if(!isset($body[$item])) {
                
                die("MISSING INFO - $item");    
                
            }
            
        }

        // Checking for authentication token
        if(!isset($body["auth"])) {

            notifications::add("danger", "Invalid authentication token. Please try again.");
            $user->redirect("/");

        } else {

            $data = explode(":", cipher::decrypt($body["auth"]));

            if(count($data) < 2 || $_SESSION["authTokenSalt"] !== $data[1]) {

                notifications::add("danger", "Invalid authentication token. Please try again.");
                $user->redirect("/");

            }

            $area = $data[0];

            unset($_SESSION["authToken"]);
            unset($_SESSION["authTokenSalt"]);

        }

        // Authenticating user
        $login = $user->login($body["username"], $body["password"], $area);
        
        if(!$login["status"]) {

            $_SESSION["loginAttemptCount"] += 1;

            if($_SESSION["loginAttemptCount"] == 5) {

                $user->lockout();
                notifications::add("danger", "Maximum number of attempts exceeded. Please try again later.");
                $user->redirect($user->getAreaRoot($area));

            }
            
            notifications::add("danger", $login["reason_desc"]);
            
            $user->redirect($user->getAreaRoot($area));
            print $user->getAreaRoot($area);
            exit;
            
        }

        unset($_SESSION["loginAttemptCount"]);

        $user->redirect($user->getAreaRoot($area));
        exit;
        
    });
    
    $this->get("/logout", function($request, $response, $args) {
        
        $user = $this->get("user");

        if(isset($_SESSION["_customer"])) {
            $redirect = $user->root["customer"];
        } else {
            $redirect = $user->root["manage"];
        }
        
        $user->logout($redirect);
        
    });
    
    /*$this->get("/register/gfcAuth", function($request, $response, $args) {
       
       $user = $this->get("user");
       
       $reg = $user->register(array(
            "user_id" => "admin",
            "user_pwd" => "gfc123",
            "user_name" => "Administrator",
            "user_type" => "superuser",
            "user_email" => "gfc@gadgetfreak.co.uk"
       ));
       
       print "<pre>"; print_r($reg); print "</pre>";
       exit;
        
    });*/
    
});