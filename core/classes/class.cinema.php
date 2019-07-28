<?php

class cinema {
    
    protected $config;
    protected $conn;
    
    public function __construct($config = array(), $database) {
        
        $this->config = $config;
        $this->conn = $database;
        

    }

    public function buildPromoBanner() {

            $promo = $this->conn->query("SELECT value FROM gfc_config WHERE `key` = ?", "_promoBanner")->fetchArray();

            $data = json_decode($promo["value"], true);

            $fields = array(
                "%BANNER%",
                "%NAME%",
                "%DESC%"
            );

            $data = array($data["banner"], $data["title"], $data["desc"]);

            return str_replace($fields, $data, file_get_contents("../templates/partials/whats-on-banner.phtml"));

    }

    public function getCinemaInfo() {

        $info = $this->conn->query("SELECT * FROM gfc_config WHERE `key` = 'INFO_cinemaName'")->fetchArray();


        $data = array(
            "name" => $info["value"],
            "id" => "1043"

        );

        return $data;

    }
    
    public function getScreens($ids = array()) {
        
        if(count($ids) >= 1) {
            
            $screenIds = implode(",", $ids);
            
            $film = $this->conn->query("SELECT * FROM gfc_screens WHERE id IN($screenIds)")->fetchAll();
            
        } else {
            
            $film = $this->conn->query("SELECT * FROM gfc_screens")->fetchAll();    
            
        }
        
        return $film;
        
    } 
    
    public function getFilmData($id = false) {
        
        if(!$id) {
            
            return false;
            
        }
        
        $film = $this->conn->query("SELECT * FROM gfc_films WHERE id = ?", $id)->fetchArray();
        
        return $film;    
        
    }
    
    public function getFilms($ids = array()) {
        
        if(count($ids) >= 1) {
            
            $filmIds = implode(",", $ids);
            
            $films = $this->conn->query("SELECT * FROM gfc_films WHERE id IN($filmIds) AND film_status = 1")->fetchAll();
            
        } else {
        
            $films = $this->conn->query("SELECT * FROM gfc_films WHERE film_status = 1")->fetchAll();
        
        }
        
        return $films;
        
    }
    
    public function getTicketInfo($ids) {
        
        $ids = implode(",", $ids);
        
        $r = $this->conn->query("SELECT * FROM gfc_ticket_types WHERE id IN($ids)")->fetchAll();
        
        $types = array();
        
        foreach($r as $index => $type) {
            
            $types[$type["id"]] = $type;    
            
        }
        
        return $types;
        
    }
    
