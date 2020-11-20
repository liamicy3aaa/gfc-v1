<?php

/**
* Cinema Class
* This is the main class for the cinema system
* Author: Liam McClelland
* Copyright: � 2019 Gadgetfreak Systems.
*/

class cinema {
    
    protected $config;
    protected $conn;
    protected $seatingSizes = array("1" => "standard", "2" => "double", "50" => "standard", "99" => "space");
    
    /**
    * Constructor
    * 
    * @param mixed $config
    * @param mixed $database
    */
    
    public function __construct($config = array(), $database) {
        
        $this->config = $config;
        $this->conn = $database;
        

    }

    /**
    * Build promo banner
    * Builds the main banner on the front page using data from the database
    *
    * @return string 
    */
    public function buildPromoBanner() {

        // Step 1 - GET IDs of the films for films that have upcoming showings (Limit 4)
        $films = $this->getShowtimes(false, true, 6);

        if($films === false) {

            return "<div class='mb-5' style='min-height:300px; background-color:#000;'></div>";

        }

        $filmsSorted = array_values(array_unique($films["_films"]));

        $filmInfo = $this->getFilms($filmsSorted);

        $items = array();
        $indicators = array();

        foreach($filmInfo as $id => $data) {

            $position = array_search($data["id"], $filmsSorted);
            $active = (($position < 1) ? "active" : "");
            $indicators[$position] = "<li data-target=\"#whatsOnBanner\" data-slide-to=\"$position\" class=\"$active\"></li>";

            $fields = array(
                "%BANNER%",
                "%NAME%",
                "%DESC%",
                "%BTN%",
                "%FIRST%"
            );

            if(strlen($data["film_trailer"]) > 3) {

                $trailerBtn = "<button onclick=\"Cinema.openTrailer('" . $data["film_trailer"] . "');\" class='btn btn-light' style='border-radius:20px'>PLAY TRAILER</button>";

            } else {

                $trailerBtn = "<a class='btn btn-light' href='/film/" . cipher::encrypt($data["id"]) . "' style='border-radius:20px; text-shadow:none;'>BOOK NOW</a>";

            }

            $data = array($data["film_banner"], $data["film_name"], substr($data["film_desc"], 0, 150) . "...", $trailerBtn, $active);

            $items[$position] = str_replace($fields, $data, file_get_contents("../templates/partials/whats-on-banner-item.phtml"));

        }

        ksort($items);
        ksort($indicators);

        return str_replace(array("%items%", "%indicators%"), array(implode("", $items), implode("", $indicators)), file_get_contents("../templates/partials/whats-on-banner.phtml"));

    }

    /**
    * Get Cinema Information
    * Get the information for the cinema that is stored in the gfc_config table
    *
    * @return array 
    */
    public function getCinemaInfo() {

        $info = $this->conn->query("SELECT * FROM gfc_config WHERE `key` = 'INFO_cinemaName'")->fetchArray();


        $data = array(
            "name" => $info["value"],
            "id" => "1043"

        );

        return $data;

    }

    public function getConfigItem($key) {

        $query = $this->conn->query("SELECT * FROM gfc_config WHERE `key` = ?", $key);

        return (($query->numRows() < 1) ? false : $query->fetchArray());

    }
    
    public function createScreen($screenName, $status) {
        
        $query = $this->conn->query("INSERT INTO gfc_screens (screen_name,status) VALUES (?, ?)", $screenName, $status);
        
        return true;
        
    }
    
    public function deleteScreen($screenId) {
        
        
        $seatCount = $this->getSeatingInfoByScreen($screenId, false);
        
        
        if($seatCount >= 1) {
            
            $this->conn->query("DELETE FROM gfc_screens_seats WHERE screen_id = ?",  $screenId);    
            
        }
        
        $delete = $this->conn->query("DELETE FROM gfc_screens WHERE id = ?",  $screenId);
            
        if($delete->affectedRows() >= 1) {
            
            return true;
            
        } else {
            
            return false;
            
        }
        
    }
    
    /**
    * Get screen information
    * Get information about either particular screens or all screens on the system
    * 
    * @param mixed $ids
    * @return array
    */
    
    public function getScreens($ids = array()) {
        
        if(count($ids) >= 1) {
            
            $screenIds = implode(",", $ids);
            
            $film = $this->conn->query("SELECT * FROM gfc_screens WHERE id IN($screenIds)")->fetchAll();
            
        } else {
            
            $film = $this->conn->query("SELECT * FROM gfc_screens")->fetchAll();    
            
        }
        
        return $film;
        
    }
    
    public function screenExists($id) {
        
        $r = $this->conn->query("SELECT id FROM gfc_screens WHERE id = ?", $id)->numRows();
        
        return (($r < 1) ? false : true);
    }

    public function getScreenLastRow($id) {

        $result = $this->conn->query("SELECT MAX(seat_row) as 'row', MAX(seat_row_label) as 'row_label' FROM gfc_screens_seats WHERE screen_id = ?", $id)->fetchArray();

        $row = (($result["row"] === null) ? 0 : $result["row"]);
        $label = (($result["row_label"] === null) ? null : $result["row_label"]);

        return array("status" => true, "row" => $row, "row_label" => $label);

    }

    public function addScreenRows($params = array()) {

        $required = array("screenId", "rows", "seats", "seatLabel");

        foreach($required as $item) {

            if(!isset($params[$item])) {

                return array("status" => false, "error" => "Missing $item");

            }

        }

        // Get highest row number for the screen from the database
        $result = $this->getScreenLastRow($params["screenId"]);
        $startRow = ($result["row"] + 1);
        $startRowLabel = (($result["row_label"] === null) ? "A" : (++$result["row_label"]));
        $totalResults = ($params["rows"] * $params["seats"]);
        $count = 1;

        $SQL = "INSERT INTO gfc_screens_seats (screen_id, seat_row, seat_row_label, seat_number, seat_status, seat_type) VALUES ";

        for($increment = 1; $increment <= $params["rows"]; $increment++) {

            for($inc = 1; $inc <= $params["seats"]; $inc++) {

                $SQL .= "(";

                $SQL .= "$params[screenId],";
                $SQL .= "$startRow,";
                $SQL .= "'$startRowLabel',";
                $SQL .= "$inc,";
                $SQL .= "1,";
                $SQL .= "1";

                $SQL .= ")";

                if($count < $totalResults) {

                    $SQL .= ", ";

                }

                $count++;
            }

            $startRow++;
            $startRowLabel++;

        }

        //return array("status" => false, "error" => "params", "data" => $SQL);
        $this->conn->query($SQL);

        return array("status" => true);

    }
    
    public function reorderScreenRows($id, $rowOrder, $options = array()) {
        
        // Step 1 - Get the list of seats for the screens
        $seats = $this->getSeatingInfoByScreen($id, true);
        
        // Step 2 - Sort them by their row ID
        $screen = array();
        
        foreach($seats AS $pos => $seat) {
            
            if(!isset($screen[$seat["seat_row"]])) {
                
                $screen[$seat["seat_row"]] = array();
                
            }
            
            $screen[$seat["seat_row"]][] = $seat["id"];
            
        }

           //$sql = array();
        // Step 3 - Loop through each item in the order and update the seats row id
        foreach($rowOrder as $position => $row) {
            
            $newRow = ($position + 1);
            $seatIds = implode(",", $screen[$row]);
            
            $this->conn->query("UPDATE gfc_screens_seats SET seat_row = $newRow WHERE id IN($seatIds) AND screen_id = $id");
            //$sql[] = "UPDATE gfc_screens_seats SET seat_row = $newRow WHERE id IN($seatIds) AND screen_id = $id";
        }
        
        return true;
        
    }
    
