<?php

$app->group("/booking", function(){

    
    // AJAX CALLS FOR FRONTEND //
    
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
    
    $this->get("/view/{bookingId}", function($request, $response, $args) {




    });

    $this->get("/new/{film}/{show}", function($request, $response, $args) {

        $cinema = $this->get("cinema");
        $user = $this->get("user");
        
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
    
});