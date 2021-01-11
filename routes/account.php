<?php

$app->group("/my-account", function(){

        $this->get("[/{op}]", function($request, $response, $args) {

        $user = $this->get("user");
        $cinema = $this->get("cinema");
        $cmd = ((isset($args["op"])) ? $args["op"] : "bookings");

        // If customer is not logged in show login page
        if(!isset($_SESSION["_customer"])) {

            // Generating CSRF token for form
            if (!isset($_SESSION["authToken"]) || isset($_SESSION["authTokenArea"]) && $_SESSION["authTokenArea"] !== "customer") {

                $authSalt = cipher::encrypt(time() + rand(14));
                $auth = cipher::encrypt("customer:" . $authSalt);
                $_SESSION["authToken"] = $auth;
                $_SESSION["authTokenSalt"] = $authSalt;
                $_SESSION["authTokenArea"] = "customer";

            } else {

                $auth = $_SESSION["authToken"];

            }

            $finalHtml = str_replace(array("%AUTHTOKEN%"), array($auth), file_get_contents("../templates/authentication/customer_login.phtml"));

        } else {

            switch($cmd) {

                case "bookings":
                    $userEmail = $user->getUserInfo(array("user_email"), $_SESSION["_customer"]["id"]);
                    $userEmail = $userEmail["data"]["user_email"];

                    $bookings = $cinema->getBookingsByEmail($userEmail, (time() - 7890000));

                    if(count($bookings) < 1) {
                        $html = "<h5>No bookings</h5>";
                    } else {
                        $bookingHtml = "";
                        foreach ($bookings as $booking) {

                            $bookingHtml .= '<a class="MP-item list-group-item list-group-item-action my-1">';
                            $bookingHtml .= '<div class="d-flex w-100 py-0 h-100">';
                            $bookingHtml .= '<div class="col-1 col-md-1 d-none d-lg-block p-0">';
                            $bookingHtml .= '<img src="' . $booking['film_thumbnail'] . '" class="img-thumbnail" height="auto" width="50px"/>';
                            $bookingHtml .= '</div>';
                            $bookingHtml .= '<div class="col-12 col-md-10 py-auto justify-content-center">';
                            $bookingHtml .= '<h5 class="mb-1 mt-1 pt-1 pt-md-2"> ' . $booking["film_name"] . '</h5>';
                            $bookingHtml .= '<small class="mb-1"><span class="font-weight-bold">' . date("d/m/Y", $booking["time"]) . ' ' . date("H:i", $booking["time"]) . '<br/></small>';
                            $bookingHtml .= '</div></div></a>';
                            //$bookingHtml .= "<a href=\"#\" class=\"list-group-item list-group-item-action\">" . date("d/m/Y H:i", $booking["time"]) . " | " . $booking["film_name"] . "</a>";
                        }

                        $html = str_replace(array("%BOOKINGS%"), array($bookingHtml), file_get_contents("../templates/account/partial.account_bookings.phtml"));
                    }
                    break;

                case "profile":
                    $html = str_replace(array("%2%"), array("%2%"), file_get_contents("../templates/account/partial.account_edit_profile.phtml"));
                    break;

                default:
                    $html = "NO OPTION SELECTED";
                    break;

            }
            $finalHtml = str_replace(array("%html%", "%p_$cmd%"), array($html, "active font-weight-bold disabled"), file_get_contents("../templates/account/partial.account_nav.phtml"));
        }


        return $response = $this->view->render($response, "/account/my-account.phtml", ["_title" => "My Account", "content" => $finalHtml]);


    });




});