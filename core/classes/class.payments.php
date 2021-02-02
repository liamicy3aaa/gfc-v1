<?php

/**
 * Class payments
 *
 * @author Liam McClelland
 * @property string $PUBLIC_KEY Stripe public key
 * @property string $PRIVATE_KEY Stripe private key
 * @property db $conn Reference to an instance of the database class.
 * @property string $template Path to a template for the payment screen.
 */

class payments {

    private $PUBLIC_KEY;
    private $PRIVATE_KEY;
    private $conn;
    private $template;

    /**
     * payments constructor.
     * @param string $publicKey
     * @param string $privateKey
     * @param db $db
     * @param string $template
     */

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

    /**
     * TLS Support Check
     *
     * Ensuring TLS is supported on the server.
     *
     * @return null
     */

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

    /**
     * Transaction Exists by Booking
     *
     * Check if a transaction record already exists for a particular booking id.
     *
     * @param string $bookingId
     * @return array Returns a status and if true the data for the record.
     */

    public function transactionExistsByBooking($bookingId) {

        $r = $this->conn->query("SELECT * FROM gfc_transactions WHERE booking_id = ? ORDER BY ts DESC LIMIT 1", $bookingId);

        if($r->numRows() >= 1) {

            return array("status" => true, "data" => $r->fetchArray());

        } else {

            return array("status" => false);

        }


    }

    /**
     * Transaction Exists by Id
     *
     * Check if a transaction recoard already exists for a particular id.
     *
     * @param int $id
     * @return array
     */

    public function transactionExistsById($id) {

        $r = $this->conn->query("SELECT * FROM gfc_transactions WHERE id = ? ORDER BY ts DESC LIMIT 1", $id);

        if($r->numRows() >= 1) {

            return array("status" => true, "data" => $r->fetchArray());

        } else {

            return array("status" => false);

        }


    }

    /**
     * Create Payment
     *
     * Create a new transaction record for the current booking and generate payment screen html.
     *
     * @param string $bookingId
     * @return array Returns a status and if true, it returns the html, public key and transaction Id.
     */

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

    /**
     * Refund Payment
     *
     * Refund a payment that has already taken place.
     *
     * @param string $bookingId
     * @param string $amount
     * @return array Returns a status and if true also the amount that was refunded.
     */

    public function refundPayment($bookingId, $amount = "FULL") {

        // Checking the provided booking id is valid
        if(strlen($bookingId) < 2) {

            return array("status" => false, "error" => "missing_data");

        }

        // Getting transaction info
        $data = $this->transactionExistsByBooking($bookingId);

        // Checking a transaction exists for the provided booking id
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

    /**
     * Create Transaction
     *
     * Create a new transaction record for the provided booking id.
     *
     * @param string $bookingId
     * @return array
     */
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

    /**
     * Update Transaction
     *
     * Update the transaction record with more information.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateTransaction($id, $data) {

        $allowed = array("status", "total", "ts", "payment_type", "payment_ref", "refund_amount");
        $str = array("payment_type", "payment_ref", "status");

        // Ensuring any data items provided are allowed to be updated.
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

        $r = $this->conn->query($q);

        $affected = $r->affectedRows();

        // Checking if the update has worked.
        if($affected < 1) {

            return array("status" => false, "error" => "No rows updated");

        } else {

            return array("status" => true);

        }

    }

    /**
     * Build Payment Screen
     *
     * Generate the html for the payment screen.
     *
     * @param int $cost
     * @param string $transactionId
     * @return string
     */
    public function buildPaymentScreen($cost, $transactionId) {
        setlocale(LC_MONETARY,"en");
        $screen = str_replace(
            array("%COST%", "%PUBLIC_KEY%", "%TRANSACTION_ID%"),
            array(money_format("%i", $cost), $this->PUBLIC_KEY, cipher::encrypt($transactionId)),
            file_get_contents("../templates/payments/payment_screen.phtml")
        );

        return $screen;

    }

    /**
     * Process Payment
     *
     * Process and charge the payment for the provided transaction id.
     *
     * @param int $transactionId
     * @param array $data
     * @return array
     */
    public function processPayment($transactionId, $data) {

        \Stripe\Stripe::setApiKey($this->PRIVATE_KEY);

        // retrieve json from POST body
        $required = array("amount", "currency");

        // Check the method and intent have been provided.
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

            // Display error on client
            return array("status" => false, "error" => "stripe_error", "error_desc" => $e->getMessage());

        }

    }

    /**
     * Generate Response
     *
     * Create a response for the Stripe front-end client using the data received from the Stripe API request.
     *
     * @param string $intent
     * @param int $transactionId
     * @return array
     */
    private function generateResponse($intent, $transactionId) {

        // Checking if the intent requires action
        if ($intent->status == 'requires_action' && $intent->next_action->type == 'use_stripe_sdk') {

            // Return the error to the client.
            return array("status" => false, "error" => "require_action", "res" => array(
                'requires_action' => true,
                'payment_intent_client_secret' => $intent->client_secret
            ));

        } else if ($intent->status == 'succeeded') {

            // The payment didn’t need any additional actions and completed!
            // Handle post-payment fulfillment

            // Update the transaction to reflect the successful payment.
            $this->updateTransaction($transactionId, array(
                "status" => "COMPLETE",
                "payment_type" => $intent->charges->data[0]->payment_method_details->card->brand . ":" . $intent->charges->data[0]->payment_method_details->card->last4,
                "payment_ref" => $intent->id
            ));

            return array("status" => true, "res" => array("success" => true));

        } else {

            // Invalid status
            return array("status" => false, "error" => "server_error", "error_desc" => "Invalid PaymentIntent status");
        }
    }


}