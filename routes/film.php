<?php

$app->group("/film", function(){

    $this->get("/{filmId}", function($request, $response, $args) {
        
        $filmName = str_replace("-", " ", $args["filmId"]);

        return $response = $this->view->render($response, "/film/film-detail.phtml", ["_title" => $filmName]);

    });






});