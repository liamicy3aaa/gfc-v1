<?php

class payments {

    private $PUBLIC_KEY;
    private $PRIVATE_KEY;
    private $conn;
    private $template;

    public function __construct($publicKey, $privateKey, $db, $template = "../templates/payments/payment_screen.phtml")
    {

        $this->PUBLIC_KEY = $publicKey;
        $this->PRIVATE_KEY = $privateKey;
        $this->conn = $db;

        if(!file_exists("../templates/payments/payment_screen.phtml")) {

            die("DEVELOPER: INVALID TEMPLATE FILEPATH PROVIDED [". __LINE__ ."]");

        }

        $this->template = $template;


    }
	
	public function _test() {
		
		error_reporting(E_ALL);
		\Stripe\Stripe::setApiKey($this->PRIVATE_KEY);
		try {
		  \Stripe\Charge::all();
		  echo "TLS 1.2 supported, no action required.";
		} catch (Exception $e) {
		  echo "TLS 1.2 is not supported. You will need to upgrade your integration.";
		}
		print "<br/>END";
		exit;
	}

    public function transactionExistsByBooking($bookingId) {

        $r = $this->conn->query("SELECT * FROM gfc_transactions WHERE booking_id = ? ORDER BY ts DESC LIMIT 1", $bookingId);

        if($r->numRows() >= 1) {

            return array("status" => true, "data" => $r->fetchArray());

        } else {

            return array("status" => false);

        }


    }

    public function transactionExistsById($id) {

        $r = $this->conn->query("SELECT * FROM gfc_transactions WHERE id = ? ORDER BY ts DESC LIMIT 1", $id);

        if($r->numRows() >= 1) {

            return array("status" => true, "data" => $r->fetchArray());

        } else {

            return array("status" => false);

        }


    }

    public function createPayment($bookingId) {

        $transaction = $this->createTransaction($bookingId);

        if(!$transaction["status"]) {

            return $transaction;

        }

        if($transaction["tran_status"] !== "NEW") {

            return array("status" => false, "error" => "payment_already_taken");

        }

        $screen = $this->buildPaymentScreen($transaction["total"], $transaction["id"]);

        return array("status" => true, "html" => $screen, "publicKey" =>$this->PUBLIC_KEY, "transactionId" => $transaction["id"]);


    }

    public function refundPayment($bookingId, $amount = "FULL") {

        if(strlen($bookingId) < 2) {

            return array("status" => false, "error" => "missing_data");

        }

        // Getting transaction info
        $data = $this->transactionExistsByBooking($bookingId);

        if(!$data["status"]) {

            return array("status" => false, "error" => "no_transaction_found");

        } else {

            if(in_array($data["data"]["status"], array("NEW", "REFUNDED"))) {

                return array("status" => false, "error" => "refund_not_possible");

            }

        }

        $transactionIntent = $data["data"]["payment_ref"];

        // Checking that if an amount is provided, it is not more than the original transaction total
        if($amount !== "FULL") {

            if($amount > $data["data"]["total"]) {

                return array("status" => false, "error" => "invalid_amount");

            }
            $cost = ($amount * 100);

            $refundObj = [
                'amount' => $cost,
                'payment_intent' => $transactionIntent,
            ];

            $this->updateTransaction($data["data"]["id"], array(
                "refund_amount" => $amount
            ));

        } else {

            $refundObj = [
                'payment_intent' => $transactionIntent,
                'reason' => 'requested_by_customer',
            ];

        }

        // Setting API Key for Stripe
        \Stripe\Stripe::setApiKey($this->PRIVATE_KEY);

        // Try refunding the payment

        try{

            $refund = \Stripe\Refund::create($refundObj);

        } catch (\Stripe\Exception\ApiErrorException $e) {

            # Display error on client

            return array("status" => false, "error" => "stripe_error", "error_desc" => $e->getMessage());
        }

        if($refund->status == "succeeded") {

            $update = $this->updateTransaction($data["data"]["id"], array(
                "status" => (($amount == "FULL") ? "REFUNDED" : "PARTIAL_REFUNDED")
            ));

            if($update["status"]) {

                return array("status" => true, "amount" => $refund->amount);

            } else {

                return array("status" => false, "error" => "server_error");

            }

        } else {

            return array("status" => false, "error" => "stripe_error", "data" => $refund->status);

        }




    }

