<?php

$app->group("/Manage", function(){

    $this->get("", function($request, $response, $args) {

        return $response = $this->view->render($response, "/manage/home.phtml", ["_title" => "Manage"]);

    });



});