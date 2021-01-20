<?php

class emailQueue {

    private $db;
    private $cinema;
    private $user;
    private $runtime_timeout;

    public function __construct($db, $cinema) {

        if(!is_a($db, "db") || !is_a($cinema, "cinema")) {
            throw new Exception("An error occurred initializing one of the parameters for emailQueue. (" . __LINE__ . ")");
        }

        $this->db = $db;
        $this->cinema = $cinema;
        $this->runtime_timeout = 50;
    }


    public function add($recipient, $template, $subject, $content, $settings = array()) {

        if(!isset($recipient["name"]) || !isset($recipient["email"])) {
            return array("status" => false, "error" => "Missing user info.");
        }

        if(!filter_var($recipient["email"], FILTER_VALIDATE_EMAIL)) {
            return array("status" => false, "error" => "Invalid email provided.");
        }

        // Check template is valid
        $emailTemplate = email::getTemplate($template);

        if(!$emailTemplate) {
            return array("status" => false, "error" => "Invalid template id provided.");
        }

        // Valid content for email meets the template requirements
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

        foreach($toProcess as $id => $item) {

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

    private function markAsSent($id) {
        $this->db->query("UPDATE gfc_emails_queue SET is_sent = 1 WHERE id = ?", $id);
        return true;
    }

    private function getEmails($limit = 50) {
        return $this->db->query("SELECT * FROM gfc_emails_queue WHERE send_at <= " . time() . " AND is_sent = 0 LIMIT $limit")->fetchAll();
    }

    public function clean() {
        return $this->db->query("DELETE FROM gfc_emails_queue WHERE is_sent = 1")->affectedRows();
    }


}