    public function addFilm($data) {
        
        $required = array("film_name", "film_desc", "film_release", "film_release", "film_runtime");
        
        // Step 1 - Check we have all the required pieces of data
        foreach($required as $item) {
            
            if(!isset($data[$item])) {
                
                return array("status" => false, "error" => "missing_info", "error_desc" => "$item missing from data");
                
            }
        }
        
        // STEP 2 - Check a film with the same name doesn't already exist
        $count = $this->conn->query("SELECT id FROM gfc_films WHERE film_name = ?", $data["film_name"])->NumRows();
        
        if($count >= 1) {
            
            return array("status"=>false, "error" => "film_exists", "error_desc" => "A film with the same name already exists");
            
        }
        
        // Step 3 - Add film to database
        
            $r = $this->conn->query("INSERT INTO gfc_films (film_name, film_desc, film_rating, film_release, film_runtime, film_status) VALUES (?, ?, ?, ?, ?, ?)", array(
                $data["film_name"],
                $data["film_desc"],
                $data["film_rating"],
                $data["film_release"],
                $data["film_runtime"],
                0
            ));
            
        // Step 4 - Get id of the film we just created
        
            $id = $this->conn->query("SELECT id FROM gfc_films WHERE film_name = ?", $data["film_name"])->fetchArray()["id"];
            
            return array("status" => true, "filmId" => $id); 
        
    }
    
    public function updateFilmStatus($filmId, $status = false) {
        
        if($status === false) {
            
            return false;
            
        }
        
        $status = (($status) ? "1" : "0");
        
        $this->conn->query("UPDATE gfc_films SET film_status = ? WHERE id = ?", $status, $filmId);
        
        return true;
        
        
    }

    public function deleteFilm($filmId) {

        // Check id matches a film in the database
        $d = $this->getFilmData($filmId);

        if(count($d) < 1) {

            return array("status" => false, "error" => "Unable to locate film.");

        }

        // Check that the film doesn't have any active showings
        if(($this->getShowtimesByFilm($filmId, false)) >= 1) {

            return array("status" => false, "error" => "Cannot delete a film with an active showing");

        }

        // Remove film from database
        $r = $this->conn->query("DELETE FROM gfc_films WHERE id = ?", $filmId);

        if($r->affectedRows() < 1) {

            return array("status" => false, "error" => "Unable to delete film");

        } else {

            // Remove film thumbnail and banner from server
            $f1 = substr($d["film_thumbnail"], 1, strlen($d["film_thumbnail"]));
            $f2 = substr($d["film_banner"], 1, strlen($d["film_banner"]));

            if(file_exists($f1)) {

                unlink($f1);

            }

            if(file_exists($f2)) {

                unlink($f2);

            }

            return array("status" => true);

        }

    }

    public function addShowing($data) {

        $required = array(
            "date",
            "time",
            "film_id",
            "screen_id",
            "special_requirements",
            "ticket_config"
        );

        // Checking we have all required pieces of data
        foreach($required as $item) {

            if(!isset($data[$item])) {

                return array("status" => false, "error" => "missing_data", "error_desc" => "$item is missing from data.");

            }

        }

        // Checking data

        // #1 - date should just be numbers with dashes
        $check = str_replace(array("-", "/"), "", $data["date"]);

        if(!ctype_digit($check)) {

            return array("status"=>false, "error" => "invalid_date", "error_desc" => "Invalid date provided");

        }

        // #2 - time should just be numbers without colon
        $check = str_replace(":", "", $data["time"]);

        if(!ctype_digit($check)) {

            return array("status"=>false, "error" => "invalid_time", "error_desc" => "Invalid time provided");

        }

        // #3 - screen id should just be a number
        if(!ctype_digit($data["screen_id"])) {

            return array("status"=>false, "error" => "invalid_screen_id", "error_desc" => "Invalid screen_id provided");

        }

        // #4 - Film id should just be a number
        if(!ctype_digit($data["film_id"])) {

            return array("status"=>false, "error" => "invalid_film_id", "error_desc" => "Invalid film_id provided");

        }

        // #5 - Special requirements should just be alphanumeric characters
        $check = str_replace(" ", "", $data["special_requirements"]);
        if(strlen($check) >= 1 && !ctype_alnum($check)) {

            return array("status"=>false, "error" => "invalid_requirements", "error_desc" => "Invalid requirements provided");

        }

        // Build query
        $columns[] = "social_distancing";
        $columns = implode(",", $required);
        $info = "'" . $data["date"] . "',";
        $info .= "" . $data["time"] . ",";
        $info .= "" . $data["film_id"] . ",";
        $info .= "" . $data["screen_id"] . ",";
        $info .= "'" . $data["special_requirements"] . "',";
        $info .= "'" . $data["ticket_config"] . "'";
        // Social Distancing
        $info .= "" . (($this->getConfigItem("social_distancing")["value"] == 1) ? 1 : 0) . "";

        $r = $this->conn->query("INSERT INTO gfc_films_showtimes ($columns) VALUES ($info)");

        return array("status" => true);

    }

    
    /**
    * Get film data
    * Get information about a film
    *  
    * @param mixed $id
    * @return array
    */
    
    public function getFilmData($id = false) {
        
        if(!$id) {
            
            return false;
            
        }
        
        $film = $this->conn->query("SELECT * FROM gfc_films WHERE id = ?", $id)->fetchArray();
        
        return $film;    
        
    }

    public function filmExists($id = false){

        if(!$id) {
            return false;
        }

        $film = $this->conn->query("SELECT id FROM gfc_films WHERE id = ?", $id)->numRows();

        return (($film == 1) ? true : false);

    }
    
    /**
    * Get data for either particular films or all active films on the system.
    * 
    * @param mixed $ids
    */
    
    public function getFilms($ids = array(), $onlyActive = true) {
        
        if(count($ids) >= 1) {
            
            $filmIds = implode(",", $ids);
            
            $films = $this->conn->query("SELECT * FROM gfc_films WHERE id IN($filmIds)" . (($onlyActive === true) ? "AND film_status = 1" : ""))->fetchAll();
            
        } else {
        
            $films = $this->conn->query("SELECT * FROM gfc_films ". (($onlyActive === true) ? "WHERE film_status = 1" : ""))->fetchAll();
        
        }
        
        return $films;
        
    }
    
    public function getAllFilms() {
        
            $films = $this->conn->query("SELECT * FROM gfc_films")->fetchAll();

        return $films;
        
    }
    
    /**
    * Get ticket information
    * Get information about a ticket type on the system.
    * 
    * @param mixed $ids
    * @param mixed $onlyActive 
    * @return array
    */
    public function getTicketInfo($ids, $onlyActive = true) {
        
        $active = (($onlyActive) ? "AND ticket_status = 1" : "");
        
        $ids = implode(",", $ids);
        
        $r = $this->conn->query("SELECT * FROM gfc_ticket_types WHERE id IN($ids) $active")->fetchAll();
        
        $types = array();
        
        foreach($r as $index => $type) {
            
            $types[$type["id"]] = $type;    
            
        }
        
        return $types;
        
    }

    public function getFilmInfo($ids, $onlyActive = true) {

        $and = ((!is_string($ids)) ? "AND" : "");
        $active = (($onlyActive) ? "$and film_status = 1" : "");

        if(is_string($ids) && $ids == "*") {

            $sqlEnd = (($onlyActive === true && is_string($ids)) ? "" : "WHERE $active");

        } else {

            $ids = implode(",", $ids);
            $sqlEnd = "WHERE id IN($ids) $active";

        }


        $r = $this->conn->query("SELECT * FROM gfc_films $sqlEnd")->fetchAll();

        $types = array();

        foreach($r as $index => $type) {

            $types[$type["id"]] = $type;

        }

        return $types;

    }

    public function getScreenInfo($ids, $onlyActive = true) {

        $and = ((!is_string($ids)) ? "AND" : "");
        $active = (($onlyActive) ? "$and status = 1" : "");

        if(is_string($ids) && $ids == "*") {

            $sqlEnd = (($onlyActive === true && is_string($ids)) ? "" : "WHERE $active");

        } else {

            $ids = implode(",", $ids);
            $sqlEnd = "WHERE id IN($ids) $active";

        }


        $r = $this->conn->query("SELECT * FROM gfc_screens $sqlEnd")->fetchAll();

        $types = array();

        foreach($r as $index => $type) {

            $types[$type["id"]] = $type;

        }

        return $types;

    }




