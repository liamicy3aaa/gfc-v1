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

    public function __construct($cinemaClass){

        $this->cinema = $cinemaClass;
        $this->template = array();
        $info = $this->cinema->getCinemaInfo();

        $server = parse_ini_file("../app/email.ini");

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

        switch($id) {

            case "booking_confirmation":
                $this->template["path"] = "../templates/Emails/booking/confirmation.phtml";
                $this->template["required"] = array("%CINEMA%", "%TIME%", "%SHOWTIME%", "%BOOKINGREF%", "%BOOKING_NAME%", "%FILM%", "%SCREEN%", "%SEATS%", "%CARD_TYPE%", "%CARD_LAST4%", "%COST%");
                break;

            case "booking_cancellation":
                $this->template["path"] = "../templates/Emails/booking/cancellation.phtml";
                $this->template["required"] = array("%CINEMA%", "%TIME%", "%SHOWTIME%", "%BOOKINGREF%", "%BOOKING_NAME%", "%FILM%", "%SCREEN%", "%SEATS%", "%REFUND_INFO%","%CARD_TYPE%", "%CARD_LAST4%", "%COST%");
                break;

            case "general":
                $this->template["path"] = "../templates/Emails/general.phtml";
                $this->template["required"] = array("%CONTENT%");
                break;

            default:
                die(__LINE__ . " Invalid template selected.");
                break;
        }

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