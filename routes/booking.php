<?php

$app->group("/booking", function(){


    $this->get("/view/{bookingId}", function($request, $response, $args) {




    });


    $this->get("/new/{film}/{showtime}", function($request, $response, $args) {

            $html = "";
            $html .= "Film title:&nbsp;" . str_replace("-", " ", $args["film"]) . "<br/>";
            $html .= "Film Time:&nbsp;" . date("d-m-Y H:i:s", $args["showtime"]) . "<br/>";

            $response->write($html);

            return $response;

    });



});