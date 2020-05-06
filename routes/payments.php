<?php

$app->group("/payments", function(){

    $this->get("/test", function($request, $response, $args){


exit;
        $payments = $this->get("payments");
        $cinema = $this->get("cinema");
        $amount = 5.95;

        $payments->updateTransaction(282, array(
            "status" => (($amount == "FULL") ? "REFUNDED" : "PARTIAL_REFUNDED")
        ));

    });

    $this->get("/new/{id}", function($request, $response, $args) {

        $cinema = $this->get("cinema");
        $payments = $this->get("payments");
        $id = cipher::decrypt($args["id"]);

        if(!$cinema->bookingExists($id)) {

            return $response->withJson(array("error" => "invalid_booking"), 400);

        }

        $payment = $payments->createPayment($id);

        if(!$payment["status"]) {

            return $response->withJson($payment, 400);

        }

        return $response->withJson(array(
            "html" => $payment["html"],
            "public_key" => $payment["publicKey"],
            "transaction" => cipher::encrypt($payment["transactionId"])),
            200);

        /*return $response = $this->view->render($response, "/blank.phtml", [
            "_title" => "PAYMENTS",
            "html" => $payment["html"]
        ]);*/


    });

    $this->post("/process/{id}", function($request, $response, $args){

        $payments = $this->get("payments");
        $cinema = $this->get("cinema");
        $postBody = $request->getParsedBody();
        $exists = $payments->transactionExistsById(cipher::decrypt($args["id"]));

        // Checking id provided is valid
        if(!isset($args["id"]) || !$exists["status"]) {

            return $response->withJson(array("status"=>401, "error"=>"access_denied"), 401);

        }

        if(isset($postBody["payment_method_id"])) {

           $code = "payment_method_id";
           $param = "method";

        } elseif(isset($postBody["payment_intent_id"])) {

            $code = "payment_intent_id";
            $param = "intent";

        } else {

            return $response->withJson(array("error"=>"invalid_request"), 400);

        }

        if(strpos($exists["data"]["total"], ".") !== false) {

            $cost = str_replace(".", "", $exists["data"]["total"]);

        } else {

            $cost = $exists["data"]["total"] . "00";

        }

        // Processing payment
        $cmd = $payments->processPayment(cipher::decrypt($args["id"]), array(
            "$param" => $postBody[$code],
            "amount" => $cost,
            "currency" => "gbp"
        ));

        // If error, response accordingly
        if(!$cmd["status"]) {
            // Error - Process error and return to the client
            switch($cmd["error"]) {

                case "missing_data":
                    return $response->withJson(array("error" => "missing_data", "error_desc" => $cmd["error_desc"]), 400);
                    break;

                case "stripe_error":
                    return $response->withJson(array("error" => $cmd["error_desc"]), 200);
                    break;

                case "require_action":
                    return $response->withJson($cmd["res"], 200);
                    break;

                case "server_error":
                    return $response->withJson(array("error" => "server_error", "error_desc"=>$cmd["error_desc"]), 500);
                    break;

                default:
                    return $response->withJson(array("error"=>"unknown_error", "res"=>$cmd), 500);
                    break;

            }

        } else {
            $db = $this->get("db");
            $booking = $db->query("SELECT booking_reference AS 'id' FROM gfc_bookings WHERE id = ?", $exists["data"]["booking_id"])->fetchArray();
            // Send booking confirmation
            $cinema->sendBookingConfirmation($booking["id"]);

            // Updating booking status to PAID
            $cinema->updateBooking($booking["id"], array(
                "booking_status" => "PAID"
            ));

            // Success, return success response to client
            return $response->withJson($cmd["res"], 200);

        }


    });



});