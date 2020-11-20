<?php

$app->group("/Manage", function(){
    
    /** AJAX */

    $this->get("/ajax/tickets/new", function($request, $response, $args) {

        $user = $this->get("user");
        $user->loginRequired();

        return $response->withJson(array("status"=>200, "html"=>file_get_contents("../templates/Manage/tickets/partial_new_ticket.phtml")), 200);

    });

    $this->post("/ajax/tickets/new", function($request, $response, $args) {

        $user = $this->get("user");
        $user->loginRequired();

        $cinema = $this->get("cinema");
        $body = $request->getParsedBody();
        $validChars = array(" ", "'", ".");
        $required = array("ticketLabel", "ticketSeats", "ticketCost");
        $optional = array("ticketProof", "ticketCeaFree", "ticketCeaFull", "ticketActive");
        $dataToTable = array(
            "ticketLabel" => "ticket_label",
            "ticketCost" => "ticket_cost",
            "ticketSeats" => "seats",
            "ticketProof" => "proof",
            "ticketCeaFree" => "cea_free",
            "ticketCeaFull" => "cea_full",
            "ticketActive" => "ticket_status"
        );

        $data = array();

        foreach($required as $item) {


            if (!isset($body[$item])) {

                return $response->withJson(array("status" => 400, "error" => "missing_info", "error_desc" => "$item missing from request."), 400);

            }

            if (strlen($body[$item]) < 1) {

                return $response->withJson(array("status" => 400, "error" => "missing_info", "error_desc" => "$item must be at least 1 character long."), 400);

            }

            if (!ctype_alnum(str_replace($validChars, "", $body[$item]))) {

                return $response->withJson(array("status" => 400, "error" => "invalid_$item", "error_desc" => "Screen name can only contain letters or number."), 400);

            }

            $data[$dataToTable[$item]] = $body[$item];

        }

        foreach($optional as $item) {

            if(isset($body[$item])) {

                if (strlen($body[$item]) < 1) {

                    return $response->withJson(array("status" => 400, "error" => "missing_info", "error_desc" => "$item must be at least 1 character long."), 400);

                }

                if (!ctype_alnum(str_replace($validChars, "", $body[$item]))) {

                    return $response->withJson(array("status" => 400, "error" => "invalid_$item", "error_desc" => "$item can only contain letters or number."), 400);

                }

                $data[$dataToTable[$item]] = $body[$item];

            }

        }

        if(!isset($body["ticketActive"])) {

            $data["ticket_status"] = 0;

        }

        if(isset($body["ticketSeats"])) {

            $data[$dataToTable["ticketSeats"]] = intval($body["ticketSeats"]);

        }

        if(isset($body["ticketProof"])) {

            $data[$dataToTable["ticketProof"]] = intval($body["ticketProof"]);

        }

        $creation = $cinema->createTicket($data);

       // exit;

        if($creation["status"]) {

            notifications::add("success", "Ticket successfully created", array("dismiss"=>false));

            return $response->withJson(array("status"=>200), 200);

        } else {

            return $response->withJson(array("status"=>500, "error" => "An error occurred."), 500);

        }

    });
    
    $this->get("/ajax/screens/new", function($request, $response, $args) {
        
        $user = $this->get("user");
        $user->loginRequired();
        
        return $response->withJson(array("status"=>200, "html"=>file_get_contents("../templates/Manage/screens/partial_new_screen.phtml")), 200);
        
    });
    
    $this->get("/ajax/screens/delete/{id}", function($request, $response, $args){
        
        $user = $this->get("user");
        $cinema = $this->get("cinema");
        $user->loginRequired();
        
        if(!ctype_alnum($args["id"])) {
            
            $response->write("ERROR: INVALID ID");
            
            return $response;
        }
        
        $id = cipher::decrypt($args["id"]);
        
        if($cinema->getShowtimesByScreen($id, false) >= 1) {
            
            notifications::add("danger", "Cannot delete a screen that has one or more active showings");
            header("Location: /Manage/screens");
            exit;
            
        }
        
        if($cinema->deleteScreen($id)) {
            
            notifications::add("success", "Screen successfully deleted.", array("dismiss"=>false));
            header("Location: /Manage/screens");
            exit;
            
        } else {
            
            notifications::add("danger", "An error occurred while trying to delete this screen");
            header("Location: /Manage/screens");
            exit;
            
        }
        
    });
    
    $this->post("/ajax/screens/new", function($request, $response, $args) {
        
        $user = $this->get("user");
        $user->loginRequired();
        
        $cinema = $this->get("cinema");
        $body = $request->getParsedBody();
        $validChars = array(" ", "'"); 
        
        if(!isset($body["screenName"])) {
            
            return $response->withJson(array("status"=>400, "error"=>"missing_info", "error_desc"=>"Screen name missing from request."), 400);
            
        }
        
        if(strlen($body["screenName"]) < 1) {
            
            return $response->withJson(array("status"=>400, "error"=>"missing_info", "error_desc"=>"Screen name must be at least 1 character long."), 400);
            
        }
        
        if(!ctype_alnum(str_replace($validChars, "", $body["screenName"]))) {
            
            return $response->withJson(array("status"=>400, "error"=>"invalid_screenName", "error_desc"=>"Screen name can only contain letters or number."), 400);        
            
        }
        
        $creation = $cinema->createScreen($body["screenName"], 0);
        
        if($creation) {
            
            notifications::add("success", "Screen successfully created", array("dismiss"=>false));
            
            return $response->withJson(array("status"=>200), 200);        
            
        }
        
    });
    
    $this->get("/ajax/screens/seats/{id}", function($request, $response, $args){
        
        $user = $this->get("user");
        $user->loginRequired();
        
        $cinema = $this->get("cinema");
        
        $seatingInfo = $cinema->getSeatingInfo(array(cipher::decrypt($args["id"])));
        
        return $response->withJson(array("status"=>200, "html"=>"<pre>" . print_r($seatingInfo, true) . "</pre>"), 200);
        
        
    });
    
    $this->post("/ajax/screens/{id}/reorder", function($request, $response, $args){
    
        $user = $this->get("user");
        $user->loginRequired();
        $cinema = $this->get("cinema");
        $screen = cipher::decrypt($args["id"]);
        
        // Getting body
        $body = $request->getParsedBody();
        
        // Checking they have provided a valid order array
        if(!isset($body["newOrder"]) || !is_array($body["newOrder"])){
            
            return $response->withJson(array(
                "status" => 400,
                "error" => "invalid_order",
                "error_desc" => "Missing or invalid order array provided."
            ), 400);
            
        }
        
        // Running the update
        $result = $cinema->reorderScreenRows($screen, $body["newOrder"]);
        
        //return $response->withJson(array("status" => "test", "data" => $result), 200);
        
        if(!$result) {
            
            return $response->withJson(array(
                "status" => 500,
                "error" => "server_error",
                "error_desc" => "An error occurred processing the request. Please try again later."
            ), 500);
           
        }
        
        return $response->withJson(array("status" => 200), 200);
    
    });


    /** FILMS */
    
    $this->post("/ajax/films/new", function($request, $response, $args) {
        
        $user = $this->get("user");
        $user->loginRequired();
        
        $cinema = $this->get("cinema");
        $body = $request->getParsedBody();
        $validChars = array(" ", "'", "/", ":", ",", ".", "-");
        $required = array("filmName" => "Film name", "filmDesc" => "Film Description", "filmRelease" => "Film release", "filmRating" => "Film rating", "filmRuntime" => "Film runtime");
        
        foreach($required as $id => $desc) {

            if(!isset($body[$id])) {
                
                return $response->withJson(array("status"=>400, "error"=>"missing_info", "error_desc"=>"$desc missing from request."), 400);
                
            }
            
        
            if(strlen($body[$id]) < 1) {
                
                return $response->withJson(array("status"=>400, "error"=>"missing_info", "error_desc"=>"$desc must be at least 1 character long."), 400);
                
            }
        
            if(!ctype_alnum(str_replace($validChars, "", $body[$id])) && $id !== "filmDesc") {
                
                return $response->withJson(array("status"=>400, "error"=>"invalid_$id", "error_desc"=>"$desc can only contain letters or numbers."), 400);        
                
            }
            
        }
        
        $data = array();
        $data["film_name"] = $body["filmName"];
        $data["film_desc"] = $body["filmDesc"];
        $data["film_release"] = strtotime($body["filmRelease"]);
        $data["film_runtime"] = $body["filmRuntime"];
        $data["film_rating"] = $body["filmRating"];
        
        //print "<pre>"; print_r($data); print "</pre>";
        //exit;
        
        $creation = $cinema->addFilm($data);
        
        if($creation["status"]) {
            
            notifications::add("success", "Film added successfully", array("dismiss"=>false));
            
            return $response->withJson(array(
                "status"=>200,
                "redirect" => "/Manage/films/" . cipher::encrypt($creation["filmId"]) . ""
                ), 200);        
            
        } else {
            
            return $response->withJson(array(
                "status" => 400,
                "error" => $creation["error"],
                "error_desc" => $creation["error_desc"]
            ), 400);
            
        }
        
    });

    $this->get("/ajax/films/{id}/showtimes/{showId}/plan", function($request, $response, $args) {

        $user = $this->get("user");
        $user->loginRequired();
        $cinema = $this->get("cinema");

        $show = cipher::decrypt($args["showId"]);

        $plan = $cinema->buildSeatingPlan($show, 1);

        $html = str_replace(array("%LAYOUT%", "%ID%"), array($plan["html"], $args["id"]),
            file_get_contents("../templates/Manage/films/partial_showtimes_layout.phtml"));

        return $response->WithJson(array("html" => $html), 200);


    });

    $this->get("/ajax/films/{id}/showtimes/{showId}/{seat}", function($request, $response, $args){

        $user = $this->get("user");
        $user->loginRequired();
        $cinema = $this->get("cinema");

        $show = cipher::decrypt($args["showId"]);
        $seat = cipher::decrypt($args["seat"]);

        $booking = $cinema->getBookingBySeat($show, $seat);

        if(!$booking) {

            return $response->WithJson(array("error" => "no_booking", "error_desc"=>"No booking available"), 400);

        } else {

            $bookingUrl = "/Manage/bookings/" . cipher::encrypt($booking["booking_reference"]);

            return $response->WithJson(array("booking" => $bookingUrl), 200);

        }


    });
    
    $this->get("/ajax/films/new", function($request, $response, $args){
        
        $user = $this->get("user");
        $user->loginRequired();
        
        return $response->withJson(array("status"=>200, "html"=>file_get_contents("../templates/Manage/films/partial_new_film.phtml")), 200);
   
    });


    $this->get("/ajax/films/delete/{id}", function($request, $response, $args){

        $user = $this->get("user");
        $user->loginRequired();
        $filmId = cipher::decrypt($args["id"]);

        $cinema = $this->get("cinema");

        $delete = $cinema->deleteFilm($filmId);

        if($delete["status"]) {

            notifications::add("success", "Film deleted");

            return $response->withJson(array("status" => 200), 200);

        } else {

            return $response->withJson(array("status" => 400, "error" => $delete["error"]), 400);

        }


    });

    $this->get("/ajax/films/{id}/upload/{cmd}", function($request, $response, $args) {

        $user = $this->get("user");
        $user->loginRequired();

        /*$html = str_replace(array("{IMAGEPREVIEW}", "{POSTURL}"),
            array("/assets/images/films/1_banner.jpg", "/Manage/ajax/films/" . $args["id"] . "/upload/" . $args["cmd"]),
            file_get_contents("../templates/Manage/films/partial_upload_thumbBanner.phtml"));*/

        $crop = new resizer();
        
        switch($args["cmd"]) {

                    case "thumbnail":
                        $crop->setCropSize(341, 512);
                        $crop->centerResizer();
                        $crop->setUploadUrl("/Manage/ajax/films/" . $args["id"] . "/upload/" . $args["cmd"]);    
                        break;

                    case "banner":
                        $crop->setCropSize(1024, 576);
                        $crop->setUploadUrl("/Manage/ajax/films/" . $args["id"] . "/upload/" . $args["cmd"]);    
                        break;

                }    
            
        return $response->withJson(array("status" => 200, "html" => $crop->build()));

    });

    $this->post("/ajax/films/{id}/upload/{cmd}", function($request, $response, $args){

       $user = $this->get("user");
       $user->loginRequired();


        if(isset($_POST['image']) && strlen($_POST['image']) >= 1) {

            $array = getimagesize($_POST['image']);

            $e = explode("/",$array['mime']);

            if($e[0] == "image") {


                $f = finfo_open();
                $sub = str_replace("data:image/png;base64,", "", $_POST['image']);
                $imgdata = base64_decode($sub);

                $mime_type = finfo_buffer($f, $imgdata, FILEINFO_MIME_TYPE);

                if(!in_array($mime_type, array("image/png", "image/jpeg", "image/gif"))) {

                    http_response_code(400);
                    print json_encode(array("status"=>false, "error"=>"Invalid filetype"));
                    exit;

                }

                switch($args["cmd"]) {

                    case "thumbnail":
                        $fileEndpoint = "assets/images/films/" . cipher::decrypt($args["id"]) . "_thumb.jpg";
                        $column = "film_thumbnail";
                        break;

                    case "banner":
                        $fileEndpoint = "assets/images/films/" . cipher::decrypt($args["id"]) . "_banner.jpg";
                        $column = "film_banner";
                        break;

                }

                if(file_exists($fileEndpoint)) {

                    unlink($fileEndpoint);

                }

                $source = imagecreatefromstring($imgdata);
                $imageSave = imagejpeg($source,$fileEndpoint,100);
                imagedestroy($source);

                // Logging users action //
                $conn = $this->get("db");
                $conn->query("UPDATE gfc_films SET $column = '/$fileEndpoint' WHERE id = " . cipher::decrypt($args["id"]) . "");

                if(!$conn) {

                    die(mysqli_error($conn));

                }
                notifications::add("success", $args["cmd"] . " successfully updated.");
                sleep(3);
                return $response->withJson(array("status"=>true, "error"=>null), 200);
                exit;

            } else {


                return $response->withJson(array("status"=>false, "error"=>"Invalid file"), 400);
                exit;

            }

        } else {

            return $response->withJson(array("status"=>false, "error"=>"No file provided"), 400);
            exit;

        }


    });
    
    $this->post("/ajax/films/{id}/filmStatus", function($request, $response, $args){
        
        $user = $this->get("user");
        $user->loginRequired();
        
        $cinema = $this->get("cinema");
        $filmId = cipher::decrypt($args["id"]);
        $body = $request->getParsedBody();
        
        if(!$cinema->updateFilmStatus($filmId, $body["status"])) {
            
            return $response->withJson(array(
                "status" => 400,
                "error" => "An error occurred while updating film status."
            ), 400);
            
        } else {
         
            return $response->withJson(array(
                "status" => 200,
                "html" => "Film status successfully updated."
            ), 200);
            
        }
            
        
        
    });
    
    $this->get("/ajax/films/{id}/addShowing", function($request, $response, $args){
        
        $user = $this->get("user");

        $user->loginRequired();
        
        $cinema = $this->get("cinema");


        // Getting ticket options
        $ticketTypes = $cinema->getTicketTypes(array(), false);

        $tickets = "";

        foreach($ticketTypes as $id => $type) {

            $tickets .= "<option value='" . cipher::encrypt($type['id']) ."'>" . $type["ticket_label"] . "</option>";

        }

        // Getting screen options

        $screenOptions = $cinema->getScreens();

        $screens = "";

        foreach($screenOptions as $id => $screen) {

            $screens .= "<option value='" . cipher::encrypt($screen['id']) . "'>Screen " . $screen["screen_name"] . "</option>";

        }

        // Generating html

        $html = str_replace(array("{filmId}", "{screens}", "{ticketTypes}"),
            array($args["id"], $screens, $tickets),
            file_get_contents("../templates/Manage/films/partial_new_showtime.phtml"));

        // Returning result

        return $response->withJson(array("status"=>200, "html"=>$html), 200);
        
        
        
    });

    $this->get("/ajax/screens/addRows", function($request, $response, $args){

        $user = $this->get("user");

        $user->loginRequired();
        $cinema = $this->get("cinema");
        
        //Check screen exists
        if(!$cinema->screenExists($screenId)) {
            
            return $response->withJson(array(
                "error" => "invalid_screenId",
                "error_desc" => "Unable to find screen with provided id."
            ), 400);
            
        }

        // Generating html

        $html = file_get_contents("../templates/Manage/screens/partial_new_screen_rows.phtml");

        // Returning result

        return $response->withJson(array("status"=>200, "html"=>$html), 200);

    });

    $this->post("/ajax/screens/{id}/addRows/", function($request, $response, $args){

        $user = $this->get("user");
        $user->loginRequired();
        $cinema = $this->get("cinema");

        $screenId = cipher::decrypt($args["id"]);
        
        //Check screen exists
        if(!$cinema->screenExists($screenId)) {
            
            return $response->withJson(array(
                "error" => "invalid_screenId",
                "error_desc" => "Unable to find screen with provided id."
            ), 400);
            
        }
        
        $params = $request->getParsedBody();
        $required = array("rows", "seats", "seatLabel");

        foreach($required as $item) {

            if(!isset($params[$item])) {

                return $response->withJson(array(
                    "error" => "missing_parameter",
                    "error_desc" => "One or more parameters missing."
                ), 400);

            }

            switch($item) {

                case "seatLabel":
                    if(!ctype_alpha($params["seatLabel"]) || strlen($params["seatLabel"]) < 1){

                        return $response->withJson(array("error" => "invalid_seatLabel"), 400);

                    }
                    break;

                default:
                    if(!ctype_digit($params[$item]) || $params[$item] > 30 || $params[$item] < 1) {

                        return $response->withJson(array("error" => "invalid_$item"), 400);

                    }
                    break;

            }
        }

        $insert = $cinema->addScreenRows(array(
            "screenId" => $screenId,
            "rows" => $params["rows"],
            "seats" => $params["seats"],
            "seatLabel" => $params["seatLabel"],
        ));

        if($insert["status"]) {

            return $response->withJson(array("status" => 200), 200);

        } else {

            return $response->withJson($insert, 400);

        }

    });

    $this->post("/ajax/films/{id}/addShowing", function($request, $response, $args){

        $user = $this->get("user");

        $user->loginRequired();

        $cinema = $this->get("cinema");

        $params = $request->getParsedBody();

        $data = array();

        // Mapping data
        $data["date"] = $params["showDate"];
        $data["time"] = strtotime($params["showDate"] . " " . $params["showTime"]);
        $data["screen_id"] = cipher::decrypt($params["screenSelection"]);
        
        // Checking that at least one ticket option has been selected
        if(count($params["ticketSelection"]) < 1) {
            
            return $response->withJson(array("status" => 400, "error" => "missing_info", "error_desc" => "At least one ticket option must be selected."), 400);
            
        }

        $tickets = array();

        foreach($params["ticketSelection"] as $ticket) {

            $tickets[] = cipher::decrypt($ticket);
        }

        $data["ticket_config"] = json_encode(array("types" => $tickets));
        $data["film_id"] = cipher::decrypt($args["id"]);
        $data["special_requirements"] = ((strlen($params["requirements"]) < 1) ? " " : $params["requirements"]);

        // Social Distancing
        $data["social_distancing"] = (($cinema->getConfigItem("social_distancing")["value"] == 1) ? 1 : 0);

        // Data is fine so insert into database
        $r = $cinema->addShowing($data);

        if(!$r["status"]) {

            return $response->withJson(array("error" => $r["error"], "error_desc" => $r["error_desc"]), 400);

        } else {

            notifications::add("success", "New show added");

         return $response->withJson(array("html" => "Show successfully added"));

        }

    });

    $this->get("/ajax/bookings/cancel/{id}", function($request, $response, $args){

        $user = $this->get("user");

        $user->loginRequired();

        $cinema = $this->get("cinema");

        $id = cipher::decrypt($args["id"]);

        $booking = $cinema->getBookingInfo($id);

        // Generating html

        $html = str_replace(array("{TOTAL}"),
            array($booking["booking_total"]),
            file_get_contents("../templates/Manage/bookings/partial_cancel_booking.phtml"));

        // Returning result

        return $response->withJson(array("status"=>200, "html"=>$html, "total" => $booking["booking_total"]), 200);



    });


    $this->post("/ajax/bookings/cancel/{id}", function($request, $response, $args){
        
        $user = $this->get("user");

        $user->loginRequired();
        
        $cinema = $this->get("cinema");
        $payments = $this->get("payments");
        
        $id = cipher::decrypt($args["id"]);

        $body = $request->getParsedBody();

        // Checking if we need to do a refund
        switch($body["refundType"]) {
            case "full":
                $booking = $cinema->getBookingInfo($id);

                $refund = $payments->refundPayment($booking["id"]);
                break;

            case "partial":
                $booking = $cinema->getBookingInfo($id);

                if($body["refundAmount"] > $booking["booking_total"]) {

                    return $response->withJson(array("error" => "invalid_amount"), 400);

                }

                $refund = $payments->refundPayment($booking["id"], $body["refundAmount"]);
                break;

            case "none":
                $refund = array("status" => false);
                break;

            default:
                return $response->withJson(array("error"=>"invalid_request"),400);
                break;

        }

        if(!$refund["status"]) {

            return $response->withJson(array("error" => "server_error", "data" => $refund), 500);

        }
        
        // Cancel booking
        $cinema->cancelBooking($id, $body["refundType"]);
        
        notifications::add("success", "Booking cancelled");
        
        return $response->withJson(array("status"=>200, "html"=>"Booking cancelled"), 200);
        
    });

    $this->get("/ajax/bookings/{id}/resendConfirmation", function($request, $response, $args){

        $user = $this->get("user");

        $user->loginRequired();

        $cinema = $this->get("cinema");

        // Get booking info from ID
        $booking = $cinema->getBookingInfo(cipher::decrypt($args["id"]));

        // Generating html

        $html = str_replace(array("{id}", "{email}"),
            array($args["id"], $booking["booking_email"]),
            file_get_contents("../templates/Manage/bookings/partial_resend_confirmation.phtml"));

        // Returning result

        return $response->withJson(array("status"=>200, "html"=>$html), 200);



    });

    $this->post("/ajax/bookings/{id}/resendConfirmation", function($request, $response, $args){

        $user = $this->get("user");

        $user->loginRequired();

        $cinema = $this->get("cinema");
        $body = $request->getParsedBody();

        if(!isset($body["email"]) || !filter_var($body["email"], FILTER_VALIDATE_EMAIL)){

            return $response->withJson(array("error" => "invalid_email", "error_desc" => "Invalid email provided."), 400);

        }

        $cinema->sendBookingConfirmation(cipher::decrypt($args["id"]), $body["email"]);

        notifications::add("success", "Email sent successfully");

        return $response->WithJson(array("html" => "Confirmation sent successfully"), 200);

    });

    $this->map(["GET", "POST"],"/ajax/bookings/{id}/movePerformance[/{op}]", function($request, $response, $args){

        $user = $this->get("user");
        $user->loginRequired();

        $cinema = $this->get("cinema");

        $op = ((isset($args["op"])) ? $args["op"] : "start");

        switch($op) {
            case "selection":
                if(!$request->isPost()) {

                    return $response->withJson(array("status" => 400, "error" => "Operation must be called via POST"), 400);

                }

                $postBody = $request->getParsedBody();

                if(!isset($postBody["showId"])) {

                    return $response->withJson(array("status" => 400, "error" => "missing_param"), 400);

                }

                $bookingInfo = $cinema->getBookingInfo(cipher::decrypt($args["id"]));

                // Getting seat Ids
                $seats = $cinema->getSeatingInfo(json_decode($bookingInfo["booking_seats"], true));

                $seatLabels = array();

                foreach($seats as $seat) {

                    $seatLabels[] = $seat["seat_row_label"] . $seat["seat_number"];

                }

                $seatFinal = implode(",", $seatLabels);

                $screen = $cinema->buildSeatingPlan(cipher::decrypt($postBody["showId"]), 1);

                return $response->withJson(array(
                    "html" => $screen["html"],
                    "allowed" => $bookingInfo["booking_seats_total"],
                    "seats" => $seatFinal
                ), 200);

                break;


            case "start":
            $itemHtml = "";
            $bookingInfo = $cinema->getBookingInfo(cipher::decrypt($args["id"]));

            $data = $cinema->getMovePerformanceShowings($bookingInfo["showtime_id"], $bookingInfo["film_id"]);

            if (count($data) < 1) {

            $itemHtml .= "<div class='container p-5 text-center text-secondary'><h5>No other performances available</h5></div>";

            }
            else {

                foreach ($data as $id => $item) {

                    $itemHtml .= '<a class="MP-item list-group-item list-group-item-action my-1 ' . (($item["available"] < 1) ? "disabled bg-light" : "") . '" href="Javacript:void(0)" data-showid="' . cipher::encrypt($item["id"]) .'">';
                    $itemHtml .= '<div class="d-flex w-100 py-1">';
                    $itemHtml .= '<div class="col-8">';
                    $itemHtml .= '<h5 class="mb-1">' . date("l jS F", $item["time"]) . '</h5>';
                    $itemHtml .= '<small class="mb-1">' . date("g:ia", $item["time"]) . '</small>';
                    $itemHtml .= '</div>';
                    $itemHtml .= '<div class="col-4 justify-content-inbetween text-right">';
                    $itemHtml .= '<small>' . (($item["available"] < 1) ? "SOLD OUT" : $item["available"] . " seats remaining") . '<br>Screen ' . $item["screen_name"] . '</small>';
                    $itemHtml .= '</div>';
                    $itemHtml .= '</div>';
                    $itemHtml .= '</a>';

                }

            }

            $html = str_replace(array("{items}"),
                array($itemHtml),
                file_get_contents("../templates/Manage/bookings/partial_move_performance.phtml"));

            return $response->withJson($html, 200);
            break;

            case "process":

                if(!$request->isPost()) {

                    return $response->withJson(array("status" => 400, "error" => "Operation must be called via POST"), 400);

                }

                $postBody = $request->getParsedBody();

                if(!isset($postBody["showId"]) || !isset($postBody["seats"])) {

                    return $response->withJson(array("status" => 400, "error" => "missing_param"), 400);

                }


                $bookingInfo = $cinema->getBookingInfo(cipher::decrypt($args["id"]));

                $show = cipher::decrypt($postBody["showId"]);
                $seats = array();

                foreach($postBody["seats"] as $seat) {

                    $seats[] = cipher::decrypt($seat);

                }

                $update = $cinema->updateBooking($bookingInfo["booking_reference"], array(
                    "showtime_id" => $show,
                    "booking_seats" => $seats,
                    "booking_ts" => time()
                ));

                $cinema->sendBookingConfirmation($bookingInfo["booking_reference"]);

                notifications::add("success", "Booking successfully moved to a different performance.");

                // Creating summary page
                $info = $cinema->getShowInfo($show);

                $summary = "";

                $summary .= "<div class='container container-fluid'>";
                    $summary .= "<h4 class='text-dark'>New performance date</h4>";
                    $summary .= "<h3>" . date("l jS F g:ia", $info["time"]) . "</h3><br/>";
                    $summary .= "<h4 class='text-dark'>Screen</h4>";
                    $summary .= "<h3>Screen " . $info["screen_name"] . "</h3>";
                $summary .= "</div>";
                $summary .= "<button id='MPClose' class='btn btn-info btn-block my-2'>Close</button>";


                return $response->withJson(array("status" => 200, "html" => $summary), 200);
                break;

            default:
                return $response->withJson(array("status" => 400, "error" => "Invalid command"), 400);
                break;

    }

    });
    
     /** END OF AJAX */

    $this->get("", function($request, $response, $args) {
        
        $user = $this->get("user");

        $user->loginRequired();

        return $response = $this->manageView->render($response, "/dashboard/dashboard.phtml", [
            "_title" => "Manage",
            "_user" => $_SESSION["user"],
            "_page" => "dashboard"
            ]);

    });

    /** SCREENS */

    $this->get("/screens", function($request, $response, $args){
        
        $user = $this->get("user");
        $user->loginRequired();
        
        $cinema = $this->get("cinema");
        
        $screens = $cinema->getScreens();
        
        $tableHtml = "";
        
        foreach($screens as $index => $screen) {
            
            $activeShowings = $cinema->getShowtimesByScreen($screen["id"], false);
            
            $tableHtml .= "<tr " . (($screen['status'] == 0) ? "class='bg-gray-100 text-gray-500'" : "") . ">";
            
                $tableHtml .= "<td>" . $screen["id"] . "</td>";
                $tableHtml .= "<td><a href='/Manage/screens/" . cipher::encrypt($screen["id"]) . "'>Screen " . $screen["screen_name"] . " " . (($screen['status'] == 0) ? "[Inactive]": "") . "</a></td>";
                $tableHtml .= "<td>$activeShowings</td>";
                
                $tableHtml .= "<td>";
                    
                    $tableHtml .= "<a href='/Manage/screens/" . cipher::encrypt($screen["id"]) . "' class='btn btn-primary'>view</a>";
                    
                    if($activeShowings < 1) {
                        
                        $tableHtml .= "&nbsp;<a onclick='return confirm(\"Are you sure you want to delete this screen?\");' href='/Manage/ajax/screens/delete/" . cipher::encrypt($screen["id"]) . "' class='btn btn-danger'>Delete</a>";
                    
                    }
                    
                $tableHtml .= "</td>";
                
            $tableHtml .= "</tr>";    
            
        }

        return $this->manageView->render($response, "/screens/overview.phtml", [
            "_title" => "Manage Screens",
            "_user" => $_SESSION["user"],
            "_page" => "screens",
            "screens" => $tableHtml
        ]);


    });

    /** TICKETS */

    $this->get("/tickets", function($request, $response, $args){

        $user = $this->get("user");
        $user->loginRequired();

        $cinema = $this->get("cinema");

        $tickets = $cinema->getTicketInfo("*");

        $tableHtml = "";
        setlocale(LC_MONETARY,"en");
        foreach($tickets as $index => $ticket) {

            $tableHtml .= "<tr " . (($ticket['ticket_status'] == 0) ? "class='bg-gray-100 text-gray-500'" : "") . ">";

            $tableHtml .= "<td>" . $ticket["id"] . "</td>";
            $tableHtml .= "<td><a href='/Manage/tickets/" . cipher::encrypt($ticket["id"]) . "'>" . $ticket["ticket_label"] . " " . (($ticket['ticket_status'] == 0) ? "[Inactive]": "") . "</a></td>";
            $tableHtml .= "<td>&pound;" .  money_format("%i", $ticket["ticket_cost"]). "</td>";

            $tableHtml .= "<td>";

            $tableHtml .= "<a href='/Manage/tickets/" . cipher::encrypt($ticket["id"]) . "' class='btn btn-primary'>view</a>";

            $tableHtml .= "</td>";

            $tableHtml .= "</tr>";

        }

        return $this->manageView->render($response, "/tickets/overview.phtml", [
            "_title" => "Manage Tickets",
            "_user" => $_SESSION["user"],
            "_page" => "tickets",
            "tickets" => $tableHtml
        ]);


    });
    
    $this->get("/bookings", function($request, $response, $args){
        
        $user = $this->get("user");
        $user->loginRequired();
        
        $cinema = $this->get("cinema");
        
        $bookings = $cinema->getBookings();
        
        $tableHtml = "";
        
        foreach($bookings as $index => $booking) {
            
            $tableHtml .= "<tr>";
            
                $tableHtml .= "<td data-order='" . $booking['booking_ts'] ."'>" . date("d/m/Y", $booking["booking_ts"]) . "</td>";
                $tableHtml .= "<td><a href='/Manage/bookings/" . cipher::encrypt($booking["booking_reference"]) . "'>" . $booking["booking_reference"] . "</a></td>";
                $tableHtml .= "<td>" . $booking["film_name"] . "</td>";
                $tableHtml .= "<td>" . $booking["booking_total"] . ".00</td>";
                $tableHtml .= "<td>" . str_replace("_", " ", $booking["booking_status"]) . "</td>";
                
                $tableHtml .= "<td>";
                    
                    $tableHtml .= "<a href='/Manage/bookings/" . cipher::encrypt($booking["booking_reference"]) . "' class='btn btn-primary'>view</a>";
                    
                $tableHtml .= "</td>";
                
            $tableHtml .= "</tr>";    
            
        }

        return $this->manageView->render($response, "/bookings/overview.phtml", [
            "_title" => "Manage bookings",
            "_user" => $_SESSION["user"],
            "_page" => "bookings",
            "bookings" => $tableHtml
        ]);


    });
    
    $this->get("/bookings/{id}", function($request, $response, $args){
        
        $user = $this->get("user");
        $user->loginRequired();
        
        $cinema = $this->get("cinema"); 
        
        $id = cipher::decrypt($args["id"]);
        $booking = $cinema->getBookingInfo($id);
        $seating = $cinema->getSeatingInfo(json_decode($booking["booking_seats"], TRUE));
        $filmInfo = $cinema->getFilmData($booking["film_id"]);
        
        // Build booking details screen
        $detailsHtml = "<table id='bookingDetails' class='table table-bordered table-responsive-sm' style='min-width:100%'>";
        
            $detailsHtml .= "<tbody>";
                
                // Film Name
                $detailsHtml .= "<tr scope='row'>";
                
                    $detailsHtml .= "<th class='bg-light'>Film</th>";
                    $detailsHtml .= "<td>" . $filmInfo["film_name"] . "</td>";
                    
                $detailsHtml .= "</tr>";
                
                // Booking Name
                $detailsHtml .= "<tr scope='row'>";
                
                    $detailsHtml .= "<th class='bg-light'>Name</th>";
                    $detailsHtml .= "<td>" . $booking["booking_name"] . "</td>";
                    
                $detailsHtml .= "</tr>";
                     
                // Booking Email
                $detailsHtml .= "<tr scope='row'>";
                
                    $detailsHtml .= "<th class='bg-light'>Email</th>";
                    $detailsHtml .= "<td>" . $booking["booking_email"] . "</td>";
                    
                $detailsHtml .= "</tr>";
                
                // Booking Phone
                $detailsHtml .= "<tr scope='row'>";
                
                    $detailsHtml .= "<th class='bg-light'>Phone</th>";
                    $detailsHtml .= "<td>" . $booking["booking_phone"] . "</td>";
                    
                $detailsHtml .= "</tr>";
                
            $detailsHtml .= "</tbody>";
            
        $detailsHtml .= "</table>";

        // Building tickets screen

        $tickets = $cinema->getBookingTicketInfo($id);
        $ticketHtml = "";
        $total = 0;

        foreach($tickets as $ticket) {

            $ticketHtml .= "<tr>";

                $ticketHtml .= "<td class='text-center'><input type='checkbox'/></td>";
                $ticketHtml .= "<td>" . $ticket["item"] . "</td>";
                $ticketHtml .= "<td>" . $ticket["units"] . "</td>";
                $ticketHtml .= "<td>&pound;" . $ticket["price"] . "</td>";
                $ticketHtml .= "<td>&pound;" . $ticket["total"] . "</td>";
                $ticketHtml .= "<td class='d-none d-sm-table-cell'>" . $ticket["seat_total"] . "</td>";
                $ticketHtml .= "<td>" . $ticket["seat_labels"] . "</td>";


            $ticketHtml .= "</tr>";

            $total += $ticket["total"];

        }
        
        
        return $this->manageView->render($response, "/bookings/view.phtml", [
            "_title" => $id . " - Manage booking",
            "_user" => $_SESSION["user"],
            "_page" => "bookings",
            "booking" => $booking,
            "bookingId" => $args["id"],
            "details" => $detailsHtml, /*"" . print_r($booking, TRUE) . "", */
            "tickets" => $ticketHtml,
            "ticketTotal" => $total . ".00"
        ]);
        
        
    });
    
    $this->get("/films", function($request, $response, $args){
        
        $user = $this->get("user");
        $user->loginRequired();
        
        $cinema = $this->get("cinema");
        
        $films = $cinema->getAllFilms();

        $tableHtml = "";
        
        foreach($films as $index => $film) {
            
            $activeShowings = $cinema->getShowtimesByFilm($film["id"], false);
            
            $tableHtml .= "<tr " . (($film['film_status'] == 0) ? "class='bg-gray-100 text-gray-500'" : "") . ">";
            
                $tableHtml .= "<td class='align-middle text-center'>" . $film["id"] . "</td>";
                
                $tableHtml .= "<td>";
                    
                    $tableHtml .= "<a href='/Manage/films/" . cipher::encrypt($film["id"]) . "'>";
                
                        $tableHtml .= "<div class='row p-0 no-gutters'>";
                        
                            $tableHtml .= "<div class='col-1 d-none d-xl-block mr-0'>";
                        
                                $tableHtml .= "<img class='img-fluid rounded film-thumb p-0' style='max-width:40px; min-height:60px;' src='" . $film["film_thumbnail"] . "'/>";
                        
                            $tableHtml .= "</div>";
                            
                            $tableHtml .= "<div class='col-12 col-xl-11 mt-xl-3 mt-2 ml-0'>";
                            
                                $tableHtml .= $film["film_name"] . " <span class='small'>(" . $film["film_rating"] . ")</span> " . (($film['film_status'] == 0) ? "[Inactive]": "");
                                
                            $tableHtml .= "</div>";
                            
                        $tableHtml .= "</div>";
                        
                    $tableHtml .= "</a>";    
                            
                $tableHtml .= "</td>";
                
                $tableHtml .= "<td class='align-middle d-none d-sm-table-cell'>$activeShowings</td>";
                
                $tableHtml .= "<td class='align-middle'>";
                    
                    $tableHtml .= "<a href='/Manage/films/" . cipher::encrypt($film["id"]) . "' class='btn btn-primary'>view</a>";
                    
                    if($activeShowings < 1) {
                        
                        $tableHtml .= "&nbsp;<button onclick='Cinema.deleteFilm(\"" . cipher::encrypt($film["id"]) . "\")' class='btn btn-danger'>Delete</button>";
                    
                    }
                    
                $tableHtml .= "</td>";
                
            $tableHtml .= "</tr>";    
            
        }

        return $this->manageView->render($response, "/films/overview.phtml", [
            "_title" => "Manage Films",
            "_user" => $_SESSION["user"],
            "_page" => "films",
            "films" => $tableHtml
        ]);


    });

    $this->get("/films/{id}[/{op}]", function($request, $response, $args) {

        $user = $this->get("user");
        $user->loginRequired();

        if(!ctype_alnum($args["id"])) {

            return $response->write("ERROR: INVALID ID");

        }

        $cinema = $this->get("cinema");

        $film = $cinema->getFilmData(cipher::decrypt($args["id"]));

        $op = ((isset($args["op"])) ? $args["op"] : "info");

        $returnObj = array(
            "_title" => $film["film_name"],
            "_user" => $_SESSION["user"],
            "_page" => "films",
            "op" => $op,
            "film_id" => $args["id"]
        );

        // Processing operation
        switch($op) {

            case "showtimes":

                $view = ((isset($_GET["view"])) ? ((in_array($_GET["view"], array("all"))) ? $_GET["view"] : "upcoming") : "upcoming");

                $showtimes = $cinema->getShowtimesByFilm(cipher::decrypt($args["id"]), true, $view);
                $getScreens = $cinema->getScreens();
                $screens = array();
                $html = "";
                
                foreach($getScreens as $id => $screen) {
                    
                    $screens[$screen["id"]] = $screen;
                    
                }
                
                if(count($showtimes) >= 1) {
                                
                    foreach($showtimes as $id => $show) {
                        
                        $html .= "<tr>";
                            $html .= "<td>" . date("d/m/Y H:i", $show["time"]) . "</td>"; 
                            $html .= "<td>Screen " . $screens[$show["screen_id"]]["screen_name"] . "</td>";
                            $html .= "<td>";
                                
                               $html .= "<button class='btn btn-info m-1'>Edit</button>";
                               $html .= "<button class='btn btn-info' onclick='Cinema.getShowPlan(\"" . $args["id"] . "\",\"" . cipher::encrypt($show["id"]) ."\")'>View Plan</button>";
                            
                            $html .= "</td>";
                        $html .= "</tr>";
       
                    }
                }

                $viewData = (($view !== "all") ? "<a class=\"btn btn-sm btn-info float-right\" href=\"?view=all\">View all showings</a>" : "<a class=\"btn btn-sm btn-info float-right\" href=\"?view=upcoming\">View upcoming showings</a>");

                $returnObj["html"] = str_replace(array("{showtimes}", "{film}", "{VIEW}"), array($html, $args["id"], $viewData),
                file_get_contents("../templates/Manage/films/partial_showtimes.phtml"));
                break;
                
            case "media":
                $returnObj["html"] =  str_replace(array("%ID%", "%DID%"), array($args["id"], cipher::decrypt($args["id"])),
                    file_get_contents("../templates/Manage/films/partial_media.phtml"));
                break;    

            default:
                $status = (($film["film_status"] == "1") ? "checked" : "");
            
                $form = str_replace(array("{filmId}", "{filmStatus}", "{filmName}", "{filmDesc}", "{filmRelease}", "{filmRuntime}"),
                array(cipher::encrypt($film["id"]), $status, $film["film_name"], $film["film_desc"], date("d-m-Y", $film["film_release"]), $film["film_runtime"]),
                file_get_contents("../templates/Manage/films/partial_edit_film.phtml"));
            
                $returnObj["html"] = $form;
                $returnObj["html"] .= "<script>$('#inputFilmRating').val('" . $film["film_rating"] . "');</script>";
            
                break;

        }

        return $this->manageView->render($response, "/films/view.phtml", $returnObj);

    });


    $this->get("/screens/{id}[/{op}]", function($request, $response, $args) {
        
        $user = $this->get("user");
        $user->loginRequired();

        if(!ctype_alnum($args["id"])) {
            
            return $response->write("ERROR: INVALID ID");
            
        }
        
        $cinema = $this->get("cinema");
        $screenId = cipher::decrypt($args["id"]);
        
        $screen = $cinema->getScreens(array(cipher::decrypt($args["id"])));
        
        $op = ((isset($args["op"])) ? $args["op"] : "info");
        
        $returnObj = array(
            "_title" => "Screen " . $screen[0]["screen_name"],
            "_user" => $_SESSION["user"],
            "_page" => "screens",
            "op" => $op,
            "screen_id" => $args["id"] 
        );
        
        // Processing operation
        switch($op) {
            
            case "layout":
                $plan = $cinema->buildScreenPlan($screenId);
                $content = str_replace(array("%LAYOUT%", "%SCREENID%"), array($plan["html"], $args["id"]), file_get_contents("../templates/Manage/screens/partial_screen_layout.phtml"));
                $returnObj["html"] = $content;                
                break;
                
            default:
                $returnObj["html"] = "<h2 class='text-center'>TEST INFO PAGE</h2>"; 
                break;
                                                             
        }
        
        return $this->manageView->render($response, "/screens/view.phtml", $returnObj);

    });

    /** ####### TOOLS ######## */
    
    /** ## SHOWING TIMELINE ## */
    
    $this->get("/tools/timeline", function($request, $response, $args) {

        // HELP - https://www.jqueryscript.net/time-clock/Simple-Daily-Schedule-Plugin-with-jQuery-and-jQuery-UI-Schedule.html //

        $user = $this->get("user");
        $user->loginRequired();
        $cinema = $this->get("cinema");

        if (isset($_GET["date"])) {

            $p = $_GET["date"];

        } else {

            $p = date("d-m-Y", time());
        }

        $shows = $cinema->getShowtimesByDate($p);


        $data = array();
        $screens = $cinema->getScreens();
        $countOne = 1;

        // Creating output array with screen info
        foreach($screens as $int => $screen) {

            $output[$screen["id"]] = array(
                "title" => "Screen " . $screen["screen_name"],
                "schedule" => array()
            );
            $countOne++;
        }

        foreach ($shows as $show) {

                $output[$show["screen_id"]]["schedule"][] = array(
                    "start" => date("H:i", $show["time"]),
                    "end" => date("H:i", ($show["time"] + ($show["runtime"] * 60))),
                    "text" => $show["film_name"],
                    "data" => array(
                        "url" => "/Manage/films/" . cipher::encrypt($show["film_id"]) ."/showtimes",
                    )
                );

        }

        $returnObj = array(
            "_title" => "Timeline",
            "_user" => $_SESSION["user"],
            "_page" => "timeline",
            "schedule" => $output,
            "date" => ((isset($_GET['date'])) ? $_GET["date"] : "")
        );
        
        return $this->manageView->render($response, "/tools/showing_timeline.phtml", $returnObj);
     
    });

    /** ## TICKET POS ## */

    $this->get("/tools/ticketPOS", function($request,$response,$args){

        $user = $this->get("user");
        $user->loginRequired();

        $returnObj = array(
            "_title" => "TICKET POS",
            "_user" => $_SESSION["user"],
            "_page" => "TICKET POS"
        );

        return $this->manageView->render($response, "/tools/ticket_pos.phtml", $returnObj);

    });

    $this->post("/ajax/tools/ticketPOS/{op}", function($request, $response, $args) {

        $user = $this->get("user");
        $user->loginRequired();

        $cinema = $this->get("cinema");

        // Checking for cmd
        $op = $args["op"];

        switch($op) {

            case "search":

                $body = $request->getParsedBody();

                if(!$body["booking_ref"]){

                    return $response->withJson(array("status" =>400, "error" => "missing_ref", "error_desc" => "Missing booking reference"), 400);

                }

                $ref = $body["booking_ref"];

                $booking = $cinema->getBookingInfo($ref);

                if($booking === false) {

                    $html = file_get_contents("../templates/Manage/tools/partials/ticketPOS_no_booking.phtml");

                    return $response->withJson(array("status" => 200, "code" => "no_booking", "html" => $html), 200);

                } else {

                    $ticketsUsed = (($booking["booking_tickets_issued"] == 1) ? true : false);

                    switch($booking["booking_status"]) {

                        case "PAID":

                            $filmInfo = $cinema->getFilms(array($booking["film_id"]), false);
                            $showInfo = $cinema->getShowInfo($booking["showtime_id"]);
                            $filmName = $filmInfo[0]["film_name"];
                            $tickets = $cinema->getTicketInfoBybooking($ref);
                            $info = json_decode($booking["booking_info"], true);
                            $admits = "";

                            //return $response->withJson($tickets, 200);

                            $count = 0;
                            foreach($tickets as $id => $info) {

                                $admits .= (($count >= 1) ? ", ": "") . $info["count"] . " " . $info["label"];
                                $count++;
                            }

                            $html = str_replace(array(
                                "{FILM}",
                                "{DATE}",
                                "{TIME}",
                                "{SCREEN}",
                                "{ADMITS_TOTAL}",
                                "{ADMITS}",
                                "{BOOKER}",
                                "{BOOKING_LINK}"
                                ),
                                array(
                                    $filmName,
                                    $showInfo["date"],
                                    date("H:i", $showInfo["time"]),
                                    $showInfo["screen_name"],
                                    $booking["booking_seats_total"],
                                    $admits,
                                    $booking["booking_name"],
                                    "/Manage/bookings/" . cipher::encrypt($ref) . ""
                                ),
                                file_get_contents("../templates/Manage/tools/partials/ticketPOS_valid.phtml"));

                            $code = (($ticketsUsed === true) ? "valid_but_used" : "valid_booking");
                            break;

                        case "cancelled":
                            $html = "<a class='btn btn-info' href='/Manage/bookings/" . cipher::encrypt($ref). "'>Manage Booking</a>";
                            $code = "cancelled_booking";
                            break;

                        case "reserved":
                            $html = false;
                            $code = "unpaid_booking";
                            break;

                        default:
                            die("ERROR: NO BOOKING STATUS CASE SET FOR [" . $booking["booking_status"] . "]");
                            break;

                    }

                    return $response->withJson(array("status" => 200, "code" => $code, "html" => $html), 200);

                }

                break;

            case "validate":

                $body = $request->getParsedBody();

                if(!$body["booking_ref"]){

                    return $response->withJson(array("status" =>400, "error" => "missing_ref", "error_desc" => "Missing booking reference"), 400);

                }

                $ref = $body["booking_ref"];

                $updateBooking = $cinema->updateBooking($ref, array("booking_tickets_issued" => 1));

                return $response->withJson(array("status" => 200), 200);
                break;

            default:

                return $response->withJson(array("status" => 400, "error" =>"invalid_request", "error_desc" => "Invalid command provided."), 400);
                break;
        }

    });

    /** AUTHENTICATION **/
    
    $this->get("/authenticate/login", function($request, $response, $args){

        $user = $this->get("user");

        if($user->loggedIn()) {

            $user->redirect("/Manage");

        }

        $html = file_get_contents("../templates/Manage/authentication/login.phtml");
        
        $errors = notifications::display() . "<br/>";

        return str_replace(array("<?=\$title?>", "#errors#"), array("Login | " . $_SESSION["system"]["name"], $errors), $html);
        
    });

    $this->map(['GET', 'POST'],"/tools/bulkImporter", function($request, $response, $args){

        $user = $this->get("user");
        $user->loginRequired();

        if(file_exists($_FILES["files"]["tmp_name"]) && is_uploaded_file($_FILES["files"]["tmp_name"])) {
            $files = $this->get("files");
            $file = $_FILES["files"]["tmp_name"];


            try {

                if($_FILES["files"]["size"] > 2000000) {
                    throw new Exception("File to large. Please upload a smaller csv. ");
                }

                $test = $files->validateFileType($file, array("text/csv", "text/plain"));

                if($test !== true) {

                    throw new  Exception("An error occurred validating the file.");

                }

                $fh = fopen($file, 'r+');

                $lines = array();
                while( ($row = fgetcsv($fh, 8192)) !== FALSE ) {
                    if($row[0] !== "" && $row[1] !== "") {
                        $lines[] = $row;
                    }
                }

                $_SESSION["csvData"] = $lines;
                //print "<pre>"; print_r($lines[0]); print "</pre>";
                //exit;

                $headings = "";
                $required = array(
                    "date" => "Date",
                    "time" => "Time",
                    "screen_id" => "Screen Id",
                    "film_id" => "Film Id",
                    "ticket_id" => "Ticket Ids"
                );

                foreach($required as $column => $heading) {

                    $headings .= "<div class='col-2'>";
                        $headings .= "<div class='form-group'>";
                            $headings .= "<label for='" . $column . "'>$heading</label>";
                            $headings .= "<select name='" . $column . "' class='form-control' id='" . $column . "'>";

                            foreach($lines[0] as $id => $item) {

                                $headings .= "<option value='$id'>$item</option>";

                            }

                            $headings .= "</select>";
                        $headings .= "</div>";
                    $headings .= "</div>";

                }

                $html = str_replace(array(
                    "%HEADINGS%"
                ),
                    array(
                    $headings
                    ),
                    file_get_contents("../templates/Manage/films/partial_showtime_bulk_importer.phtml"));

                $returnObj = array(
                    "_title" => "Bulk Importer",
                    "_user" => $_SESSION["user"],
                    "_page" => "films",
                    "html" => $html
                );

                return $this->manageView->render($response, "/films/view.phtml", $returnObj);

            } catch (Exception $e) {

                print $e->getMessage();

            }

            return $response;

        } else {

            $html = file_get_contents("../templates/Manage/tools/partials/partial_showtime_bulk_upload.phtml");


            $returnObj = array(
                "_title" => "Bulk Importer",
                "_user" => $_SESSION["user"],
                "_page" => "films",
                "html" => $html,
                "desc" => "Add showtimes to the system from a csv."
            );

            return $this->manageView->render($response, "/films/view.phtml", $returnObj);
        }


        //$response->getBody()->write("DONE");
    });

    $this->post("/films/showtime_bulk_columns", function($request, $response, $args){

        $user = $this->get("user");
        $cinema = $this->get("cinema");
        $user->loginRequired();
        $body = $request->getParsedBody();
        $required = array("date", "time", "screen_id", "ticket_id", "film_id");

        foreach($required as $item) {
            if(!isset($body[$item])) {
                return $response->withJson(array(
                    "error" => "missing_field",
                    "error_desc" => "$item missing from request."
                ), 400);
            }
        }

        $positions = array();

        $positions["date"] = $body["date"];
        $positions["time"] = $body["time"];
        $positions["screen_id"] = $body["screen_id"];
        $positions["ticket_id"] = $body["ticket_id"];
        $positions["film_id"] = $body["film_id"];

        $csv = $_SESSION["csvData"];

        if(isset($body["firstColumn"])) {
            unset($csv[0]);
        }

        $data = array();
        $filmIds = array();
        $screenIds = array();
        $errors = array();

        foreach($csv as $id => $showing) {

            // Validate date object
            if(!validate::date($showing[$positions["date"]], "DD/MM/YYYY")){
                $errors[$id][] = "invalid_date";
            }

            // Validate time
            if(!validate::time($showing[$positions["time"]])) {
                $errors[$id][] = "invalid_time";
            }

            // Validate Screen id
            if(!validate::numeric($showing[$positions["screen_id"]]) || !$cinema->screenExists($showing[$positions["screen_id"]])) {
                $errors[$id][] = "invalid_screenId";
            }

            // Validate film id
            if(!validate::numeric($showing[$positions["film_id"]]) || !$cinema->filmExists($showing[$positions["film_id"]])) {
                $errors[$id][] = "invalid_filmId";
            }





            $data[] = array(
                "id" => $id,
                "date" => $showing[$positions["date"]],
                "time" => $showing[$positions["time"]],
                "screen_id" => $showing[$positions["screen_id"]],
                "film_id" => $showing[$positions["film_id"]],
                "ticket_id" => array_filter(explode(";", $showing[$positions["ticket_id"]]))
            );

            if(!in_array($showing[$positions["film_id"]], $filmIds)) {
                $filmIds[] = $showing[$positions["film_id"]];
            }

            if(!in_array($showing[$positions["screen_id"]], $screenIds)) {
                $screenIds[] = str_replace(" ", "", $showing[$positions["screen_id"]]);
            }

        }

        $_SESSION["bulkImport"] = $data;

        $ticketInfo = $cinema->getTicketInfo("*");
        $screenInfo = $cinema->getScreenInfo($screenIds);
        $filmInfo = $cinema->getFilmInfo($filmIds);
        $html = "";

        foreach($data as $showing) {
            $tickets = array();
            foreach($showing["ticket_id"] as $ticket) {
                if(!isset($ticketInfo[$ticket])) {
                    $errors[$showing["id"]][] = "invalid_ticketId";
                } else {
                    $tickets[] = $ticketInfo[$ticket]["ticket_label"];
                }
            }


            $errorClass = ((count($errors[$showing["id"]]) >= 1) ? "bg-danger text-white" : "");
            $errorMessage = ((count($errors[$showing["id"]]) >= 1) ? "<br/><span class='font-weight-bold'>Errors</span>: " . implode(", ", $errors[$showing["id"]]) : "");

            $html .= '<div class="MP-item list-group-item list-group-item-action my-1 ' . $errorClass . '">';
            $html .= '<div class="d-flex w-100 py-1">';
            $html .= '<div class="col-8">';
            $html .= '<h5 class="mb-1">Date: ' . $showing["date"] . ' Time: ' . $showing["time"] . '</h5>';
            $html .= '<small class="mb-1"><span class="font-weight-bold">Film:</span> ' . $filmInfo[$showing["film_id"]]["film_name"] . '<br/><span class="font-weight-bold">Tickets:</span> ' . implode(", ", $tickets) . '</small>';
            $html .= '</div>';
            $html .= '<div class="col-4 justify-content-inbetween text-right">';
            $html .= '<small>Screen ' . $screenInfo[$showing["screen_id"]]["screen_name"] . $errorMessage . '</small>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

        }

        return $response->withJson(array(
            "items" => $html,
            "total" => count($data),
            "errors" => (count($errors, COUNT_RECURSIVE) - count($errors))
        ),200);

        //print $html;
        //print "<pre>"; print_r($data); print "</pre>";
        exit;


    });

    $this->post("/showtime_bulk_process", function($request, $response, $args){

        $user = $this->get("user");
        $cinema = $this->get("cinema");

         $user->loginRequired();
         $body = $request->getParsedBody();

        if(!isset($body["cmd"]) || $body["cmd"] !== "process") {

            return $response->withJson(array(
                "error" => "invalid_cmd",
                "error_desc" => "Invalid command provided."
            ), 400);

        }

         $data = $_SESSION["bulkImport"];

        foreach($data as $id => $show) {

            try {
                $required = array(
                    "date" => str_replace("/", "-", $show["date"]),
                    "time" => strtotime(str_replace("/", "-", $show["date"]) . " " . $show["time"]),
                    "film_id" => $show["film_id"],
                    "screen_id" => $show["screen_id"],
                    "special_requirements" => "",
                    "ticket_config" => json_encode(array("types" => $show["ticket_id"]))
                );

                $process = $cinema->addShowing($required);

                if(!$process["status"]) {

                    return $response->withJson(array(
                        "showId" => $id,
                        "error" => $process["error"],
                        "error_desc" => $process["error_desc"]
                    ),400);

                }

            } catch(Exception $e) {

                print $e->getMessage();
                exit;
            }

        }

        unset($_SESSION["bulkImport"]);

        return $response->withJson(array("html"=>count($data). " shows processed and added."),200);
    });

    $this->get("/algotest", function($request, $response, $args){

        function seatBlocker($selection, $seatingPlan, $space)
        {

            // Step 1 - Get Get array positions for each seatId
            $result1 = array(
                array("id" => 17, "row" => 2, "seat_number" => 7),
                array("id" => 18, "row" => 2, "seat_number" => 8),
                array("id" => 19, "row" => 2, "seat_number" => 9)
            );

            $result = array(
                array("id" => 13, "row" => 2, "seat_number" => 3),
                array("id" => 14, "row" => 2, "seat_number" => 4),
                array("id" => 15, "row" => 2, "seat_number" => 5),
                array("id" => 23, "row" => 3, "seat_number" => 3),
                array("id" => 24, "row" => 3, "seat_number" => 4)
            );




            // Step 2 - Sort the seats by their row number
            $grouped = array();

            foreach ($result as $id => $item) {

                if (!isset($grouped[$item["row"]])) {

                    $grouped[$item["row"]] = array();

                }

                $grouped[$item["row"]][] = $item;

            }

            // Step 3 - Collect ids of seats in the vacinity of the selection
            $blockedSeats = array();

            foreach ($grouped as $row => $items) {

                foreach ($items as $id => $seat) {

                    $rightSeat = $seat["seat_number"];

                    $l = 1;

                    // Check left side
                    while($l <= $space) {

                        $stop = false;

                        $leftSeat = (($seat["seat_number"] - 1) - $l);
                        //die(var_dump($leftSeat));

                        // Check seat to the left exists
                        if ($seatingPlan[($seat["row"])][$leftSeat] !== null) {

                            // If seat exists, check it isn't a selected seat
                            if (!in_array($seatingPlan[($seat["row"])][$leftSeat], $selection)) {

                                $blockedSeats[] = $seatingPlan[($seat["row"])][$leftSeat];

                            } else {
                                //die("FAILED CHECK: ". $seatingPlan[($seat["row"])][$leftSeat]);
                                // Stopping loop as seat is a selected seat.
                                $stop = true;

                            }

                            if($l == 1) {

                                // Check seat behind exists
                                if ($seatingPlan[($seat["row"] - 1)][$leftSeat] !== null) {

                                    // If seat exists, check it isn't a selected seat
                                    if (!in_array($seatingPlan[($seat["row"] - 1)][$leftSeat], $selection)) {

                                        $blockedSeats[] = $seatingPlan[($seat["row"] - 1)][$leftSeat];

                                    }

                                }

                                // Check seat behind exists
                                if ($seatingPlan[($seat["row"] + 1)][$leftSeat] !== null) {

                                    // If seat exists, check it isn't a selected seat
                                    if (!in_array($seatingPlan[($seat["row"] + 1)][$leftSeat], $selection)) {

                                        $blockedSeats[] = $seatingPlan[($seat["row"] + 1)][$leftSeat];

                                    }

                                }

                            }


                            if($stop === true) {

                                break;

                            }

                        } else {

                            // Stopping loop as no seat exists to the left
                            break;

                        }

                        $l++;
                    }

                    $r = 1;
                    // Check right side
                    while($r <= $space) {

                        $stop = false;

                        $rightSeat = (($seat["seat_number"] - 1) + $r);
                        //die(var_dump($leftSeat));

                        // Check seat to the left exists
                        if ($seatingPlan[($seat["row"])][$rightSeat] !== null) {

                            // If seat exists, check it isn't a selected seat
                            if (!in_array($seatingPlan[($seat["row"])][$rightSeat], $selection)) {

                                $blockedSeats[] = $seatingPlan[$seat["row"]][$rightSeat];

                            } else {
                                //die("FAILED CHECK: ". $seatingPlan[($seat["row"])][$leftSeat]);
                                // Stopping loop as seat is a selected seat.
                                $stop = true;

                            }

                            if($r == 1) {

                                // Check seat behind exists
                                if ($seatingPlan[($seat["row"] - 1)][$rightSeat] !== null) {

                                    // If seat exists, check it isn't a selected seat
                                    if (!in_array($seatingPlan[($seat["row"] - 1)][$rightSeat], $selection)) {

                                        $blockedSeats[] = $seatingPlan[($seat["row"] - 1)][$rightSeat];

                                    }

                                }

                                // Check seat behind exists
                                if ($seatingPlan[($seat["row"] + 1)][$rightSeat] !== null) {

                                    // If seat exists, check it isn't a selected seat
                                    if (!in_array($seatingPlan[($seat["row"] + 1)][$rightSeat], $selection)) {

                                        $blockedSeats[] = $seatingPlan[($seat["row"] + 1)][$rightSeat];

                                    }

                                }

                            }

                            if($stop === true) {

                             break;

                            }

                        } else {

                            // Stopping loop as no seat exists to the left
                            break;

                        }

                        $r++;
                    }

                }


            }
            $blockedSeats = array_unique($blockedSeats);
            sort($blockedSeats);
            print "<pre>"; print_r($blockedSeats); print "</pre>";
            exit;

        }


        $userSelection1 = array(732,733);
        $userSelection2 = array(13, 14, 15, 23, 24);

        $spacer = 2;

        $cinema = $this->get("cinema");
        $screen = 23;

        return $response->withJson($cinema->seatingSocialDistancing($userSelection1, $screen, $spacer), 200);

       //print $cinema->getConfigItem("social_distancing")["value"];

    });



    });