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

        if($_SERVER["REMOTE_ADDR"] !== "92.238.2.109") {

            notifications::add("danger", "Platform is currently under maintenance. Please try again later.");
            header("Location: https://" . $_SERVER["HTTP_HOST"] ."/Manage/authenticate/login");
            exit;

        }
        
        $body = $request->getParsedBody();
        
        $required = array("username", "password");
        
        foreach($required as $item) {
            
            if(!isset($body[$item])) {
                
                die("MISSING INFO - $item");    
                
            }
            
        }
        
        $login = $user->login($body["username"], $body["password"]);
        
        if(!$login["status"]) {
            
            notifications::add("danger", $login["reason_desc"]);
            
            header("Location: https://" . $_SERVER["HTTP_HOST"] ."/Manage/authenticate/login");
            exit;
            
        }
                                    
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