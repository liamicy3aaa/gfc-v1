<?php

$app->group("/auth", function(){
    
    $this->get("/account", function($request, $response, $args) {
        
        $user = $this->get("user");
        
        if($user->loggedIn()) {
        
            print "<pre>"; print_r($_SESSION["user"]); print "</pre>";
            exit;
        
        } else {
            
            die("NOT LOGGED IN");
            
        }
        
    });
    
    $this->post("/login", function($request, $response, $args) {

        $user = $this->get("user");

        if(!in_array($_SERVER["REMOTE_ADDR"],array("86.3.187.72", "62.252.28.218"))) {

            notifications::add("danger", "Platform is currently under maintenance. Please try again later.");
            header("Location: https://" . $_SERVER["HTTP_HOST"] ."/Manage/authenticate/login");
            exit;

        }

        // Checking if user is locked out. If so, prevent authentication
        if(isset($_SESSION["lockOut"]) && $_SESSION["lockOut"] > time()) {

            notifications::add("danger", "You have been temporarily locked out. Please try again later.");
            $user->redirect("/Manage/authenticate/login");

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
        if(!isset($body["auth"]) || $_SESSION["authToken"] !== $body["auth"]) {

            notifications::add("danger", "Invalid authentication token. Please try again.");
            $user->redirect("/Manage/authenticate/login");

        } else {

            unset($_SESSION["authToken"]);

        }

        // Authenticating user
        $login = $user->login($body["username"], $body["password"]);
        
        if(!$login["status"]) {

            $_SESSION["loginAttemptCount"] += 1;

            if($_SESSION["loginAttemptCount"] == 5) {

                $user->lockout();
                notifications::add("danger", "Maximum number of attempts exceeded. Please try again later.");
                $user->redirect("/Manage/authenticate/login");

            }
            
            notifications::add("danger", $login["reason_desc"]);
            
            header("Location: https://" . $_SERVER["HTTP_HOST"] ."/Manage/authenticate/login");
            exit;
            
        }

        unset($_SESSION["loginAttemptCount"]);
                                    
        header("Location: /Manage");
        exit;
        
    });
    
    $this->get("/logout", function($request, $response, $args) {
        
        $user = $this->get("user");
        
        $user->logout("/Manage");
        
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