    public function getTicketInfoByBooking($id) {

        $booking = $this->getBookingInfo($id);

        // Loop through seat info and get the name for each ticket type needed
        $ticketTypes = json_decode($booking["booking_info"], true);

        $requiredTypes = array();

        foreach($ticketTypes as $type => $number) {

            if(intval($number) >= 1 && !in_array($type, $requiredTypes)) {

                $requiredTypes[] = $type;

            }

        }

        // Get ticket names
        $required = implode(",", $requiredTypes);

        $ticketInfo = $this->conn->query("SELECT id, ticket_label, ticket_cost FROM gfc_ticket_types WHERE id IN($required)")->fetchAll();

        $details = array();

        foreach($ticketInfo as $ticket) {

            $details[$ticket["id"]]["label"] = $ticket["ticket_label"];
            $details[$ticket["id"]]["cost"] = $ticket["ticket_cost"];
            $details[$ticket["id"]]["count"] = intval($ticketTypes[$ticket["id"]]);

        }

        return $details;

    }


    /**
    * Get show information
    * Get information about a particular show
    * 
    * @param mixed $id
    * @return array
    */
    
    public function getShowInfo($id) {
        
        // Clean item
        $showId = $this->conn->conn()->real_escape_string($id);
        
        $show = $this->conn->query("SELECT *, b.id AS 'showId', b.screen_id, b.time AS 'showtime', c.screen_name, b.`ticket_config` FROM gfc_films AS a INNER JOIN gfc_films_showtimes AS b ON a.id = b.film_id INNER JOIN gfc_screens AS c ON b.screen_id = c.id WHERE b.id = ?", $showId)->fetchArray();
        
        if(count($show) >= 1) {
            $show["ticket_config"] = json_decode($show["ticket_config"], true);


            return $show;

        } else {

            return false;

        }
             
    }
    
    /**
    * Create a booking
    * Create a booking for a particular show / film
    * 
    * @param mixed $data
    */
    
    public function createBooking($data) {
        
        $available = array(
            "film_id",
            "showtime_id",
            "booking_info",
            "booking_total",
            "booking_seats",
            "booking_seats_total",
            "booking_status",
            "booking_email",
            "booking_name",
            "booking_tickets_issued",
            "booking_method",
            "booking_used",
            "social_distancing"
        );

        $number = array(
            "booking_seats_total",
            "film_id",
            "showtime_id",
            "booking_total",
            "booking_ts"
        );

        // Social distancing (if active)
        $socialDistancing = $this->conn->query("SELECT screen_id, social_distancing as 'v' FROM gfc_films_showtimes WHERE id = ?", $data["showtime_id"])->fetchArray();
        if($socialDistancing["v"] == 1) {

            $data["social_distancing"] = $this->seatingSocialDistancing($data["booking_seats"], $socialDistancing["screen_id"]);

        }

        $columns = array();
        $values = array();

        // Generating content for certain columns
        $bookingCode = $this->booking_generateCode();

        foreach($data as $column => $value) {
            
            if(in_array($column, $available)) {

                if(in_array($column, array("booking_info", "booking_seats", "social_distancing"))){

                    $val = "'" . json_encode($value) . "'";

                } elseif(!in_array($column, $number)) {

                    $val = "'" . $value . "'";

                } else {

                    $val = $value;

                }
                
                $columns[] = $column;
                $values[] = $val;
                
            }   
            
        }

        // Booking reference
        $columns[] = "booking_reference";
        $values[] = "'" . $bookingCode . "'";

        // Booking time
        $columns[] = "booking_ts";
        $values[] = time();

        //print "<pre>"; print_r($columns); print "</pre><br/>";
        //print "<pre>"; print_r($values); print "</pre>";
        //exit();

        // Building query
        $required = implode(",", $columns);
        $values = implode(",", $values);

        //exit("INSERT INTO gfc_bookings ($required) VALUES ($values);");
        
        $r = $this->conn->query("INSERT INTO gfc_bookings ($required) VALUES ($values);");
        
        return array("status" => true, "reference" => $bookingCode);
        
    }
    
    /**
    * Check if booking exists
    * 
    * @param mixed $bookingId
    * @return boolean
    */
    
    public function bookingExists($bookingId) {
        
        $r = $this->conn->query("SELECT ID FROM gfc_bookings WHERE booking_reference = ?", $bookingId)->numRows();
        
        if($r >= 1) {
            
            return true;
            
        } else {
            
            return false;
            
        }
        
    }

    public function getMovePerformanceShowings($show, $film) {

        $q1 = "SELECT a.*, c.screen_name, count(d.id) as screen_seats FROM gfc_films_showtimes as a INNER JOIN gfc_screens as c ON a.screen_id = c.id inner JOIN gfc_screens_seats as d ON c.id = d.screen_id WHERE NOT a.id = ? AND a.film_id = ? AND a.time >= ? GROUP BY a.id";
        $q2 = "SELECT count(booking_seats_total) as 'records', sum(booking_seats_total) as 'booked_seats' FROM gfc_bookings WHERE showtime_id = ? AND booking_status IN (\"PAID\", \"RESERVED\")";

        $r1 = $this->conn->query($q1, $show, $film, time())->fetchAll();

        $data = array();

        foreach($r1 as $id => $item) {

            $data[$item["id"]] = $item;

            $r2 = $this->conn->query($q2, $item["id"])->fetchArray();

            $data[$item["id"]]["available"] =  (($r2["records"] >= 1) ? ($item["screen_seats"] - $r2["booked_seats"]) : $item["screen_seats"]);

        }

        return $data;


    }
    
    public function getBookings() {
        
        return $this->conn->query("SELECT a.*, b.film_name FROM gfc_bookings AS a INNER JOIN gfc_films AS b ON a.film_id = b.id")->fetchAll();
        
    }
    
    /**
    * Get booking information
    * Get information about a particular booking
    * 
    * @param mixed $bookingId
    * @return array|boolean
    */
    
    public function getBookingInfo($bookingId) {
        
        if(!$this->bookingExists($bookingId)) {
            
            return false;
            
        }
        
        $booking = $this->conn->query("SELECT * FROM gfc_bookings WHERE booking_reference = ?", $bookingId);
        $total = $booking->numRows();
        
        if($total < 1) {
            
            return false;
            
        } else {
            
            return $booking->fetchArray();
            
        }
        
    }
    
    public function updateBooking($bookingId, $data) {
        
              $available = array(
            "film_id",
            "showtime_id",
            "booking_info",
            "booking_total",
            "booking_seats",
            "booking_seats_total",
            "booking_status",
            "booking_email",
            "booking_name",
            "booking_tickets_issued",
            "booking_method",
            "booking_used",
            "booking_phone" 
        );

        $number = array(
            "booking_seats_total",
            "film_id",
            "showtime_id",
            "booking_total",
            "booking_ts"
        );
        
        $updateString = "";
        $x = 0;
        $total = count($data);
        
        foreach($data as $column => $value) {
            
            if(in_array($column, $available)) {

                if(in_array($column, array("booking_info", "booking_seats"))){

                    $val = "'" . json_encode($value) . "'";

                } elseif(!in_array($column, $number)) {

                    $val = "'" . $value . "'";

                } else {

                    $val = $value;

                }
                
                $updateString .= (($x >= 1 && $x !== $total) ? ",": "") . " " . $column . " = " . $val;
                
                $x++;
                
            }   
            
        }
        $sql = "UPDATE gfc_bookings SET$updateString WHERE booking_reference = ?";
        
        //die($sql);
        
        $update = $this->conn->query($sql, $bookingId)->affectedRows();
        
        /*if($update > 0) {
            
            return true;
                
        } else {
            
            return false;
            
        }*/

        return true;
              
    }
    
