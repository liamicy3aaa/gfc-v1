<?php

$app->group("/film", function(){

    $this->get("/{filmId}", function($request, $response, $args) {
        
        $cinema = $this->get("cinema");
        $filmId = cipher::decrypt($args["filmId"]);
        
        $filmInfo = $cinema->getFilmData($filmId);
        
        if(count($filmInfo) < 1) {
            
            die("Film not found");
            
        }

        if($filmInfo["sale_unlock"] !== 0 && $filmInfo["sale_unlock"] > time()) {

            $showtimesHtml = "<h3 class='text-center'>Tickets go on sale<br/>" . $cinema->createCountdown() . "</h3>";

        } else {

            // Get showtimes for film
            $showtimes = $cinema->getShowtimes($filmId);

            $filmInfo["showtimes"] = $showtimes;

            // Build showtimes html
            $showtimesHtml = $cinema->buildShowtimes($filmInfo);

        }

        return $response = $this->view->render($response, "/film/film-detail.phtml", [
            "_title" => $filmInfo["film_name"],
            "showtimes" => $showtimesHtml,
            "banner" => $filmInfo["film_banner"],
            "thumbnail" => $filmInfo["film_thumbnail"],
            "desc" => $filmInfo["film_desc"],
            "rating" => $filmInfo["film_rating"],
            "release" => date("d-m-Y", $filmInfo["film_release"]),
            "runtime" => $filmInfo["film_runtime"],
            "trailer" => $filmInfo["film_trailer"],
            "countdownDate" => ($filmInfo["sale_unlock"] * 1000),
            "current_time" => (time() * 1000)
            ]);

    });






});