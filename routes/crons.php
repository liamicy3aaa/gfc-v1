<?php

$app->group("/_crons", function(){

//    $this->get("/Liamtest", function($request, $response, $args){
//
////        $emailQueue->add(1,"general", "TEST Email", array("%CONTENT%" => "THIS IS A TEST MESSAGE FROM LIAM."));
////        $emailQueue->add(2,"general", "LIAM IS HERE", array("%CONTENT%" => "Another test message."));
//
//
//    });

    $this->get("/{id}", function($request, $response, $args){

        return crons::startHandler($request, $response, $args, $this);

    });



});