    public function cancelBooking($bookingId, $refund = "full") {
        
        $this->conn->query("UPDATE gfc_bookings SET booking_status = 'cancelled' WHERE booking_reference = ?", $bookingId);

        $booking_id = $this->getBookingInfo($bookingId);
        $filmInfo = $this->getFilms(array($booking_id["film_id"]));
        $cinemaInfo = $this->getCinemaInfo();
        $showInfo = $this->getShowInfo($booking_id["showtime_id"]);
        $transactionInfo = $this->conn->query("SELECT * FROM gfc_transactions WHERE booking_id = ?", $booking_id["id"])->fetchArray();

        // send confirmation email
        $email = new email($this);

        $email->setTemplate("booking_cancellation");

        $email->addRecipient($booking_id["booking_email"], $booking_id["booking_name"]);

        $email->setSubject("Booking cancelled: $bookingId");

        // Getting seat Ids
        $seats = $this->getSeatingInfo(json_decode($booking_id["booking_seats"], true));

        $seatLabels = array();

        foreach($seats as $seat) {

            $seatLabels[] = $seat["seat_row_label"] . $seat["seat_number"];

        }

        // Generating refund message
        setlocale(LC_MONETARY,"en");

        switch($refund) {

            case "full":
                $refundMessage = "A full refund of <b>&pound;" . money_format("%i", $booking_id["booking_total"]) . "</b> has been given and you should receive this amount within the next 5 days.";
                break;

            case "partial":
                $refundMessage = "A partial refund of <b>&pound;" . money_format("%i", $transactionInfo["refund_amount"]) . "</b> has been given and you should receive this amount within the next 5 days.";
                break;

            case "none":
                $refundMessage = "No refund has been given for this cancellation. If you feel this is incorrect, then please contact " . $cinemaInfo["name"] .".";
                break;

        }

        // Splitting card info
        $card = explode(":", $transactionInfo["payment_type"]);


        $email->addContent(array(
            "%CINEMA%" => $cinemaInfo["name"],
            "%TIME%" => date("l jS F H:i:s", time()),
            "%SHOWTIME%" => date("l jS F H:i", $showInfo["time"]),
            "%BOOKINGREF%" => $bookingId,
            "%BOOKING_NAME%" => $booking_id["booking_name"],
            "%FILM%" => $filmInfo[0]["film_name"],
            "%SCREEN%" => $showInfo["screen_name"],
            "%SEATS%" => implode(",", $seatLabels),
            "%REFUND_INFO%" => $refundMessage,
            "%CARD_TYPE%" => $card[0],
            "%CARD_LAST4%" => $card[1],
            "%COST%" => money_format("%i", $booking_id["booking_total"])
        ));

        $email->send();


        
        //$this->conn->query("DELETE FROM gfc_bookings WHERE booking_reference = ?", $bookingId);
        
        return true;  
        
    }
    
    public function deleteBooking($bookingId) {
              
        $this->conn->query("DELETE FROM gfc_bookings WHERE booking_reference = ?", $bookingId);
        
        return true;  
        
    }

    public function sendBookingConfirmation($id, $contact = false) {

        $booking_id = $this->getBookingInfo($id);
        $filmInfo = $this->getFilms(array($booking_id["film_id"]));
        $cinemaInfo = $this->getCinemaInfo();
        $showInfo = $this->getShowInfo($booking_id["showtime_id"]);
        $transaction = $this->conn->query("SELECT * FROM gfc_transactions WHERE booking_id = ? LIMIT 1", $booking_id["id"])->fetchArray();

        // send confirmation email
        $email = new email($this);

        $email->setTemplate("booking_confirmation");

        if($contact !== false) {

            $email->addRecipient($contact);

        } else {

            $email->addRecipient($booking_id["booking_email"], $booking_id["booking_name"]);

        }

        $email->setSubject("Booking confirmed: $id");

        // Getting seat Ids
        $seats = $this->getSeatingInfo(json_decode($booking_id["booking_seats"], true));

        $seatLabels = array();

        foreach($seats as $seat) {

            $seatLabels[] = $seat["seat_row_label"] . $seat["seat_number"];

        }

        // Splitting card info
        $card = explode(":", $transaction["payment_type"]);

        setlocale(LC_MONETARY,"en");
        $email->addContent(array(
            "%CINEMA%" => $cinemaInfo["name"],
            "%TIME%" => date("l jS F H:i:s", $booking_id["booking_ts"]),
            "%SHOWTIME%" => date("l jS F H:i", $showInfo["time"]),
            "%BOOKINGREF%" => $id,
            "%BOOKING_NAME%" => $booking_id["booking_name"],
            "%FILM%" => $filmInfo[0]["film_name"],
            "%SCREEN%" => $showInfo["screen_name"],
            "%SEATS%" => implode(",", $seatLabels),
            "%CARD_TYPE%" => $card[0],
            "%CARD_LAST4%" => $card[1],
            "%COST%" => money_format("%i", $booking_id["booking_total"])
        ));

        /*
        $html = "Hello " . $booking_id["booking_name"] . ",<br/>";
        $html .= "Thanks for booking to see " . $filmInfo[0]["film_name"] . ".<br/><br/>";
        $html .= "Your booking reference is <span style='font-weight:bold'>" . $id . "</span> which you can use to collect your tickets when you arrive at the venue.";
        $html .= "<br/><br/>Kind regards, GFC Cinemas";


        $email->addContent(array(
            "%CONTENT%" => $html
        ));*/

        $email->send();

        return true;

    }

    public function getShowtimesByDate($date) {

        $r = $this->conn->query("SELECT a.*, b.screen_name as 'screen_name', c.film_name as 'film_name', c.film_runtime as 'runtime' FROM gfc_films_showtimes as a INNER JOIN gfc_screens as b ON a.screen_id = b.id INNER JOIN gfc_films as c ON a.film_id = c.id WHERE date = ?", $date)->fetchAll();

        return $r;

    }
    
    public function getShowtimesByScreen($screenId, $includeData = true) {
        
        $time = time();
        
        $columns = (($includeData) ? "*" : "count(id) as 'total'");
        
        $query = $this->conn->query("SELECT $columns FROM gfc_films_showtimes WHERE screen_id = ? AND time >= ? ORDER BY time ASC", $screenId, $time);    
        
        if($includeData) {
            
            return $query->fetchAll();
            
        } else {
            
            return $query->fetchArray()["total"];
            
        }
    }
    
    public function getShowtimesByFilm($filmId, $includeData = true, $status = "upcoming") {
        
        $time = time();
        
        $columns = (($includeData) ? "*" : "count(id) as 'total'");

        if($status == "all") {

            $query = $this->conn->query("SELECT $columns FROM gfc_films_showtimes WHERE film_id = ? ORDER BY time ASC", $filmId);

        } else {

            $query = $this->conn->query("SELECT $columns FROM gfc_films_showtimes WHERE film_id = ? AND time >= ? ORDER BY time ASC", $filmId, $time);

        }

        if($includeData) {
            
            return $query->fetchAll();
            
        } else {
            
            return $query->fetchArray()["total"];
            
        }
    }

    public function getShowtimes($id = false, $onlyActive = false, $limit = false, $unique = false) {

        $time = time();
        $limit = (($limit !== false) ? " LIMIT $limit" : "");
        $active = (($onlyActive === true) ? "AND b.film_status = 1" : "");
        
        if($id !== false) {
            
            // List show times for a specific film
            $times = $this->conn->query("SELECT a.* FROM gfc_films_showtimes as a INNER JOIN gfc_films as b ON a.film_id = b.id WHERE a.film_id = ? AND a.time > ? $active ORDER BY time ASC $limit", $id, $time)->fetchAll();
            
            return $times;
            
        } else {
            
            // List all available showtimes by film id
            
            $times = $this->conn->query("SELECT a.id, a.film_id, a.date, a.time FROM gfc_films_showtimes as a INNER JOIN gfc_films as b ON a.film_id = b.id WHERE a.`time` > ? $active ORDER BY time ASC $limit", $time)->fetchAll();
            
            $data = array();
            $filmIds = array();

            if(count($times) < 1) {

                return false;

            }
            
            foreach($times as $index => $time) {
                
                if(!isset($data[$time["film_id"]])) {
                    
                    $data[$time["film_id"]] = array();
                    
                }
                    
                    $data[$time["film_id"]][] = $time;
                    $filmIds[] = $time["film_id"];
   
                
            }
            
            $data["_films"] = $filmIds;
            
            return $data;
            
            
        }
        
    }
    
    public function getFilmsByName($name) {
        
        // Cleaning variable
        $query = "%" . ($this->conn->conn()->real_escape_string($name)) . "%";
        
        $films = $this->conn->query("SELECT * FROM gfc_films WHERE film_name LIKE ?", $query)->fetchAll();
        
        return $films;
        
    }
    
    public function getFilmsByReleaseDate($month, $year) {
        
        // Converting two string dates to unixtimestamp
        $totalDays = cal_days_in_month(CAL_GREGORIAN,$month,$year);
        $startDate = strtotime(("01-" . $month . "-" . $year));
        $endDate = strtotime(($totalDays . "-" . $month . "-" . $year));
        
        // Searching database
        $films = $this->conn->query("SELECT * FROM gfc_films WHERE film_release BETWEEN ? AND ?", $startDate, $endDate)->fetchAll();
        
        return $films;
        
    }

