<?php

$app->group("/booking", function(){

    
    // AJAX CALLS FOR FRONTEND //

    $this->post("/ajax/cea/validate", function($request, $response, $args){

        $body = $request->getParsedBody();
        $auth = $request->getCookieParam('CEAAUTH');


        /*if(strlen($auth) < 1 || time() > cipher::decrypt($auth) || $auth !== $_SESSION["_CEAAUTH"]) {

            return $response->withJson(array(
                "error" => "unauthorised",
                "error_desc" => "401 Unauthorised"
            ), 401);

        }*/

        if(!isset($body["card"])) {

            return $response->withJson(array(
                "error" => "missing_data",
                "error_desc" => "Card number missing from data."
            ), 400);

        } elseif(!ctype_alnum($body["card"])){

            return $response->withJson(array(
                "error" => "invalid_data",
                "error_desc" => "Card number not a valid format.",
            ), 400);

        }

        if($body["card"] == "111111111") {

            $code = cipher::encrypt($body["card"]);
            $_SESSION["_booking"]["CEA_VALIDATE"] = $code;

            return $response->withJson(array(
                "status" => "ACTIVE",
                "token" => $code
            ), 200);

        } else {

            return $response->withJson(array(
                "status" => "EXPIRED"
            ), 200);

        }

    });
    
    $this->post("/ajax/cancel/{bookingId}", function($request, $response, $args) {
        
        $bookingId = cipher::decrypt($args["bookingId"]);
        
        if(!ctype_alnum($bookingId)) {
            
            return $response->withJson(array("status"=>400, "error"=>"invalid_booking_id", "error_desc"=>"Invalid booking id provided"), 200);
            
        }
        
        $cinema = $this->get("cinema");
        
        if($cinema->deleteBooking($bookingId)) {
        
            return $response->withJson(array("status"=>200));
            
        }
        
        
    });

    $this->post("/ajax/v2/cancel/{bookingId}", function($request, $response, $args) {

        $bookingId = cipher::decrypt($args["bookingId"]);

        if(!ctype_alnum($bookingId)) {

            return $response->withJson(array("status"=>400, "error"=>"invalid_booking_id", "error_desc"=>"Invalid booking id provided"), 200);

        }

        $cinema = $this->get("cinema");

        if($cinema->deleteBooking($bookingId)) {

            return $response->withJson(array("status"=>200));

        }


    });

    $this->post("/ajax/v2/new/{showId}", function($request, $response, $args) {

        $cinema = $this->get("cinema");
        $showId = cipher::decrypt($args["showId"]);
        
        // Getting show info
        $showInfo = $cinema->getShowInfo($showId);
        
        // If no show is found, return error
        if(count($showInfo) < 1) {
            
            return $response->withJson(array("status"=>400, "error"=>"invalid_showId", "error_desc"=>"Invalid show id provided."), 400);
            
        }
        
        // Checking we have been provided array of selected tickets
        $body = $request->getParsedBody();
        
        if(!isset($body["tickets"])) {
            
           return $response->withJson(array("status"=>400, "error"=>"missing_ticketConfig", "error_desc"=>"No ticket config was provided."), 400); 
            
        }
        
        
        
        // Get seat info for each ticket type
        $types = $cinema->getTicketInfo($showInfo["ticket_config"]["types"]);
        
        // Storing the selected tickets in the session temporarily
        $_SESSION["_booking"]["show"] = $showId;
        $_SESSION["_booking"]["tickets"] = array();
        $_SESSION["_booking"]["ticketCount"] = 0;


        foreach($body["tickets"] as $id => $count) {
            
            $type = cipher::decrypt($id);
            $_SESSION["_booking"]["tickets"][$type] = $count;
            
            $seats = json_decode($types[$type]["ticket_config"], true)["seats"];
            
            $_SESSION["_booking"]["ticketCount"] += ($seats * $count);
            
        }
        
        // If ticket count is less than 1, then return error prompting user to select at least one ticket
        if($_SESSION["_booking"]["ticketCount"] < 1) {
            
            return $response->withJson(array("status"=>400, "error"=>"invalidTicketTotal", "error_desc"=>"At least one ticket must be selected."), 400);
            
        }

        //CEA CHECK
        $isCEA = false;

        //print "<pre>"; print_r($types); print "</pre>";
        //exit;

        foreach($types as $id => $type) {

            // CEA
            if($type["cea_free"] == 1 && $_SESSION["_booking"]["tickets"][$id] >= 1) {
                $isCEA = true;
                break;

            }

        }

        if($isCEA) {

            if(!isset($body["CEA_VALIDATE"]) || $body["CEA_VALIDATE"] !== $_SESSION["_booking"]["CEA_VALIDATE"]) {

                return $response->withJson(array(
                    "status"=>400,
                    "error"=>"invalid_CEA_number",
                    "error_desc"=>"Invalid CEA number provided."
                ), 400);


            }

        }

        // Building seating plan with the show id and ticket count
        $seating = $cinema->buildSeatingPlan($showId, $_SESSION["_booking"]["ticketCount"]);
        
        if($seating === false) {
            
            return $response->withJson(array("status"=>400, "error"=>"notEnoughSeats", "error_desc"=>"The number of tickets you requested is higher than the remaining available tickets"), 400);
            
        }

        $html = "<div class='card'>";
        $html .= "<div class='card-header'>";
        $html .= "<p>Please select seats for:</p>";
        $html .= "<p style='font-weight:bold;'>" . $showInfo["film_name"]. " (" . $showInfo["film_rating"] . ") at " . date("H:i", $showInfo["time"]) . " on " . date("l jS F", $showInfo["time"]) . " showing in screen " . $showInfo["screen_name"] . "</p>";
        $html .= "<p>Remaining seats to select: <span class='font-weight-bold seats-remaining'>" . $seating["required"] . "</span></p>";
        $html .= "</div>";
        $html .= "<div class='card-body'>";
        $html .= "<div id='step2Error' class='alert alert-danger text-center' style='display:none;'></div>";
        $html .= "<div class='screen-container text-center p-0 mx-auto m-0 m-md-3'>";
        $html .= "<table class='screen mx-auto'>";
        $html .= "<tbody>" . $seating["html"] . "</tbody>";
        $html .= "</table>";
        $html .= "</div>";
        $html .= "</div></div>";

        $seating["html"] = $html;

        // Return the seating plan to front-end
        return $response->withJson(array("status"=>200, "seating"=>$seating), 200);      
        
    });

    $this->post("/ajax/v2/seating/{showId}", function($request, $response, $args){

        $cinema = $this->get("cinema");
        $showId = cipher::decrypt($args["showId"]);

        // Getting show info
        $showInfo = $cinema->getShowInfo($showId);

        // If no show is found, return error
        if(count($showInfo) < 1) {

            return $response->withJson(array("status"=>400, "error"=>"invalid_showId", "error_desc"=>"Invalid show id provided."), 400);

        }

        // Checking we have been provided array of selected tickets
        $body = $request->getParsedBody();

        if(!isset($body["seats"])) {

            return $response->withJson(array("status"=>400, "error"=>"missing_ticketConfig", "error_desc"=>"No ticket config was provided."), 400);

        }

        // Checking number of seats provided matches selected tickets
        if(count($body["seats"]) !== $_SESSION["_booking"]["ticketCount"]) {

            return $response->withJson(array("status"=>400, "error"=>"invalid_seatSelection", "error_desc"=>"Number of seats selected does not match number of tickets selected."), 400);

        }

        $ticketInfo = $cinema->getTicketInfo($showInfo["ticket_config"]["types"]);

        // Get total price of tickets
        $total = 0;

        foreach($_SESSION["_booking"]["tickets"] as $ticketId => $number) {

            $cost = $ticketInfo[$ticketId]["ticket_cost"];

            $total += ($cost * $number);

        }

        $seats = array();

        foreach($body["seats"] as $seat) {

            $seats[] = cipher::decrypt($seat);

        }

        // Creating the booking with a status of reserved_temp so seats are reserved while they enter their details
        $data = array(
            "showtime_id" => $showId,
            "film_id" => $showInfo["film_id"],
            "booking_info" => $_SESSION["_booking"]["tickets"],
            "booking_seats" => $seats,
            "booking_total" => $total,
            "booking_seats_total" => count($seats),
            "booking_status" => "reserved_temp",
            "booking_method" => "online",
            "cea_card" => ((isset($_SESSION["_booking"]["CEA_VALIDATE"]) && $_SESSION["_booking"]["CEA_VALIDATE"] >= 1) ? cipher::decrypt($_SESSION["_booking"]["CEA_VALIDATE"]) : "")
        );

        $booking = $cinema->createBooking($data);

        if($booking["status"] === false) {

            return $response->withJson(array("status"=>500, "error"=>$booking["error"]), 500);

        } else {

            // Generate details screen
            $details = $cinema->buildDetailsScreen($showId, $booking["reference"]);

            $html = "<div class='card'>";
            $html .= "<div class='card-header text-center'>";
            $html .= "<p class='h4'>Your details</p>";
            $html .= "</div>";
            $html .= "<div class='card-body'>";
            $html .= "<div id='step3Error' class='alert alert-danger text-center' style='display:none;'></div>";
            $html .= $details;
            $html .= "</div></div>";
            $html .= "<div class='card-body col-sm-8 mx-auto'>";
            $html .= "<div class='alert alert-warning alert-dismissable container-fluid'>";
            $html .= "<div class='col-sm-12'><p class='d-inline'>By completing this booking, I confirm that I have read and accepted the terms and conditions and privacy policy.</p>";
            $html .= "<button class='close'><input class='form-control d-inline float-right' type='checkbox'></button>";
            $html .= "</div></div></div>";

            return $response->withJson(array("status"=>200, "bookingId"=>cipher::encrypt($booking["reference"]), "details"=>$html), 200);

        }

    });

    $this->post("/ajax/v2/details/{bookingId}", function($request, $response, $args){

        $cinema = $this->get("cinema");
        $bookingId = cipher::decrypt($args["bookingId"]);

        // Check booking exists
        if(!$cinema->bookingExists($bookingId)) {

            return $response->withJson(array("status" => 400, "error" => "invalid_booking_id", "error" => "Unable to find booking."), 400);

        }


        $body = $request->getParsedBody();

        $required = array("name", "phone", "email");

        foreach($required as $item) {

            if(!isset($body[$item])) {

                return $response->withJson(array("status"=>400, "error"=>"missing_data", "error_desc"=>"$item is missing from the data array."), 400);

            } else {

                $error = false;

                // Check data is clean
                switch($item) {

                    case "name":
                        if(!preg_match('/^[a-zA-Z ]+$/', $body["name"])) {

                            $error = "Invalid name";

                        };
                        break;

                    case "phone":
                        if(!ctype_digit($body["phone"])) {

                            $error = "Invalid phone number";

                        };
                        break;

                    case "email":
                        if(!filter_var($body["email"], FILTER_VALIDATE_EMAIL)) {

                            $error = "Invalid email";

                        };
                        break;

                }

                if($error !== false) {

                    return $response->withJson(array("status"=>400, "error"=>"invalid_parameter", "error_desc"=>$error), 400);

                }


            }

        }

        // All parameters are checked and exist
        // Update booking
        $data = array(
            "booking_name" => $body["name"],
            "booking_email" => $body["email"],
            "booking_phone" => $body["phone"],
            "booking_status" => "reserved",
            "booking_method" => "online"
        );

        $booking = $cinema->updateBooking($bookingId, $data);

        if(!$booking) {

            return $response->withJson(array("error" => 500, "error" => "server_error", "error" => "Error occurred while trying to update booking."),400);

        } else {

            $booking_id = $cinema->getBookingInfo($bookingId);

            // Send confirmation email
            //$cinema->sendBookingConfirmation($bookingId);

            $confirmation = $cinema->buildConfirmationScreen($booking_id["showtime_id"], $bookingId);

            $confirmationHtml = "<h1>Booking confirmed</h1><br/><div class='card mt-2'>" . $confirmation . "</div>";

            $html = "<div id='paymentWindow' class='card mt-2'></div>";


            return $response->withJson(array("status" => 200, "confirmation" => $confirmationHtml, "html" => $html), 200);

        }


    });


    $this->post("/ajax/new/{showId}", function($request, $response, $args) {

        $cinema = $this->get("cinema");
        $showId = cipher::decrypt($args["showId"]);

        // Getting show info
        $showInfo = $cinema->getShowInfo($showId);

        // If no show is found, return error
        if(count($showInfo) < 1) {

            return $response->withJson(array("status"=>400, "error"=>"invalid_showId", "error_desc"=>"Invalid show id provided."), 400);

        }

        // Checking we have been provided array of selected tickets
        $body = $request->getParsedBody();

        if(!isset($body["tickets"])) {

            return $response->withJson(array("status"=>400, "error"=>"missing_ticketConfig", "error_desc"=>"No ticket config was provided."), 400);

        }



        // Get seat info for each ticket type
        $types = $cinema->getTicketInfo($showInfo["ticket_config"]["types"]);

        // Storing the selected tickets in the session temporarily
        $_SESSION["_booking"]["show"] = $showId;
        $_SESSION["_booking"]["tickets"] = array();
        $_SESSION["_booking"]["ticketCount"] = 0;

        foreach($body["tickets"] as $id => $count) {

            $type = cipher::decrypt($id);
            $_SESSION["_booking"]["tickets"][$type] = $count;

            $seats = json_decode($types[$type]["ticket_config"], true)["seats"];

            $_SESSION["_booking"]["ticketCount"] += ($seats * $count);

        }

        // If ticket count is less than 1, then return error prompting user to select at least one ticket
        if($_SESSION["_booking"]["ticketCount"] < 1) {

            return $response->withJson(array("status"=>400, "error"=>"invalidTicketTotal", "error_desc"=>"At least one ticket must be selected."), 400);

        }

        // Building seating plan with the show id and ticket count
        $seating = $cinema->buildSeatingPlan($showId, $_SESSION["_booking"]["ticketCount"]);

        if($seating === false) {

            return $response->withJson(array("status"=>400, "error"=>"notEnoughSeats", "error_desc"=>"The number of tickets you requested is higher than the remaining available tickets"), 400);

        }

        // Return the seating plan to front-end
        return $response->withJson(array("status"=>200, "seating"=>$seating), 200);

    });

    // STANDARD ENDPOINTS FOR PAGES //

    $this->post("/ajax/seating/{showId}", function($request, $response, $args){
        
        $cinema = $this->get("cinema");
        $showId = cipher::decrypt($args["showId"]);
        
        // Getting show info
        $showInfo = $cinema->getShowInfo($showId);
        
        // If no show is found, return error
        if(count($showInfo) < 1) {
            
            return $response->withJson(array("status"=>400, "error"=>"invalid_showId", "error_desc"=>"Invalid show id provided."), 400);
            
        }
        
        // Checking we have been provided array of selected tickets
        $body = $request->getParsedBody();
        
        if(!isset($body["seats"])) {
            
           return $response->withJson(array("status"=>400, "error"=>"missing_ticketConfig", "error_desc"=>"No ticket config was provided."), 400); 
            
        }
        
        // Checking number of seats provided matches selected tickets
        if(count($body["seats"]) !== $_SESSION["_booking"]["ticketCount"]) {
            
             return $response->withJson(array("status"=>400, "error"=>"invalid_seatSelection", "error_desc"=>"Number of seats selected does not match number of tickets selected."), 400);    
            
        }
        
        $ticketInfo = $cinema->getTicketInfo($showInfo["ticket_config"]["types"]);
        
        // Get total price of tickets
        $total = 0;
        
        foreach($_SESSION["_booking"]["tickets"] as $ticketId => $number) {
            
            $cost = $ticketInfo[$ticketId]["ticket_cost"];
            
            $total += ($cost * $number);        
            
        }
        
        $seats = array();
        
        foreach($body["seats"] as $seat) {
            
            $seats[] = cipher::decrypt($seat);
            
        }        
        
        // Creating the booking with a status of reserved_temp so seats are reserved while they enter their details
        $data = array(
            "showtime_id" => $showId,
            "film_id" => $showInfo["film_id"],
            "booking_info" => $_SESSION["_booking"]["tickets"],
            "booking_seats" => $seats,
            "booking_total" => $total,
            "booking_seats_total" => count($seats),
            "booking_status" => "reserved_temp"     
        );
        
        $booking = $cinema->createBooking($data);   
        
        if($booking["status"] === false) {
            
            return $response->withJson(array("status"=>500, "error"=>$booking["error"]), 500);
            
        } else {
            
            // Generate details screen
            $details = $cinema->buildDetailsScreen($showId, $booking["reference"]);
            
            return $response->withJson(array("status"=>200, "bookingId"=>cipher::encrypt($booking["reference"]), "details"=>$details), 200);
            
        }
        
    });
    
    $this->post("/ajax/details/{bookingId}", function($request, $response, $args){
        
              $cinema = $this->get("cinema");
              $bookingId = cipher::decrypt($args["bookingId"]);
              
              // Check booking exists
              if(!$cinema->bookingExists($bookingId)) {
                  
                  return $response->withJson(array("status" => 400, "error" => "invalid_booking_id", "error" => "Unable to find booking."), 400);
                  
              }
              
              
              $body = $request->getParsedBody();
              
              $required = array("name", "phone", "email");
              
              foreach($required as $item) {
                  
                  if(!isset($body[$item])) {
                      
                      return $response->withJson(array("status"=>400, "error"=>"missing_data", "error_desc"=>"$item is missing from the data array."), 400);
                      
                  } else {
                      
                      $error = false;
                   
                        // Check data is clean
                        switch($item) {
                            
                            case "name": 
                                if(!preg_match('/^[a-zA-Z ]+$/', $body["name"])) {
                                    
                                    $error = "Invalid name";
                                    
                                };
                                break;
                                
                            case "phone":
                               if(!ctype_digit($body["phone"])) {
                                    
                                    $error = "Invalid phone number";
                                    
                                };
                                break;
                                
                            case "email":
                               if(!filter_var($body["email"], FILTER_VALIDATE_EMAIL)) {
                                    
                                    $error = "Invalid email";
                                    
                                };
                                break;    
                                       
                        }
                        
                        if($error !== false) {
                            
                            return $response->withJson(array("status"=>400, "error"=>"invalid_parameter", "error_desc"=>$error), 400);
                            
                        }   
                      
                      
                  }
                  
              }
              
              // All parameters are checked and exist
              // Update booking
              $data = array(
                "booking_name" => $body["name"],
                "booking_email" => $body["email"],
                "booking_phone" => $body["phone"],
                "booking_status" => "reserved",
                "booking_method" => "online"
              );
              
              $booking = $cinema->updateBooking($bookingId, $data);
              
              if(!$booking) {
                  
                  return $response->withJson(array("error" => 500, "error" => "server_error", "error" => "Error occurred while trying to update booking."),400);
                  
              } else {
                  
                  $booking_id = $cinema->getBookingInfo($bookingId);

                  // Send confirmation email
                  //$cinema->sendBookingConfirmation($bookingId);

                  $html = $cinema->buildConfirmationScreen($booking_id["showtime_id"], $bookingId);
                  
                  return $response->withJson(array("status" => 200, "confirmation" => $html), 200);
                  
              }
              
        
    });
    
    $this->map(['GET', 'POST'], "/view", function($request, $response, $args) {

        if($request->isPost()) {

            $user = $this->get("user");
            $cinema = $this->get("cinema");
            $body = $request->getParsedBody();
            $required = array("bookingId", "bookingEmail");

            $res = "";
            foreach($required as $item) {

                if(!array_key_exists($item, $body) || strlen($body[$item]) < 3) {

                    notifications::add("danger", "Please provide a $item. [" . $body[$item] . "]");
                    $user->redirect("/booking/view");
                    break;
                }

            }

            // Checking for booking
            $booking = $cinema->getBookingInfo($body["bookingId"]);

            if(!$booking) {

                notifications::add("danger", "No booking found with provided id.");

                $user->redirect("/booking/view");

            } elseif($booking["booking_email"] !== $body["bookingEmail"]) {

                notifications::add("danger", "Email provided does not match email on booking.");

                $user->redirect("/booking/view");

            }



            return $response->withJson(array("data" => $booking), 200);


        } else {

            return $response = $this->view->render($response, "/booking/view.phtml", [
                "_title" => "View Booking"
            ]);

        }


    });

    $this->get("/new/{film}/{show}", function($request, $response, $args) {

        $cinema = $this->get("cinema");
        $user = $this->get("user");

        notifications::add("danger", "Invalid booking URL.");
        $user->redirect("/");
        
        $showInfo = $cinema->getShowInfo(cipher::decrypt($args["show"]));

        if($showInfo === false) {

            notifications::add("danger", "An error occurred while trying to access this showing. Please try again later.");
            $user->redirect("/film/" . $args["film"] . "");

        }
        
        if(time() > $showInfo["time"]) {
            
            notifications::add("danger", "Ticket sales for this showing have finished.");
            $user->redirect("/film/" . cipher::encrypt($showInfo["film_id"]) . "");
            
        }
        
        if($showInfo["film_status"] !== 1) {
            
            notifications::add("danger", "Ticket sales for this showing have been suspended", array("dismiss"=>false));
            $user->redirect("/");
            
        }
        
        // Build ticket screen
        $tickets = $cinema->buildTicketScreen($showInfo["ticket_config"]["types"]);
        
        // Build ticket info array for Cinema JS Class
        $ticketInfo = $cinema->getTicketTypes($showInfo["ticket_config"]["types"]);
        $ticketConfig = array();
        
        foreach($ticketInfo as $id => $ticket) {
            
            $ticketConfig[cipher::encrypt($ticket["id"])] = array(
                "cost" => $ticket["ticket_cost"],
                "count" => 0            
            );
            
        }
        
        // Getting number of available seats / tickets
        $available = $cinema->availableSeats($showInfo["showId"]);

        // If show is fully booked, display error
        if($available["available"] < 1) {
            
            notifications::add("danger", "There are no more seats available for this show.");
            $user->redirect("/film/" . cipher::encrypt($showInfo["film_id"]) . "");
            
        }
        
        $showInfo["showId"] = cipher::encrypt($showInfo["showId"]);
            
            return $response = $this->view->render($response, "/booking/booking.phtml", [
                "_title" => "New Booking",
                "show" => $showInfo,
                "tickets" => $tickets,
                "ticketConfig" => json_encode($ticketConfig, true),
                "availableTickets" => $available["available"]
                ]);

            return $response;

    });

    $this->get("/new/db", function($request, $response, $args) {

        if(isset($_GET["string"])) {
            $val = $_GET["string"];
            $val++;

            die("VAL:" . $val);

        }

        exit;

        $cinema = $this->get("cinema");

        print "/ajax/films/" . cipher::encrypt(13) . "/showtimes/" . cipher::encrypt(12) . "/" . cipher::encrypt(3) . "";
        exit;

        /*

            $show = $cinema->getBookingTicketInfo("7WZ9CNAN");
        
        print "<pre>"; print_r($show); print "</pre>";

        exit;*/

        return $response->WithJson($cinema->getBookingBySeat(12, 3), 200);

    });

    $this->get("/v2/new/{show}", function($request, $response, $args){

        $cinema = $this->get("cinema");
        $user = $this->get("user");

        $showInfo = $cinema->getShowInfo(cipher::decrypt($args["show"]));

        if($showInfo === false) {

            notifications::add("danger", "An error occurred while trying to access this showing. Please try again later.");
            $user->redirect("/");

        }

        if(time() > $showInfo["time"]) {

            notifications::add("danger", "Ticket sales for this showing have finished.");
            $user->redirect("/film/" . cipher::encrypt($showInfo["film_id"]) . "");

        }

        if($showInfo["film_status"] !== 1) {

            notifications::add("danger", "Ticket sales for this showing have been suspended", array("dismiss"=>false));
            $user->redirect("/");

        }

        // Build ticket screen
        $tickets = $cinema->buildTicketScreen($showInfo["ticket_config"]["types"]);

        // Build ticket info array for Cinema JS Class
        $ticketInfo = $cinema->getTicketTypes($showInfo["ticket_config"]["types"]);
        $ticketConfig = array();
        $x = 0;

        foreach($ticketInfo as $id => $ticket) {

            $ticketConfig[cipher::encrypt($ticket["id"])] = array(
                "cost" => $ticket["ticket_cost"],
                "count" => 0,
                "cea_free" => $ticket["cea_free"],
                "cea_full" => $ticket["cea_full"]
            );
        $x++;
        }

        // Getting number of available seats / tickets
        $available = $cinema->availableSeats($showInfo["showId"]);

        // If show is fully booked, display error
        if($available["available"] < 1) {

            notifications::add("danger", "This show is sold out. Please choose an alternative showing.");
            $user->redirect("/film/" . cipher::encrypt($showInfo["film_id"]) . "");

        }

        $showInfo["showId"] = cipher::encrypt($showInfo["showId"]);

        //$html = "<div id='stepperContainer' class='container container-fluid my-4' style='margin-top:150px !important;'></div>";


        $html = "<div class='card'>";
            $html .= "<div class='card-header'>";
                $html .= "<p>Please select tickets for:</p>";
                $html .= "<p style='font-weight:bold;'>" . $showInfo["film_name"]. " (" . $showInfo["film_rating"] . ") at " . date("H:i", $showInfo["time"]) . " on " . date("l jS F", $showInfo["time"]) . " showing in screen " . $showInfo["screen_name"] . "</p>";
                $html .= "<p>Available seats: <span class='text-bold'>" . $available["available"] . "</span></p>";
            $html .= "</div>";
            $html .= "<div class='card-body'>";
            $html .= "<div id='step1Error' class='alert alert-danger text-center' style='display:none;'></div>";
                $html .= "<div class='p-0 mx-auto m-0 m-md-1'>";
                    $html .= "<table class='table'>";
                    $html .= "<thead>";
                        $html .= "<th>Selection</th>";
                        $html .= "<th class='text-right'>Price</th>";
                        $html .= "<th>Qty</th>";
                        $html .= "<th class='d-none d-sm-table-cell text-right'>Subtotal</th>";
                    $html .= "</thead>";
                    $html .= "<tbody>" . $tickets . "</tbody>";
                $html .= "</table>";
            $html .= "</div>";
            $html .= "<div class='card-body'>";
                $html .= "<strong><span id='selectedTicketsTotal'>2</span> tickets selected at a total cost of &pound;<span id='selectedTicketsCost'>14.00</span></strong>";
            $html .= "</div></div></div>";

            //Creating CEA AUTH
            $_SESSION["_CEAAUTH"] = cipher::encrypt(time()+900);
            $token = $_SESSION["_CEAAUTH"];

                $setcookies = new Slim\Http\Cookies();
                $setcookies->set("CEAAUTH", ['value' => $token, 'expires' => time() + 900, 'path' => '/','domain' => "",'httponly' => true]);
                $response = $response->withHeader('Set-Cookie', $setcookies->toHeaders());


        return $response = $this->view->render($response, "/booking/booking2.phtml", [
            "_title" => "New Booking",
            "html" => $html,
            "show" => $showInfo,
            "ticketConfig" => json_encode($ticketConfig, true),
            "availableTickets" => $available["available"]
        ]);

    });
});