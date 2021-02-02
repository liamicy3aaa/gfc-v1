<?php

/**
 * Class emailQueue
 *
 * @author Liam McClelland
 * @property db $db Reference to an instance of the database class
 * @property cinema $cinema Reference to an instance of the cinema class
 * @property int $runtime_timeout Maximum number of seconds the process loop can run for.
 */

class emailQueue {

    private $db;
    private $cinema;
    private $runtime_timeout;

    /**
     * emailQueue constructor.
     * @param db $db
     * @param cinema $cinema
     * @throws Exception
     */

    public function __construct($db, $cinema) {

        // Check a database and cinema class instance have been provided.
        if(!is_a($db, "db") || !is_a($cinema, "cinema")) {
            throw new Exception("An error occurred initializing one of the parameters for emailQueue. (" . __LINE__ . ")");
        }

        $this->db = $db;
        $this->cinema = $cinema;
        $this->runtime_timeout = 50;
    }

    /**
     * Add Email to Queue
     *
     * Enables you to add a new email to the queue to be sent to a user.
     *
     * @param array $recipient An array containing the 'name' and 'email' of the user
     * @param string $template The id of the template you wish to use
     * @param string $subject The subject of the email
     * @param array $content An array of the data that is required for the selected template.
     * @param array $settings An array of settings which allows you to customise the email.
     * @return array An array which includes the status of the request and an error key if an error occurred.
     */

    public function add($recipient, $template, $subject, $content, $settings = array()) {

        // Check the recipient information has been provided
        if(!isset($recipient["name"]) || !isset($recipient["email"])) {
            return array("status" => false, "error" => "Missing user info.");
        }

        // Ensure recipient email is a valid email
        if(!filter_var($recipient["email"], FILTER_VALIDATE_EMAIL)) {
            return array("status" => false, "error" => "Invalid email provided.");
        }

        // Check template is valid
        $emailTemplate = email::getTemplate($template);

        if(!$emailTemplate) {
            return array("status" => false, "error" => "Invalid template id provided.");
        }

        // Validate content for email meets the template requirements
        foreach($emailTemplate["required"] as $item) {

            if(!$content[$item]) {
                return array("status" => false, "error" => "$item missing from content.");
            }

        }

        // Checking and validating settings
        $sendTime = ((isset($settings["sendTime"])) ? $settings["sendTime"] : time());

        // Insert to database
        $result = $this->db->query("INSERT INTO gfc_emails_queue (recipient, send_at, template_id, subject, content) VALUES (
            '" . json_encode($recipient). "',
            $sendTime,
            '$template',
            '$subject',
            '" . json_encode($content) . "'
        )");

        return array("status" => true);

    }

    /**
     * Process Queue
     *
     * Enables you to process a batch of outstanding emails to be sent to users. This should be called from a cronjob.
     *
     * @return int Number of emails processed.
     * @throws \PHPMailer\PHPMailer\Exception
     */

    public function process() {

        $time = time();
        $count = 0;
        $toProcess = $this->getEmails();

        // Check if there are any emails to process.
        if(count($toProcess) < 1) {
            return 0;
        }

        // Start phpmailer
        $email = new email($this->cinema, parse_ini_file($_SERVER["DOCUMENT_ROOT"] . "/../app/email.ini"));
        $email->keepAlive();

        // Start looping through outstanding emails
        foreach($toProcess as $id => $item) {

            // If the loop has been running longer than the set limit, break out of the loop.
            if(time() - $time > $this->runtime_timeout) {
                break;
            }

            $email->clear();

            $user = json_decode($item["recipient"], true);

            $email->addRecipient($user["email"], $user["name"]);
            $email->setSubject($item["subject"]);
            $email->setTemplate($item["template_id"]);
            $email->addContent(json_decode($item["content"], true));

            $email->send();

            $this->markAsSent($item["id"]);
            $count++;

        }

        $email->close();

        return $count;

    }

    /**
     * Mark As Sent
     *
     * Enables you to mark an emailQueue record as sent to prevent the same email from being sent again.
     *
     * @param int $id Id of the emailQueue record
     * @return bool
     */

    private function markAsSent($id) {
        $this->db->query("UPDATE gfc_emails_queue SET is_sent = 1 WHERE id = ?", $id);
        return true;
    }

    /**
     * Get Emails
     *
     * Enables you to retrieve a batch of emails that are awaiting to be sent to users.
     *
     * @param int $limit Maximum number of emails you wish to obtain from the emailQueue
     * @return array An array of records from the email queue.
     */

    private function getEmails($limit = 50) {
        return $this->db->query("SELECT * FROM gfc_emails_queue WHERE send_at <= " . time() . " AND is_sent = 0 LIMIT $limit")->fetchAll();
    }

    /**
     * Clean Queue
     *
     * Enables you to remove any sent emails from the database to reduce the number of records present in the table.
     *
     * @return int Returns the number of rows that have been removed.
     */

    public function clean() {
        return $this->db->query("DELETE FROM gfc_emails_queue WHERE is_sent = 1")->affectedRows();
    }


}