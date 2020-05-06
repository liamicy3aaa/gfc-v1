<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

// Error reporting status //
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
///////////////////

$app->get('/', function (Request $request, Response $response, array $args) {

    $cinema = $this->get("cinema");
    $r = $cinema->getShowtimes(); //getFilmsByReleaseDate(((isset($_GET["mon"])) ? $_GET["mon"]: 8), 2019);

    if($r === false) {

        $films = "<div class='alert alert-info text-center my-3'>No films currently showing.</div>";

    } else {

        // Get films using the filmIds

        $films = $cinema->getFilms($r["_films"]);

        $filmInfo = array();
        $filmData = array();

        foreach($films as $item => $film) {

            if($film["film_status"] == 1) {

                $item = $film;
                $item["showtimes"] = $r[$film["id"]];

                $filmInfo[$film["id"]] = $item;

            }

        }

        foreach (array_unique($r["_films"]) as $id => $film) {
            
            if(isset($filmInfo[$film]["film_status"]) && $filmInfo[$film]["film_status"] == 1) {

            $filmData[] = $filmInfo[$film];
            
            }

        }

        unset($r["_films"]);
        
        if(empty($filmData)) {
            
            $films = "<div class='alert alert-info text-center my-3'>No films currently showing.</div>";
            
        } else {


        $films = $cinema->buildFilmList($filmData);
        
        }

    }

    //return $response = $this->view->render($response, "blank.phtml", ["_title" => "blank", "html" => $html]);


    return $response = $this->view->render($response, "whats-on.phtml", [
        "_title" => "What's-on",
        "films" => $films,
        "banner" => $cinema->buildPromoBanner()
    ]);

});

$app->run();
