<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

// ERROR REPORTING //
error_reporting(E_ALL);

// APP CONFIGURATION //
require "../app/app.php";

// ### ROUTES ### //
include "../routes/film.php";
include "../routes/booking.php";
include "../routes/account.php";
include "../routes/manage.php";
include "../routes/auth.php";
include "../routes/payments.php";
include "../routes/settings.php";
///////////////////

// LANDING PAGE //
$app->get('/', function (Request $request, Response $response, array $args) {

    $cinema = $this->get("cinema");

    // Get showtimes
    $r = $cinema->getShowtimes(false, array(
        "activeFilms" => true,
        "activeShowings" => true,
    ));

    // If there are no showtimes, return a default message.
    if($r === false) {

        $films = "<div class='alert alert-info text-center my-3'>No films currently showing.</div>";

    } else {

        // Get films using the filmIds

        $films = $cinema->getFilms($r["_films"]);

        $filmInfo = array();
        $filmData = array();

        // Loop through each film and add the showtimes to them.
        foreach($films as $item => $film) {

            if($film["film_status"] == 1) {

                $item = $film;
                $item["showtimes"] = $r[$film["id"]];

                $filmInfo[$film["id"]] = $item;

            }

        }

        // Ensure we don't have multiple copies of the same films and that we only include films that are active.
        foreach (array_unique($r["_films"]) as $id => $film) {
            
            if(isset($filmInfo[$film]["film_status"]) && $filmInfo[$film]["film_status"] == 1) {

            $filmData[] = $filmInfo[$film];
            
            }

        }

        unset($r["_films"]);

        // If film data is empty, return a default message.
        if(empty($filmData)) {
            
            $films = "<div class='alert alert-info text-center my-3'>No films currently showing.</div>";
            
        } else {

            $settings = array();

            // Build film list.
            $films = $cinema->buildFilmList($filmData, $settings);
        
        }

    }

    // RENDER VIEW //
    return $response = $this->view->render($response, "whats-on.phtml", [
        "_title" => "What's-on",
        "films" => $films,
        "banner" => $cinema->buildPromoBanner(),
        "social_distancing" => (($cinema->getConfigItem("social_distancing")["value"] == 1) ? true : false)
    ]);

});

// RUN APP //
$app->run();