    public function buildFilmList($films) {

        $filmList = "";

        $fields = array(
                    "%THUMBNAIL%",
                    "%FILMID%",
                    "%FILMNAME%",
                    "%FILMDESC%",
                    "%SHOWTIMES%",
                    "%RUNTIME%",
                    "%RATING%"
                 );

        $template = file_get_contents("../templates/partials/film-card.phtml");

        foreach($films as $index => $film) {

            $data = array(
                $film["film_thumbnail"],
                cipher::encrypt($film["id"]),
                $film["film_name"],
                ((strlen($film["film_desc"]) > 250) ? substr($film["film_desc"], 0, 250). "..." : $film["film_desc"]),
                $this->buildShowtimes($film, 1),
                $film["film_runtime"],
                $film["film_rating"]
            );

            $filmList .= str_replace($fields, $data, $template);



        }

        return $filmList;

    }

    public function buildShowtimes($film, $limit = false) {

        if($limit !== false) {

            $break = $limit;

        }

        $days = array();
        $count = 0;

        if(count($film["showtimes"]) < 1) {

            if($limit !== false) {
                
                return "<a href='film/" . cipher::encrypt($film["id"]) . "' class='btn btn-primary mt-1'>More showtimes ></a>";
                
            } else {
                
                return "<small>No show times</small>";
                                
            }

        }

        foreach($film["showtimes"] as $showtime) {

            if(!isset($days[$showtime["date"]])) {

                $days[$showtime["date"]] = array();

            }

            $days[$showtime["date"]][] = $showtime;

            $count++;

            if(isset($break) && $count >= $break) {

                break;

            }

        }

        $html = "";

        foreach($days as $date => $day) {

            $date = date("l jS F", strtotime($date));

            $html .= "<p class='pt-2 mb-1'>$date</p>";
            
            foreach($day as $show) {

                //$bookingURL = "/booking/new/" . cipher::encrypt($show["film_id"]) . "/" . cipher::encrypt($show["id"]); // V1
                $bookingURL = "/booking/v2/new/" . cipher::encrypt($show["id"]); // V2

                $message = "onclick='alert(\"This show is fully booked.\")'";
                $config = (($this->availableSeats($show["id"])["available"] < 1) ? "href='Javascript:void(0);' class='btn btn-danger' $message" : "href='" . $bookingURL . "' class='btn btn-primary'");

                
                $time = date("H:i", $show["time"]);

                $html .= "&nbsp;<a $config>$time</a>";
                
            }

            if($limit !== false) {

                $html .= "&nbsp;<a href='film/" . cipher::encrypt($show["film_id"]) . "' class='btn btn-primary'>More showtimes ></a>";

            }

            $html .= "<br/>";
            
            
        }

        return $html;




    }

    public function createTicket($data, $options = array()) {



        $required = array("ticket_label", "seats", "ticket_status", "ticket_cost");
        $configItems = array("proof", "seats");

        foreach($required as $item) {

            if(!isset($data[$item])) {

                return array("status" => false, "error" => "missing_info", "error_desc" => "Missing $item from request.");

            }

        }

        // building data to turn into query
        $sqlData = array(
            "ticket_config" => array()
        );
        $sqlColumns = array("ticket_config");
        $sqlValues = array("ticket_config" => "");

        foreach($data as $column => $value) {

                if(in_array($column, $configItems)) {

                    $sqlData["ticket_config"][$column] = $value;

                } else {

                    $sqlColumns[] = $column;
                    $sqlData[$column] = $value;

                }

        }

        $x = 0;
        foreach($sqlData as $column => $data) {

            $comma = (($x >= 1) ? "," : "");

            if($column == "ticket_config") {

                $sqlValues = "$comma '" . json_encode($data, true) . "'";

            } else {

                $sqlValues .= "$comma" . ((is_string($data)) ? "'$data'" : $data) . "";

            }

            $x++;
        }

        $query = "INSERT INTO gfc_ticket_types (" . implode(",", $sqlColumns) . ") VALUES ($sqlValues)";
        //http_response_code(400);
        //print "<pre>"; print_r($query); print "</pre>";
        //exit;

        $this->conn->query($query);

        return array("status" => true);

    }
    
    public function getTicketTypes($ids = array(), $onlyActive = true) {
        
        if($onlyActive) {
            
            $addonPre = "AND ";
            $addon = "ticket_status = 1";
            
        } else {
            $addonPre = "";
            $addon = "ticket_status IN(1,0)";
            
        }
        
        if(count($ids) >= 1) {
            
            $types = implode(",", $ids);
            
           $data = $this->conn->query("SELECT * FROM gfc_ticket_types WHERE id IN($types) $addonPre$addon")->fetchAll();    
            
        } else {
            
           $data = $this->conn->query("SELECT * FROM gfc_ticket_types WHERE $addon")->fetchAll(); 
            
        }
        
        return $data;
        
    }
    
    public function getBookingTicketInfo($bookingId) {
        
        $booking = $this->getBookingInfo($bookingId);
        $bookingSeats = json_decode($booking["booking_seats"], true);
        $seatLabels = $this->getSeatingInfo($bookingSeats);
        $data = array();
        $ticket = json_decode($booking["booking_info"], true);
        $ticketTypes = array();
        
        // Get id for each type of ticket if at least one ticket is booked for this ticket type.
        foreach($ticket as $id => $total) {
            
            if($total >= 1) {
                
                $ticketTypes[] = $id;
                
            }
                        
        }
        
        // Get ticket info for 
        $ticketInfo = $this->getTicketInfo($ticketTypes, false);
        $i = 0;
        
        foreach($ticketInfo as $id => $type) {

            $config = json_decode($type["ticket_config"], true);
            
            // Get seats
            $end = ($i + ($config["seats"] * $ticket[$id]));
            $seats = array();
            $seatNumbers = "";
            
            while($i < $end) {
                
                $seats[] = $bookingSeats[$i];
                $seatNumbers .= $seatLabels[$i]["seat_row_label"] . $seatLabels[$i]["seat_number"] . ",";
                $i++;
            }
            
            $data[] = array(
              "id" => $id,
              "item" => $type["ticket_label"],
              "units" => $ticket[$id],
              "price" => $type["ticket_cost"] . ".00",
              "total" => ($type["ticket_cost"] * $ticket[$id]) . ".00",
              "seat_total" => ($config["seats"] * $ticket[$id]),
              "seats" => $seats,
              "seat_labels" => $seatNumbers
            );
            
        }
        
        return $data;      
        
        
        
    } 
    
    public function getSeatingInfo($seats) {
        
        $seating = implode(",", $seats);
        
        $data = $this->conn->query("SELECT * FROM gfc_screens_seats WHERE id IN($seating)")->fetchAll();
        
        return $data;
        
    }
    
    public function getSeatingInfoByScreen($screenId, $includeData = true) {
        
        $columns = (($includeData) ? "*": "count(id) as 'total'");
        
        $seats = $this->conn->query("SELECT $columns FROM gfc_screens_seats WHERE screen_id = ?", $screenId);

        if($includeData) {
            
            return $seats->fetchAll();
            
        } else {
            
            return $seats->fetchArray()["total"];
                        
        }
        
    }
    
