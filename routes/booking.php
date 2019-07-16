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

    $this->get("/new/tickets", function($request, $response, $args) {

        return $response = $this->view->render($response, "/booking/ticket_selection.phtml", ["_title" => "test tickets"]);

    });

    $this->get("/new/db", function($request, $response, $args) {

        $conn = $this->get("db");

        $r = $conn->query("SELECT * FROM gfc_films")->fetchAll();

        print "<pre>"; print_r($r); print "</pre>";

        $conn->close();

        exit;

    });

    $this->get("/new/seats", function($request, $response, $args) {

        $alpha = range("A", "Z");
        $count = 0;
        $html = "";

        while($count < 8) {

            $secondary = 0;
            $letter = $alpha[$count];


            $html .= "<tr class='screen-row'>";

                while($secondary < 10) {

                    $value = ((rand(0,1) == 1) ? "GREEN" : "GREY");

                    $html .= "<td class='screen-seat seat-standard'>";

                        $html .= "<img src='https://www.jack-roe.co.uk/static/sales/images/seats/1-seat_" . $value . ".png'/>";

                        $html .= "<br/>";

                        $html .= $letter . ($secondary + 1);

                    $html .= "</td>";

                    $secondary++;

                }

            $html .= "</tr>";

            $count++;
        }

        return $response = $this->view->render($response, "/booking/seat_selection.phtml", ["_title" => "test seats", "seats" => $html]);


    });



});