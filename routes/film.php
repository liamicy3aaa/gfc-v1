<?php

$app->group("/film", function(){

    $this->get("/{filmId}", function($request, $response, $args) {
        
        $cinema = $this->get("cinema");
        
        $filmInfo = $cinema->getFilmData($args["filmId"]);
        
        if(count($filmInfo) < 1) {
            
            die("Film not found");
            
        }
        
        // Get showtimes for film
        $showtimes = $cinema->getShowtimes($args["filmId"]);
        
        $filmInfo["showtimes"] = $showtimes;
        
        // Build showtimes html
        $showtimesHtml = $cinema->buildShowtimes($filmInfo);

        return $response = $this->view->render($response, "/film/film-detail.phtml", [
            "_title" => $filmInfo["film_name"],
            "showtimes" => $showtimesHtml,
            "banner" => $filmInfo["film_banner"],
            "thumbnail" => $filmInfo["film_thumbnail"],
            "desc" => $filmInfo["film_desc"],
            "rating" => $filmInfo["film_rating"],
            "release" => date("d-m-Y", $filmInfo["film_release"]),
            "runtime" => $filmInfo["film_runtime"]
            ]);

    });






});