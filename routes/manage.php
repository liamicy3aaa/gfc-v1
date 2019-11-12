<?php

$app->group("/Manage", function(){

    $this->get("", function($request, $response, $args) {
        
        $user = $this->get("user");

        if(!$user->loggedIn()) {

            notifications::add("info", "Please login to access this page.");
            
            header("Location: /Manage/authenticate/login");
            exit;
            
        }

        return $response = $this->manageView->render($response, "/dashboard/dashboard.phtml", [
            "_title" => "Manage",
            "_user" => $_SESSION["user"],
            "_page" => "dashboard"
            ]);

    });

    /** SCREENS */

    $this->get("/screens", function($request, $response, $args){

        return $this->manageView->render($response, "/screens/overview.phtml", [
            "_title" => "Manage Screens",
            "_user" => $_SESSION["user"],
            "_page" => "screens"
        ]);


    });

    $this->get("/screens/new", function($request, $response, $args) {

        return $this->manageView->render($response, "/screens/new.phtml", [
            "_title" => "New Screen",
            "_user" => $_SESSION["user"],
            "_page" => "screens"
        ]);

    });

    /** AUTHENTICATION **/
    
    $this->get("/authenticate/login", function($request, $response, $args){

        $user = $this->get("user");

        if($user->loggedIn()) {

            $user->redirect("/Manage");

        }

        $html = file_get_contents("../templates/Manage/authentication/login.phtml");
        
        $errors = notifications::display() . "<br/>";

        return str_replace(array("<?=\$title?>", "#errors#"), array("Login | " . $_SESSION["system"]["name"], $errors), $html);
        
    });


});