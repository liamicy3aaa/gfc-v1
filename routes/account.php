<?php

$app->group("/my-account", function(){

    $this->get("", function($request, $response, $args) {

        return $response = $this->view->render($response, "/account/my-account.phtml", ["_title" => "My Account"]);


    });




});