    public function getShowInfo($id) {
        
        // Clean item
        $showId = $this->conn->conn()->real_escape_string($id);
        
        $show = $this->conn->query("SELECT *, b.id AS 'showId', b.screen_id, b.time AS 'showtime', c.screen_name, b.`ticket_config` FROM gfc_films AS a INNER JOIN gfc_films_showtimes AS b ON a.id = b.film_id INNER JOIN gfc_screens AS c ON b.screen_id = c.id WHERE b.id = ?", $showId)->fetchArray();
        
        if(count($show) >= 1) {
        $show["ticket_config"] = json_decode($show["ticket_config"], true);
        }

        return $show;
             
    }
    
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
            "booking_used" 
        );

        $number = array(
            "booking_seats_total",
            "film_id",
            "showtime_id",
            "booking_total",
            "booking_ts"
        );

        $columns = array();
        $values = array();

        // Generating content for certain columns
        $bookingCode = $this->booking_generateCode();

        foreach($data as $column => $value) {
            
            if(in_array($column, $available)) {

                if(in_array($column, array("booking_info", "booking_seats"))){

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
    
    public function bookingExists($bookingId) {
        
        $r = $this->conn->query("SELECT ID FROM gfc_bookings WHERE booking_reference = ?", $bookingId)->numRows();
        
        if($r >= 1) {
            
            return true;
            
        } else {
            
            return false;
            
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
        
        if($update > 0) {
            
            return true;
                
        } else {
            
            return false;
            
        }
              
    }
    
    public function cancelBooking($bookingId) {
        
        $this->conn->query("DELETE FROM gfc_bookings WHERE booking_reference = ?", $bookingId);
        
        return true;  
        
    }
    
    public function getShowtimes($id = false) {

        $time = time();
        
        if($id !== false) {
            
            // List show times for a specific film
            $times = $this->conn->query("SELECT * FROM gfc_films_showtimes WHERE film_id = ? AND `time` > ?", $id, $time)->fetchAll();
            
            return $times;
            
        } else {
            
            // List all available showtimes by film id
            
            $times = $this->conn->query("SELECT id, film_id, date, time FROM gfc_films_showtimes WHERE `time` > ?", $time)->fetchAll();
            
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
                $film["id"],
                $film["film_name"],
                $film["film_desc"],
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
                
                return "<a href='film/" . $film["id"] . "' class='btn btn-primary mt-1'>More showtimes ></a>";
                
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

            $html .= "<span class='pb-1'>$date</span>";

            $html .= "<br/>";
            
            foreach($day as $show) {

                $time = date("H:i", $show["time"]);

                $html .= "&nbsp;<a href='/booking/new/" . $show["film_id"] . "/" . $show["id"] . "' class='btn btn-primary mt-1'>$time</a>";
                
            }

            if($limit !== false) {

                $html .= "&nbsp;<a href='film/" . $show["film_id"] . "' class='btn btn-primary mt-1'>More showtimes ></a>";

            }

            $html .= "<br/>";
            
            
        }

        return $html;




    }
    
    public function getTicketTypes($ids = array(), $onlyActive = true) {
        
        if($onlyActive) {
            
            $addonPre = "AND ";
            $addon = "ticket_status = 1";
            
        } else {
            $addonPre = "";
            $addon = "";
            
        }
        
        if(count($ids) >= 1) {
            
            $types = implode(",", $ids);
            
           $data = $this->conn->query("SELECT * FROM gfc_ticket_types WHERE id IN($types) $addonPre$addon")->fetchAll();    
            
        } else {
            
           $data = $this->conn->query("SELECT * FROM gfc_ticket_types WHERE $addon")->fetchAll(); 
            
        }
        
        return $data;
        
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
                        
                        $html .= "<small class='text-secondary'>Proof of entitlement may be required</small>";
                            
                    $html .= "</div>";
                    
                }
                       
                $html .= "</td>";
                
                $html .= "<td class='col-1 col-sm-1 col-md-1 text-right'>&pound;" . $ticket["ticket_cost"] . "</td>";
                
                $html .= "<td class='col-3 col-sm-1 col-md-2 text-right'>";
                
                    $html .= "<select name='" . $ticket["id"] . "' class='form-control input-sm ticket-option' style='width:65px;' data-tickettype='" . $ticket["ticket_label"] . "'>";
                    
                        // Build list ticket quantity options
                        for($x = 0; $x <= 8; $x++) {
                            
                            $html .= "<option value='$x'>$x</option>";
                        
                        }
                        
                    $html .= "</select>";
                
                $html .= "</td>";
                
                
                $html .= "<td class='col-1 col-md-1 text-right d-none d-sm-table-cell ticket-option-" . $ticket["id"] ."'></td>";
                
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

    public function buildSeatingPlan($show, $ticketsRequired) {

        // Get show information
        $details = $this->getShowInfo($show);
        $validStatuses = implode(",", array(
            "'reserved'",
            "'reserved_temp'",
            "'complete'",
            "'awaiting_payment'",
            "'GFC_ADMIN'"
        ));

        //die("SELECT id, booking_seats FROM gfc_bookings WHERE showtime_id = ? AND booking_status IN (" . $validStatuses . "");
        // STEP 1 - Get all bookings that hold tickets for this showing

        $result = $this->conn->query("SELECT id, booking_seats FROM gfc_bookings WHERE showtime_id = ? AND booking_status IN (" . $validStatuses . ")", $details["showId"])->fetchAll();

        // STEP 2 - Combine all seat ids from the bookings
        $bookedSeats = array();

        foreach($result as $index => $booking) {

            $seats = json_decode($booking["booking_seats"], true);

            foreach($seats as $seat) {

                $bookedSeats[] = $seat;

            }

        }

        // STEP 3 - Get all seats for the screen the show is in

        $allSeats = $this->conn->query("SELECT * FROM gfc_screens_seats WHERE screen_id = ? AND seat_status = 1", $details["screen_id"])->fetchAll();

        // STEP 4 - GET number of available seats for the show

        $availableSeats = (count($allSeats) - count($bookedSeats));

        // STEP 5 - Check that the number of available seats is higher than the required seats

        if($availableSeats < $ticketsRequired) {

            return "Not enough seats available.";

        }

        // STEP 6 - Set seat status
        $seatingPlan = array();

        foreach($allSeats as $index => $seat) {

            if(in_array($seat["id"], $bookedSeats)) {

                $allSeats[$index]["status"] = "GREY";

            } else {

                $allSeats[$index]["status"] = "GREEN";

            }

            if(!isset($seatingPlan[$seat["seat_row"]])) {

                $seatingPlan[$seat["seat_row"]] = array();

            }

            $seatingPlan[$seat["seat_row"]][] = $allSeats[$index];



        }

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

        // STEP 8 - build the html

        $html = "";
        $highest = 0;

        $seatingSizes = array("1" => "standard", "2" => "double");

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
                    
                    $seatConfig = (($seat["status"] == "GREY") ? "seat-taken" : (($selectTicket) ? "seat-selected" : ""));

                    // Start of seat
                    $html .= "<td class='screen-seat seat-" . $seatingSizes["$seat[seat_type]"] . " " . $seatConfig . "' data-seatId='" . $seat["id"] . "'>";

                        // Insert seat image
                        $html .= "<img src='/assets/images/seats/" . $seat["seat_type"] . "-seat_" . (($selectTicket) ? "RED" : $seat["status"]) . ".png'/><br/>";

                        // Insert seat label
                        $html .= $seat["seat_row_label"] . $seat["seat_number"];

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

        return array(
            "html" => $html,
            "selected" => ((!empty($preselectedTickets)) ? $preselectedTickets : "NONE"),
            "available" => $availableSeats,
            "highest" => $highest,
            "required" => $ticketsRequired);

    }
    
    public function availableSeats($show) {
        
        // Get show information
        $showInfo = $this->getShowInfo($show);
        $validStatuses = implode(",", array(
            "'reserved'",
            "'complete'",
            "'awaiting_payment'",
            "'GFC_ADMIN'"
        ));
        
        // Get number of available taken seats for the show
        $taken = $this->conn->query("SELECT SUM(booking_seats_total) AS 'taken' FROM gfc_bookings WHERE showtime_id = ? AND booking_status IN($validStatuses)", $show)->fetchArray();
        
        $taken = $taken["taken"];
        
        // Get number of seats for the screen
        $total = $this->conn->query("SELECT COUNT(id) AS 'total' FROM gfc_screens_seats WHERE screen_id = ?", $showInfo["screen_id"])->fetchArray();
        
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