    public function buildTicketScreen($types = false) {
        
        if(!$types) {
            
            return false;
            
        }
        
        $tickets = $this->getTicketTypes($types);
        
        
        $html = "";
        
        foreach($tickets AS $index => $ticket) {
            
            $config = json_decode($ticket["ticket_config"], true);
            
            $html .= "<tr>";
            
                $html .= "<td class='col-8 col-sm-10 col-md-9'>" . $ticket["ticket_label"];
                
                
                // If proof of entitlement is required, show the warning message
                
                if(isset($config["proof"]) && $config["proof"] == 1) {
                    
                    $html .= "<div>";

                    if($ticket["cea_free"] == 1) {

                        $html .= "<span class='badge badge-primary'>CEA Card required</span>";

                    } else {

                        $html .= "<small class='text-secondary'>Proof of entitlement may be required</small>";

                    }
                            
                    $html .= "</div>";
                    
                }
                       
                $html .= "</td>";
                
                $html .= "<td class='col-1 col-sm-1 col-md-1 text-right'>&pound;" . $ticket["ticket_cost"] . "</td>";
                
                $html .= "<td class='col-3 col-sm-1 col-md-2 text-right'>";
                
                    $html .= "<select name='" . $ticket["id"] . "' class='form-control input-sm ticket-option' style='width:65px;' data-tickettype='" . cipher::encrypt($ticket["id"]) . "'>";
                    
                        // Build list ticket quantity options
                        for($x = 0; $x <= 8; $x++) {
                            
                            $html .= "<option value='$x'>$x</option>";
                        
                        }
                        
                    $html .= "</select>";
                
                $html .= "</td>";
                
                
                $html .= "<td class='col-1 col-md-1 text-right d-none d-sm-table-cell ticket-option-" . cipher::encrypt($ticket["id"]) ."'></td>";
                
            $html .= "</tr>";
            
        }
        
        return $html;
         
    }
    
    public function buildDetailsScreen($show, $bookingId) {
       
       $html = "<form id='detailsScreen' class='needs-validation' novalidate>";
       
            $html .= "<div class='form-group row'>";
       
                $html .= "<label class='text-sm-right col-sm-4 col-form-label' for='name'>Name</label>";
       
                $html .= "<div class='col-sm-6'>";
                
                    $html .= "<input class='form-control val-input' id='name' data-validation='length custom' data-validation-regexp='^([a-zA-Z ]+)$' data-validation-length='min1' type='text' placeholder='Please enter your name'/>";
                    
                    $html .= "<div class='invalid-feedback'>Please enter your full name</div>";
                
                $html .= "</div>";
                
            $html .= "</div>";
            
            $html .="<div class='form-group row'>";
            
                $html .= "<label class='text-sm-right col-sm-4 col-form-label' for='phone'>Phone number</label>";
                
                $html .= "<div class='col-sm-6'>";
                
                    $html .= "<input class='form-control val-input' id='phone' data-validation='custom' data-validation-regexp='^([0-9]+)$' type='tel' placeholder='Please enter your contact number'/>";
                    
                    $html .= "<div class='invalid-feedback'>Please enter a phone number</div>";
                    
                $html .= "</div>";
                
            $html .= "</div>";
            
            $html .="<div class='form-group row'>";
                        
                $html .= "<label class='text-sm-right col-sm-4 col-form-label' for='email'>Email</label>";
            
                $html .= "<div class='col-sm-6'>";
            
                    $html .= "<input name='email' class='form-control val-input' id='email' data-validation='email' type='email' placeholder='Please enter your email'/>";
                    
                    $html .= "<div class='invalid-feedback'>Please enter your email</div>";
            
                $html .= "</div>";
            
            $html .= "</div>";
            
            $html .="<div class='form-group row'>";
            
                $html .= "<label class='text-sm-right col-sm-4 col-form-label' for='reEmail'>Re-enter email</label>";
            
                $html .= "<div class='col-sm-6'>";
            
                    $html .= "<input class='form-control val-input' id='reEmail' data-validation='length confirmation' data-validation-length='min1' data-validation-confirm='email' type='email' placeholder='Please re-enter your email'/>";
            
                    $html .= "<div class='invalid-feedback'>Please enter the same email as the one above.</div>";
            
                $html .= "</div>";
                
            $html .= "</div>";
            
       $html .= "</form>";
       
       return $html;
        
    }
    
    public function buildConfirmationScreen($show, $bookingId) {
        
        $booking = $this->getBookingInfo($bookingId);
        
        if(!$booking) {
            
            return "<h1 class='text-center' style='color:red;'>An error occurred while fetching your booking information.</h1>";
            
        }
        
        $seats = json_decode($booking["booking_seats"], true);
        
        $seatInfo = $this->getSeatingInfo($seats);
        
        // Loop through seat info and get the name for each ticket type needed
        $ticketTypes = json_decode($booking["booking_info"], true);
        
        $requiredTypes = array();
        
        foreach($ticketTypes as $type => $number) {
            
            if(intval($number) >= 1 && !in_array($type, $requiredTypes)) {
                
                $requiredTypes[] = $type;
                
            }    
            
        }
        
        // Get ticket names
        $required = implode(",", $requiredTypes);
        
        $ticketInfo = $this->conn->query("SELECT id, ticket_label, ticket_cost FROM gfc_ticket_types WHERE id IN($required)")->fetchAll();

        $details = array();
        
        //var_dump($ticketTypes);
        //print "<br/>";
        //exit;
        
        foreach($ticketInfo as $ticket) {
            
            $details[$ticket["id"]]["label"] = $ticket["ticket_label"];
            $details[$ticket["id"]]["cost"] = $ticket["ticket_cost"];
            $details[$ticket["id"]]["count"] = intval($ticketTypes[$ticket["id"]]);
            
        }
        
        //var_dump($details);
        //exit;
                    
        // Get show / film info
        $showInfo = $this->getShowInfo($show);
        
        $filmInfo = $this->getFilms(array($showInfo["film_id"]));
        
        // START OF HTML
        
        $html = "<div class='card-header bg-light'>";
        
            $html .= "<p class='font-weight-bold h4 mt-2'>";
            
                $html .= "Thanks for booking. A confirmation email has been sent to you.";
                
            $html .= "</p>";
            
            $html .= "<p class='text-muted'>Confirmation: " . $booking["booking_reference"] . "</p>"; 
            
        $html .= "</div>";
        
        $html .= "<div class='card-body container'>";
        
            $html .= "<div class='row'>";
        
                $html .= "<div class='col-12 col-md-6'>";
                    
                    $html .= "<h4>Booking details:</h4>";
                    
                        $html .= "<table class='table table-sm'>";
                        
                            $html .= "<tbody>";
                            
                                $html .= "<tr>";
                                    
                                    $html .= "<td class='text-right border-0'>Film:</td>";
                                    $html .= "<td class='font-weight-bold border-0'>" . $filmInfo[0]["film_name"] . " (" . $filmInfo[0]["film_rating"] . ")</td>";
                                    
                                $html .= "</tr>";
                                
                                $html .= "<tr>";
                                
                                    $html .= "<td class='text-right border-0'>Date & time:</td>";
                                    $html .= "<td class='font-weight-bold border-0'>" . date("l jS F H:i", $showInfo["time"]) . "</td>";
                                    
                                $html .= "</tr>";

                                $html .= "<tr>";

                                    $html .= "<td class='text-right border-0'>Screen:</td>";

                                    $html .= "<td class='font-weight-bold border-0'>" . $showInfo["screen_name"] ."</td>";
                                
                                $html .= "</tr>";

                                $html .= "<tr>";
                                
                                    $html .= "<td class='text-right border-0'>Seats:</td>";
                                    
                                        $html .= "<td class='font-weight-bold border-0'>";
                                                  
                                                  foreach($seatInfo as $seat) {
                                                      
                                                      $html .= "Row " . $seat["seat_row_label"] . " - Seat " . $seat["seat_number"] . "<br/>";
                                                      
                                                  }
                                                
                                        $html .= "</td>";
                                        
                                $html .= "</tr>";
                                
                            $html .= "</tbody>";
                            
                        $html .= "</table>";
                        
                        $html .= "<hr class='d-block d-md-none'/>";
                            
                $html .= "</div>";
                
                $html .= "<div class='col-12 col-md-6'>";
                    
                    $html .= "<h4>Cost:</h4>";
                    
                        $html .= "<table class='table table-sm mx-auto' style='max-width:300px;'>";
                        
                            $html .= "<tbody>";
                            
                            $totalCost = 0;
                                
                                    foreach($details as $type => $data) {
                                        
                                        
                                        $number = $data["count"];
                                        
                                        if($number >= 1) {
                                        
                                            for($x = 0; $x < $number; $x++) {
                                                
                                                $html .= "<tr>";
                                                
                                                    $html .= "<td class='border-0'>" . $data["label"] . "</td>";
                                                    $html .= "<td class='border-0 text-right'>&pound;" . $data["cost"] . ".00</td>";
                                            
                                                $html .= "</tr>";
                                                
                                                $totalCost += $data["cost"];
                                            }
                                        
                                        }
      
                                    }
                                    
                                    // Total cost
                                    $html .= "<tr class='bg-secondary text-white'>";
                                    
                                        $html .= "<td class='border-0'>Total:</td>";
                                        $html .= "<td class='border-0 text-right'>&pound;" . $totalCost . ".00</td>";
     
                                $html .= "</tbody>";
                            
                        $html .= "</table>";
                
                $html .= "</div>";
                
            $html .= "</div>";
            
        $html .= "</div>";    
        
        return $html;
        
    }
    
