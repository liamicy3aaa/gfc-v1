<?php

$app->group("/Manage", function(){

    $this->get("", function($request, $response, $args) {
        
        if(!isset($_SESSION["_manage"]["user"])) {
            
            header("Location: /Manage/authenticate/login");
            exit;
            
        }

        return $response = $this->manageView->render($response, "/dashboard/dashboard.phtml", ["_title" => "Manage"]);

    });

    /** AUTHENTICATION **/
    
    $this->get("/authenticate/login", function($request, $response, $args){

        $_SESSION["_manage"]["user"] = true;

        $html = file_get_contents("../templates/Manage/authentication/login.phtml");
        
        return str_replace("<?=\$title?>", "Login | " . $_SESSION["system"]["name"], $html);
        
    });

});