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
///////////////////

$app->get('/', function (Request $request, Response $response, array $args) {

    return $response = $this->view->render($response, "whats-on.phtml", ["_title" => "What's-on"]);

});

$app->run();