    public function buildScreenPlan($screen, $settings = false) {
        
        $allSeats = $this->conn->query("SELECT * FROM gfc_screens_seats WHERE screen_id = ? AND seat_status = 1", $screen)->fetchAll();
        $seatingPlan = array();
        
        foreach($allSeats as $index => $seat) {

            $allSeats[$index]["status"] = "GREEN";

            if(!isset($seatingPlan[$seat["seat_row"]])) {

                $seatingPlan[$seat["seat_row"]] = array();

            }

            $seatingPlan[$seat["seat_row"]][] = $allSeats[$index];

        }
        ksort($seatingPlan);
        
        $html = "";
        $highest = 0;                
        $seatingSizes = $this->seatingSizes;

        foreach($seatingPlan as $row => $seats) {

            // Start by creating the start of the row.
            $html .= "<tr class='screen-row' data-rowid='$row'>";

                // Building the seats
                foreach($seats as $seat) {

                    if(count($seats) > $highest) {

                        $highest = count($seats);

                    }

                    // Start of seat
                    $html .= "<td class='screen-seat seat-" . $seatingSizes["$seat[seat_type]"] . "' data-seatId='" . cipher::encrypt($seat["id"]) . "' " . (($seat[seat_type] == "99") ? "data-seatType='space'" : "data-seatType='seat'") . ">";

                        // Insert seat image
                        $html .= "<img src='/assets/images/seats/" . $seat["seat_type"] . "-seat_" . $seat["status"] . ".png'/><br/>";

                        // Insert seat label
                        if(!in_array($seat["seat_type"], array("99"))) {
                            $html .= $seat["seat_row_label"] . $seat["seat_number"];
                        }

                    $html .= "</td>";

                }


            // Ending the row
            $html .= "</tr>";

        }
        
        // Adding default extra rows
        
            $html .= "<tr class='screen-row not-sortable' data-rowid='SCREEN'>";
            
                $html .= "<td colspan='" . $highest . "' style='border:1px solid lightgrey; width:100%;' class='text-center p-1'>SCREEN</td>";
                        
            $html .= "</tr>";
            
            $html .= "<tr class='screen-row d-table-row d-sm-none not-sortable'>";
            
                $html .= "<td colspan='0' class='text-center p-1'> <---- Scroll ----> </td>";
                
            $html .= "</tr>";
            
        return array(
            "html" => $html,
            "highest" => $highest
        );
        
        
        
    }

    /** Use this to retrieve a booking by seat id for a specific showing */
    public function getBookingBySeat($show, $seatId) {

        $validStatuses = implode(",", array(
            "'reserved'",
            "'reserved_temp'",
            "'complete'",
            "'awaiting_payment'",
            "'GFC_ADMIN'",
            "'PAID'"
        ));

        //die("SELECT id, booking_seats FROM gfc_bookings WHERE showtime_id = ? AND booking_status IN (" . $validStatuses . "");
        // STEP 1 - Get all bookings that hold tickets for this showing

        $result = $this->conn->query("SELECT booking_reference, booking_seats FROM gfc_bookings WHERE showtime_id = ? AND booking_status IN (" . $validStatuses . ")", $show)->fetchAll();

        // STEP 2 - Combine all seat ids from the bookings
        $bookedSeats = array();

        foreach($result as $index => $booking) {

            $seats = json_decode($booking["booking_seats"], true);

            foreach($seats as $seat) {

                if($seat == "$seatId") {
                    //print "<pre>"; print_r($booking); print "</pre>";
                    //die($booking["booking_reference"]);

                    return $this->getBookingInfo($booking["booking_reference"]);
                    break;

                }

            }

        }

        return false;

    }

    public function buildSeatingPlan($show, $ticketsRequired) {

        // Get show information
        $details = $this->getShowInfo($show);
        $validStatuses = implode(",", array(
            "'reserved'",
            "'reserved_temp'",
            "'complete'",
            "'awaiting_payment'",
            "'GFC_ADMIN'",
            "'PAID'"
        ));

        //die("SELECT id, booking_seats FROM gfc_bookings WHERE showtime_id = ? AND booking_status IN (" . $validStatuses . "");
        // STEP 1 - Get all bookings that hold tickets for this showing

        $socialDistancing = (($details["social_distancing"] == 1) ? true : false);

        $items = (($socialDistancing) ? "id, booking_seats, social_distancing" : "id, booking_seats");

        $result = $this->conn->query("SELECT $items FROM gfc_bookings WHERE showtime_id = ? AND booking_status IN (" . $validStatuses . ")", $details["showId"])->fetchAll();

        // STEP 2 - Combine all seat ids from the bookings
        $bookedSeats = array();
        $blockedSeats = array();

        foreach($result as $index => $booking) {

            $seats = json_decode($booking["booking_seats"], true);

            foreach($seats as $seat) {

                $bookedSeats[] = $seat;

            }

            if($socialDistancing) {

                $seats2 = json_decode($booking["social_distancing"], true);

                foreach($seats2 as $seat2) {

                    $blockedSeats[] = $seat2;

                }

            }

        }

        // STEP 3 - Get all seats for the screen the show is in

        $allSeats = $this->conn->query("SELECT * FROM gfc_screens_seats WHERE screen_id = ? AND seat_status = 1", $details["screen_id"])->fetchAll();

        // STEP 4 - GET number of available seats for the show

        $availableSeats = (count($allSeats) - count($bookedSeats));
        $saved = $availableSeats;

        if($socialDistancing) {

            $availableSeats = $availableSeats - count($blockedSeats);

        }

        //print "Original: " . $saved . " | NEW: " . $availableSeats;
        //exit;

        // STEP 5 - Check that the number of available seats is higher than the required seats

        if($availableSeats < $ticketsRequired) {

            return false;

        }

        // STEP 6 - Set seat status
        $seatingPlan = array();

        foreach($allSeats as $index => $seat) {

            if(in_array($seat["id"], $bookedSeats)) {

                $allSeats[$index]["status"] = "GREY";

            } elseif($socialDistancing && in_array($seat["id"], $blockedSeats) && $seat["seat_type"] !== 99) {

                $allSeats[$index]["status"] = "COVID";

            } else {

                $allSeats[$index]["status"] = "GREEN";

            }

            if(!isset($seatingPlan[$seat["seat_row"]])) {

                $seatingPlan[$seat["seat_row"]] = array();

            }

            $seatingPlan[$seat["seat_row"]][] = $allSeats[$index];



        }
        ksort($seatingPlan);

        // STEP 7 - Finding a row with enough seats for required tickets
        $preselectedTickets = array();
        
        if($ticketsRequired <= 10) {
            

        foreach($seatingPlan as $row => $seats) {

            $seatCount = array();
            $seatNumbers = array();
            $use = false;

            foreach($seats as $seat) {

                if(count($seatCount) == $ticketsRequired) {

                    for($position = 0; $position < (count($seatCount) - 1); $position++) {

                        if (($seatNumbers[$position] + 1) == $seatNumbers[($position + 1)]) {

                            $use = true;

                        } else {

                            $use = false;
                            break;

                        }
                    }

                    if($use) {

                        break;

                    } else {


                        $lastSeat = $seatNumbers[(count($seatNumbers) - 1)];
                        $lastSeatId = $seatCount[(count($seatCount) - 1)];

                        $seatNumbers = array();
                        $seatCount = array();

                        $seatNumbers[] = $lastSeat;
                        $seatCount[] = $lastSeatId;

                    }

                }

                if($seat["status"] == "GREEN") {

                    $seatCount[] = $seat["id"];

                    $seatNumbers[] = $seat["seat_number"];

                }

            }

            if(count($seatCount) >= $ticketsRequired && $use === true) {

                unset($seatCount["_first"]);
                unset($seatCount["_second"]);

                $preselectedTickets["row"] = $row;
                $preselectedTickets["seats"] = $seatCount;

                break;

            }



        }
        
        }
        
        // Loop through selected seats and encrypt ids
        $preselectedTicketsReturn = array();
        
        if(isset($preselectedTickets["seats"])) {
            
            foreach($preselectedTickets["seats"] as $index => $seat) {
                
                $preselectedTicketsReturn[$index] = cipher::encrypt($seat);
                
            }
            
        }

        // STEP 8 - build the html

        $html = "";
        $highest = 0;

        $seatingSizes = $this->seatingSizes;

        foreach($seatingPlan as $row => $seats) {

            // Start by creating the start of the row.
            $html .= "<tr class='screen-row'>";

                // Building the seats
                foreach($seats as $seat) {

                    if(count($seats) > $highest) {

                        $highest = count($seats);

                    }

                    $selectTicket = false;

                    if(!empty($preselectedTickets)) {
                    
                    if($preselectedTickets["row"] == $row && in_array($seat["id"], $preselectedTickets["seats"])) {

                        $selectTicket = true;

                    }
                    
                    }
                    
                    $seatConfig = (($seat["status"] == "COVID") ? "seat-blocked" : (($seat["status"] == "GREY") ? "seat-taken" : (($selectTicket) ? "seat-selected" : "")));

                    // Start of seat
                    $html .= "<td class='screen-seat seat-" . $seatingSizes["$seat[seat_type]"] . " " . $seatConfig . "' data-seatId='" . cipher::encrypt($seat["id"]) . "' data-showId='" . cipher::encrypt($show) . "' data-seattype='" . (($seat["seat_type"] == "99") ? "space" : "seat") . "'>";

                        // Insert seat image
                        $html .= "<img src='/assets/images/seats/" . $seat["seat_type"] . "-seat_" . (($selectTicket) ? "RED" : $seat["status"]) . ".png'/><br/>";

                        // Insert seat label
                        if(!in_array($seat["seat_type"], array("99"))) {
                            $html .= "<span>" . $seat["seat_row_label"] . $seat["seat_number"] . "</span>";
                        }

                    $html .= "</td>";

                }


            // Ending the row
            $html .= "</tr>";

        }
        
        // Adding default extra rows
        
            $html .= "<tr class='screen-row'>";
            
                $html .= "<td colspan='" . $highest . "' style='border:1px solid lightgrey; width:100%;' class='text-center p-1'>SCREEN</td>";
                        
            $html .= "</tr>";
            
            $html .= "<tr class='screen-row d-table-row d-sm-none'>";
            
                $html .= "<td colspan='0' class='text-center p-1'> <---- Scroll ----> </td>";
                
            $html .= "</tr>";
            
            $preselectedTickets["seats"] = $preselectedTicketsReturn;

        return array(
            "html" => $html,
            "selected" => ((!empty($preselectedTickets)) ? $preselectedTickets : "NONE"),
            "available" => $availableSeats,
            "highest" => $highest,
            "required" => $ticketsRequired);

    }

