<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class email {

    private $template;
    private $username;
    private $password;
    private $mail;
    private $cinema;
    private $content;

    public function __construct($cinemaClass, $server = false){

        $this->cinema = $cinemaClass;
        $this->template = array();
        $info = $this->cinema->getCinemaInfo();

        $server = (($server !== false) ? $server : parse_ini_file("../app/email.ini"));

        $this->mail = new PHPMailer(true);

        //$this->mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
        $this->mail->isSMTP();                                            // Send using SMTP
        $this->mail->Host       = $server["host"];                    // Set the SMTP server to send through
        $this->mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $this->mail->Username   = $server["username"];                     // SMTP username
        $this->mail->Password   = $server["password"];                               // SMTP password
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        $this->mail->Port       = $server["port"];                                    // TCP port to connect to
        $this->mail->setFrom($server["account"], $info["name"]);


    }

    public function keepAlive(){
        $this->mail->SMTPKeepAlive = true;
    }

    public function close() {
        $this->mail->smtpClose();
    }

    public function addRecipient($email, $name = "") {

        $this->mail->addAddress($email, $name);

    }

    public function addReplyTo($email, $name) {

        $this->mail->addReplyTo($email, $name);

    }

    public function addCC($email, $name) {

        $this->mail->addCC($email, $name);

    }

    public function addBCC($email, $name){

        $this->mail->addBCC($email, $name);

    }

    public function addAttachment($filePath, $label) {

        // Attachments
        $this->mail->addAttachment($filePath, $label);

    }

    public function setTemplate($id) {

        $template = self::getTemplate($id);

        if(!$template) {
            die("Invalid template id provided.");
        } else {
            $this->template["path"] = $template["path"];
            $this->template["required"] = $template["required"];

        }

    }

    public static function getTemplate($id) {
        $data = array();

        switch($id) {

            case "booking_confirmation":
                $data["path"] = "../templates/Emails/booking/confirmation.phtml";
                $data["required"] = array("%CINEMA%", "%TIME%", "%SHOWTIME%", "%BOOKINGREF%", "%BOOKING_NAME%", "%FILM%", "%SCREEN%", "%SEATS%", "%CARD_TYPE%", "%CARD_LAST4%", "%COST%");
                break;

            case "booking_cancellation":
                $data["path"] = "../templates/Emails/booking/cancellation.phtml";
                $data["required"] = array("%CINEMA%", "%TIME%", "%SHOWTIME%", "%BOOKINGREF%", "%BOOKING_NAME%", "%FILM%", "%SCREEN%", "%SEATS%", "%REFUND_INFO%","%CARD_TYPE%", "%CARD_LAST4%", "%COST%");
                break;

            case "booking_moved":
                $data["path"] = "../templates/Emails/booking/alteration.phtml";
                $data["required"] = array("%CINEMA%", "%TIME%", "%SHOWTIME%", "%BOOKINGREF%", "%BOOKING_NAME%", "%FILM%", "%SCREEN%", "%SEATS%", "%COST%", "%ADMINMESSAGE%");
                break;

            case "general":
                $data["path"] = "../templates/Emails/general.phtml";
                $data["required"] = array("%CONTENT%");
                break;

            case "reset-password":
                $data["path"] = "../templates/Emails/account/reset-password.phtml";
                $data["required"] = array("%RESETLINK%", "%CINEMANAME%");
                break;

            case "password_updated":
                $data["path"] = "../templates/Emails/account/password-updated.phtml";
                $data["required"] = array("%CINEMANAME%");
                break;

            default:
                return false;
                break;
        }

        return $data;

    }

    public function setSubject($subject) {

        $this->mail->Subject = $subject;

    }

    public function addContent($data) {

        $required = $this->template["required"];

        foreach($required as $item) {

            if(!$data[$item]) {

                die("Missing $item from data array (" . __LINE__ . ")");

            }

        }

        $this->content = $data;

    }

    private function buildEmail() {

        $content = str_replace($this->template["required"], $this->content, file_get_contents($this->template["path"]));

        $this->mail->Body = $content;

    }

    public function send() {

        // Content
        $this->mail->isHTML(true);                                  // Set email format to HTML

        try {

            $this->buildEmail();
            $this->mail->send();

        } catch(Exception $e) {

            die("Error occurred: " . $e);

        }

        return true;

    }

    public static function testConnection($server) {

        $mail = new PHPMailer(true);
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = $server["host"];                    // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $server["username"];                     // SMTP username
        $mail->Password   = $server["password"];                               // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        $mail->Port       = $server["port"];                                    // TCP port to connect to
        $mail->setFrom($server["account"], $server["account"]);
        $mail->addAddress($server["recipient"]["email"], $server["recipient"]["name"]);
        $mail->Body = "TEST EMAIL MESSAGE FROM GFC CINEMA PLATFORM.";

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML

        try {

            $mail->send();

        } catch(Exception $e) {

            return array("status" => false, "error" => $e->getMessage());

        }

        return array("status" => true);

    }

    public function clear() {

        $this->mail->clearAllRecipients();
        $this->mail->clearAddresses();
        $this->mail->clearCCs();
        $this->mail->clearBCCs();
        $this->mail->clearReplyTos();
        $this->mail->clearAttachments();
        $this->mail->clearCustomHeaders();

    }


    public function Help() {

        $html = "<p>Welcome to the help screen. Below, please find the order that you need to call the functions in to successfully send an email.</p>";
        $html .= "<ol>";
            $html .= "<li>Call <span style='font-weight:bold;'>new email(\$cinemaClass);</span> where the \$cinemaClass is the instance of the Cinema class that is active.</li>";
            $html .= "<li>Set a template using the \$email->setTemplate(\$id). You should know the templates available in this script. If not, please take a look at the setTemplate() function.</li>";
            $html .= "<li>Set a recipient using the \$email->addRecipient(\$email, \$name) function. </li>";
            $html .= "<li>Set a subject for the email using the \$email->setSubject(\$subject) function.</li>";
            $html .= "<li>Set the content for the email using the \$email->addContent(Array \$data) function. Its important that you know the required fields for the template. Check the setTemplate function for more info.</li>";
            $html .= "<li>You can now send the email using the \$email->send() function. If an error occurs, this will be displayed on screen.</li>";
        $html .= "</ol>";

        die($html);
    }

}