    protected function createTransaction($bookingId) {

        if(strlen($bookingId) < 2) {

            return array("status" => false, "error" => "missing_data");

        }

        // Getting total from booking
        $booking = $this->conn->query("SELECT id, booking_total FROM gfc_bookings WHERE booking_reference = ?", $bookingId)->fetchArray();


        // Checking if transaction for booking already exists. If so, return info for that booking
        $exists = $this->transactionExistsByBooking($booking["id"]);

        if($exists["status"]) {

            return array("status" => true, "id" => $exists["data"]["id"], "total" => $exists["data"]["total"], "tran_status" => $exists["data"]["status"]);

        }

        // build query
        $q = "INSERT INTO gfc_transactions (booking_id, ts, total, status) VALUES(
        " . $booking["id"]. ",
        " . time() . ",
        " . $booking["booking_total"] . ",
        'NEW'        
        )";

        $this->conn->query($q);

        // Getting id for transaction
        $r = $this->conn->query("SELECT id, ts, total, status FROM gfc_transactions WHERE booking_id = ? ORDER BY ts DESC LIMIT 1", $booking["id"])->fetchArray();

        return array("status" => true, "id" => $r["id"], "total" => $r["total"], "tran_status" => $r["status"]);
    }

    public function updateTransaction($id, $data) {

        $allowed = array("status", "total", "ts", "payment_type", "payment_ref", "refund_amount");
        $str = array("payment_type", "payment_ref", "status");

        foreach($data as $col => $content) {

            if(!in_array($col, $allowed)) {

                return array("status" => false, "invalid_column: $id");

            }

        }

        // Build query
        $q = "UPDATE gfc_transactions SET ";
        $count = 0;

        foreach($data as $col => $content) {

            $q .= (($count >= 1) ? ", " : "") . "$col = " . ((in_array($col, $str)) ? "'" . $content . "'" : "$content");
            $count++;
        }

        $q .= " WHERE id = $id";

        //die($q);

        $r = $this->conn->query($q);

        $affected = $r->affectedRows();

        if($affected < 1) {

            return array("status" => false, "error" => "No rows updated");

        } else {

            return array("status" => true);

        }

    }

    public function buildPaymentScreen($cost, $transactionId) {
        setlocale(LC_MONETARY,"en");
        $screen = str_replace(
            array("%COST%", "%PUBLIC_KEY%", "%TRANSACTION_ID%"),
            array(money_format("%i", $cost), $this->PUBLIC_KEY, cipher::encrypt($transactionId)),
            file_get_contents("../templates/payments/payment_screen.phtml")
        );

        return $screen;

    }

    public function processPayment($transactionId, $data) {

        \Stripe\Stripe::setApiKey($this->PRIVATE_KEY);

        # retrieve json from POST body
        $required = array("amount", "currency");

        if(!isset($data["method"]) && !isset($data["intent"])) {

            return array("status" => false, "error" => "missing_data", "error_desc" => "Invalid request.");

        }

        foreach($required as $item) {

            if(!isset($data[$item])) {

                return array("status" => false, "error" => "missing_data", "error_desc" => "Missing $item from data array.");

            }

        }

        $intent = null;
        try {
            if(isset($data["method"])) {
                # Create the PaymentIntent
                $intent = \Stripe\PaymentIntent::create([
                    'payment_method' => $data["method"],
                    'amount' => $data["amount"],
                    'currency' => $data["currency"],
                    'confirmation_method' => 'manual',
                    'confirm' => true
                ]);

            }
            if (isset($data["intent"])) {
                $intent = \Stripe\PaymentIntent::retrieve(
                    $data["intent"]
                );
                $intent->confirm();
            }

            return $this->generateResponse($intent, $transactionId);
        } catch (\Stripe\Exception\ApiErrorException $e) {

            # Display error on client

               return array("status" => false, "error" => "stripe_error", "error_desc" => $e->getMessage());
        }

    }

    private function generateResponse($intent, $transactionId) {
        # Note that if your API version is before 2019-02-11, 'requires_action'
        # appears as 'requires_source_action'.
        if ($intent->status == 'requires_action' &&
            $intent->next_action->type == 'use_stripe_sdk') {
            # Tell the client to handle the action

            return array("status" => false, "error" => "require_action", "res" => array(
                'requires_action' => true,
                'payment_intent_client_secret' => $intent->client_secret
            ));

        } else if ($intent->status == 'succeeded') {
            # The payment didn’t need any additional actions and completed!
            # Handle post-payment fulfillment

            //print "<pre>"; print_r($intent->charges->data[0]->payment_method_details->card->brand); print "</pre>";
            //exit;

            $this->updateTransaction($transactionId, array(
                "status" => "COMPLETE",
                "payment_type" => $intent->charges->data[0]->payment_method_details->card->brand . ":" . $intent->charges->data[0]->payment_method_details->card->last4,
                "payment_ref" => $intent->id
            ));

            return array("status" => true, "res" => array("success" => true));

        } else {
            # Invalid status
            return array("status" => false, "error" => "server_error", "error_desc" => "Invalid PaymentIntent status");
        }
    }


}