<?php

$app->group("/booking", function(){

    
    // AJAX CALLS FOR FRONTEND //
    
    $this->post("/ajax/cancel/{bookingId}", function($request, $response, $args) {
        
        if(!ctype_alnum($args["bookingId"])) {
            
            return $response->withJson(array("status"=>400, "error"=>"invalid_booking_id", "error_desc"=>"Invalid booking id provided"), 200);
            
        }
        
        $cinema = $this->get("cinema");
        
        if($cinema->cancelBooking($args["bookingId"])) {
        
            return $response->withJson(array("status"=>200));
            
        }
        
        
    });
    
    $this->post("/ajax/new/{showId:[0-9]+}", function($request, $response, $args) {
        
        $cinema = $this->get("cinema");
        
        // Getting show info
        $showInfo = $cinema->getShowInfo($args["showId"]);
        
        // If no show is found, return error
        if(count($showInfo) < 1) {
            
            return $response->withJson(array("status"=>400, "error"=>"invalid_showId", "error_desc"=>"Invalid show id provided."), 400);
            
        }
        
        // Checking we have been provided array of selected tickets
        $body = $request->getParsedBody();
        
        if(!isset($body["tickets"])) {
            
           return $response->withJson(array("status"=>400, "error"=>"missing_ticketConfig", "error_desc"=>"No ticket config was provided."), 400); 
            
        }
        
        // Storing the selected tickets in the session temporarily
        $_SESSION["_booking"]["show"] = $args["showId"];
        $_SESSION["_booking"]["tickets"] = $body["tickets"];
        $_SESSION["_booking"]["ticketCount"] = 0;
        
        foreach($body["tickets"] as $id => $count) {
            
            $_SESSION["_booking"]["ticketCount"] += $count;
            
        }
        
        // If ticket count is less than 1, then return error prompting user to select at least one ticket
        if($_SESSION["_booking"]["ticketCount"] < 1) {
            
            return $response->withJson(array("status"=>400, "error"=>"invalidTicketTotal", "error_desc"=>"At least one ticket must be selected."), 400);
            
        }
        
        // Building seating plan with the show id and ticket count
        $seating = $cinema->buildSeatingPlan($args["showId"], $_SESSION["_booking"]["ticketCount"]);
        
        // Return the seating plan to front-end
        return $response->withJson(array("status"=>200, "seating"=>$seating), 200);      
        
    });
    
    // STANDARD ENDPOINTS FOR PAGES //

    $this->post("/ajax/seating/{showId:[0-9]+}", function($request, $response, $args){
        
        $cinema = $this->get("cinema");
        
        // Getting show info
        $showInfo = $cinema->getShowInfo($args["showId"]);
        
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
        
        //print "<pre>"; print_r($ticketInfo); print "</pre>";
       //exit;
        // Get total price of tickets
        $total = 0;
        
        foreach($_SESSION["_booking"]["tickets"] as $ticketId => $number) {
            
            $cost = $ticketInfo[$ticketId]["ticket_cost"];
            
            $total += ($cost * $number);        
            
        }
        
        // Creating the booking with a status of reserved_temp so seats are reserved while they enter their details
        $data = array(
            "showtime_id" => $args["showId"],
            "film_id" => $showInfo["film_id"],
            "booking_info" => $_SESSION["_booking"]["tickets"],
            "booking_seats" => $body["seats"],
            "booking_total" => $total,
            "booking_seats_total" => count($body["seats"]),
            "booking_status" => "reserved_temp"     
        );
        
        $booking = $cinema->createBooking($data);   
        
        if($booking["status"] === false) {
            
            return $response->withJson(array("status"=>500, "error"=>$booking["error"]), 500);
            
        } else {
            
            // Generate details screen
            $details = $cinema->buildDetailsScreen($args["showId"], $booking["reference"]);
            
            return $response->withJson(array("status"=>200, "bookingId"=>$booking["reference"], "details"=>$details), 200);
            
        }
        
    });
    
    $this->post("/ajax/details/{bookingId}", function($request, $response, $args){
        
              $cinema = $this->get("cinema");
              
              // Check booking exists
              if(!$cinema->bookingExists($args["bookingId"])) {
                  
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
                "booking_status" => "reserved"
              );
              
              $booking = $cinema->updateBooking($args["bookingId"], $data);
              
              if(!$booking) {
                  
                  return $response->withJson(array("error" => 500, "error" => "server_error", "error" => "Error occurred while trying to update booking."),400);
                  
              } else {
                  
                  return $response->withJson(array("status"=>200),200);
                  
              }
              
        
    });
    
    $this->get("/view/{bookingId}", function($request, $response, $args) {




    });

    $this->get("/new/{film}/{show}", function($request, $response, $args) {
        
        $cinema = $this->get("cinema");
        
        $showInfo = $cinema->getShowInfo($args["show"]);
        
        if(time() > $showInfo["time"]) {
            
            die("Can't make a booking for a show that is in the past.");
            
        }
        
        // Build ticket screen
        $tickets = $cinema->buildTicketScreen($showInfo["ticket_config"]["types"]);

        // Temporary build details screen
        $details = $cinema->buildDetailsScreen($showInfo["showId"], "TRT34D");
        
        // Getting number of available seats / tickets
        $available = $cinema->availableSeats($showInfo["showId"]);
            
            return $response = $this->view->render($response, "/booking/booking.phtml", [
                "_title" => "New Booking",
                "show" => $showInfo,
                "tickets" => $tickets,
                "details" => $details,
                "availableTickets" => $available["available"]
                ]);

            return $response;

    });

    $this->get("/new/tickets", function($request, $response, $args) {
        
        $cinema = $this->get("cinema");

        $tickets = $cinema->buildTicketScreen([1,3,4]);
        
        return $response = $this->view->render($response, "/booking/ticket_selection.phtml", [
            "_title" => "test tickets",
            "tickets" => $tickets
            ]);

    });
    
    $this->get("/new/details", function($request, $response, $args) {

        return $response = $this->view->render($response, "/booking/details.phtml", ["_title" => "test details"]);

    });

    $this->get("/new/db", function($request, $response, $args) {

        $cinema = $this->get("cinema");

            $show = $cinema->getShowInfo(3);
            $r = $cinema->getTicketInfo($show["ticket_config"]["types"]);
        
        print "<pre>"; print_r($_SESSION); print "</pre>";

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

                    $html .= "<td class='screen-seat seat-standard' data-seatId='" . $letter . ($secondary + 1) . "'>";

                        $html .= "<img src='/assets/images/seats/1-seat_" . $value . ".png'/>";

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