    /**
     * SeatingSocialDistancing
     * This algorithm will work out which seats in the screen need to be blocked off around the users selected seats to maintain social distancing.
     * @param $selection
     * @param $screen
     * @return array
     */
    public function seatingSocialDistancing($selection, $screen) {

        // Step 1 - Get Get array positions for each seatId

        $seats = implode(",", $selection);
        $space = $this->getConfigItem("social_distancing_spacing")["value"];

        // Queries
        $result = $this->conn->query("SELECT id, seat_row as 'row', seat_number FROM gfc_screens_seats WHERE id IN($seats)")->fetchAll();
        $screen = $this->conn->query("SELECT id, seat_row as 'row' FROM gfc_screens_seats WHERE screen_id = ?", $screen)->fetchAll();

        // Step 2 - Sort the seats by their row number
        $grouped = array();
        $seatingPlan = array();

        // Loop through each item and sort it into the right row
        foreach ($result as $id => $item) {

            // Checking the row exists in the group array
            if (!isset($grouped[$item["row"]])) {

                $grouped[$item["row"]] = array();

            }

            $grouped[$item["row"]][] = $item;

        }

        // Building seating plan
        foreach ($screen as $id => $item) {

            if (!isset($seatingPlan[$item["row"]])) {

                $seatingPlan[$item["row"]] = array();

            }

            $seatingPlan[$item["row"]][] = $item["id"];

        }


        // Step 3 - Collect ids of seats in the vacinity of the selection
        $blockedSeats = array();

        // Start looping through the group array to go through each row
        foreach ($grouped as $row => $items) {

            foreach ($items as $id => $seat) {

                // This seat is the seat to the right of the current selected seat.
                $rightSeat = $seat["seat_number"];

                // Left side counter
                $l = 1;

                // Check left side
                while($l <= $space) {

                    $stop = false;

                    $leftSeat = (($seat["seat_number"] - 1) - $l);

                    // Check seat to the left exists
                    if ($seatingPlan[($seat["row"])][$leftSeat] !== null) {

                        // If seat exists, check it isn't a selected seat
                        if (!in_array($seatingPlan[($seat["row"])][$leftSeat], $selection)) {

                            $blockedSeats[] = $seatingPlan[($seat["row"])][$leftSeat];

                        } else {
                            // Stopping loop as seat is a selected seat.
                            $stop = true;
                        }

                        // Run this section if it is at the first iteration of the loop.
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

                        // Checking if the algorithm asked for the loop to stop. If so break out of the loop.
                        if($stop === true) {

                            break;

                        }

                    } else {

                        // Stopping loop as no seat exists to the left
                        break;

                    }

                    $l++;
                }

                // Rightside seat counter
                $r = 1;

                // Check right side
                while($r <= $space) {

                    $stop = false;

                    $rightSeat = (($seat["seat_number"] - 1) + $r);

                    // Check seat to the left exists
                    if ($seatingPlan[($seat["row"])][$rightSeat] !== null) {

                        // If seat exists, check it isn't a selected seat
                        if (!in_array($seatingPlan[($seat["row"])][$rightSeat], $selection)) {

                            $blockedSeats[] = $seatingPlan[$seat["row"]][$rightSeat];

                        } else {

                            // Stopping loop as seat is a selected seat.
                            $stop = true;

                        }

                        // Run this section if its the first iteration of the loop.
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

                        // Checking if the algorithm has asked for the loop to be stopped. If so break out of the loop.
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

        // Removing duplicate seat ids
        $blockedSeats = array_unique($blockedSeats);

        // Sorting ids into numeric order
        sort($blockedSeats);

        // Return the ids that should be blocked to the caller.
        return $blockedSeats;
    }
    
    public function availableSeats($show) {
        
        // Get show information
        $showInfo = $this->getShowInfo($show);
        $validStatuses = implode(",", array(
            "'reserved'",
            "'reserved_temp'",
            "'complete'",
            "'awaiting_payment'",
            "'GFC_ADMIN'",
            "'PAID'"
        ));

        $item = "";
        if($showInfo["social_distancing"] == 1) {
            $item = ", SUM(social_distancing_total) as 'blocked'";
        }
        
        // Get number of available taken seats for the show
        $taken = $this->conn->query("SELECT SUM(booking_seats_total) AS 'booked'$item FROM gfc_bookings WHERE showtime_id = ? AND booking_status IN($validStatuses)", $show)->fetchArray();

        $taken = (($showInfo["social_distancing"] == 1) ? ($taken["booked"] + $taken["blocked"]) : $taken["booked"]);
        
        // Get number of seats for the screen
        $total = $this->conn->query("SELECT COUNT(id) AS 'total' FROM gfc_screens_seats WHERE screen_id = ? AND NOT seat_type = 99", $showInfo["screen_id"])->fetchArray();
        
        $total = $total["total"];
        
        $available = ($total - $taken);
        
        return array("taken" => $taken, "available" => $available, "total" => $total);
        
        
    }
    
    public function booking_generateCode() {
        
        $charArray = str_split("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789");
        $code = "";
        
        for($i = 0; $i < 8; $i++) {
            $randItem = array_rand($charArray);
            $code .= "".$charArray[$randItem];
        }
        
        return $code;    
        
    }
    
    
}