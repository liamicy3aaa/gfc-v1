<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Class email
 *
 * @author Liam McClelland
 * @property string $template Id of the template chosen for the email.
 * @property string $username The username for the email account.
 * @property string $password The password for the email account.
 * @property PHPMailer $mail PHPMailer object.
 * @property cinema $cinema Reference to an instance of the cinema class.
 * @property array $content Array of content for the email.
 */

class email {

    private $template;
    private $username;
    private $password;
    private $mail;
    private $cinema;
    private $content;

    /**
     * email constructor.
     * @param cinema $cinemaClass
     * @param array|bool $server
     * @throws Exception
     */

    public function __construct($cinemaClass, $server = false){

        $this->cinema = $cinemaClass;
        $this->template = array();
        $info = $this->cinema->getCinemaInfo();

        $server = (($server !== false) ? $server : parse_ini_file("../app/email.ini"));

        $this->mail = new PHPMailer(true);

        $this->mail->isSMTP();                                        // Send using SMTP
        $this->mail->Host       = $server["host"];                    // Set the SMTP server to send through
        $this->mail->SMTPAuth   = true;                               // Enable SMTP authentication
        $this->mail->Username   = $server["username"];                // SMTP username
        $this->mail->Password   = $server["password"];                // SMTP password
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;     // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        $this->mail->Port       = $server["port"];                    // TCP port to connect to
        $this->mail->setFrom($server["account"], $info["name"]);


    }

    /**
     * Keep the connection alive
     *
     * Ensures the connection to the SMTP server remains active for the duration of the script.
     *
     * @return null
     */

    public function keepAlive(){
        $this->mail->SMTPKeepAlive = true;
    }

    /**
     * Close the connection
     *
     * Closes the connection to the SMTP server.
     *
     * @return null
     */

    public function close() {
        $this->mail->smtpClose();
    }

    /**
     * Add Recipient
     *
     * Enables you to add a recipient to the current email you intend to send.
     *
     * @param string $email User's email
     * @param string $name User's name
     * @throws Exception
     */

    public function addRecipient($email, $name = "") {

        $this->mail->addAddress($email, $name);

    }

    /**
     * Add Reply to
     *
     * Enables you to add a reply to contact to the current email you intend to send.
     *
     * @param string $email User's email
     * @param string $name User's name
     * @throws Exception
     */

    public function addReplyTo($email, $name) {

        $this->mail->addReplyTo($email, $name);

    }

    /**
     * Add CC
     *
     * Enables you to add a CC recipient to the current email you intend to send.
     *
     * @param string $email User's email
     * @param string $name User's name
     * @throws Exception
     */

    public function addCC($email, $name) {

        $this->mail->addCC($email, $name);

    }

    /**
     * Add BCC
     *
     * Enables you to add a BCC recipient to the current email you intend to send.
     * 
     * @param string $email User's email
     * @param string $name User's name
     * @throws Exception
     */

    public function addBCC($email, $name){

        $this->mail->addBCC($email, $name);

    }

    /**
     * Add Attachment
     *
     * Enables you to attach a file to the current email you intend to send.
     *
     * @param string $filePath Path to the file you wish to attach.
     * @param string $label The name you would to give the file.
     * @throws Exception
     */
    public function addAttachment($filePath, $label) {

        // Attachments
        $this->mail->addAttachment($filePath, $label);

    }

    /**
     * Set template
     *
     * Enables you to choose the template for the current email you intend to send.
     *
     * @param string $id Id of the email template.
     */

    public function setTemplate($id) {

        $template = self::getTemplate($id);

        if(!$template) {

            die("Invalid template id provided.");

        } else {

            $this->template["path"] = $template["path"];
            $this->template["required"] = $template["required"];

        }

    }

    /**
     * Get Template
     *
     * Enables you to retrieve information about a template using a template id.
     *
     * @param string $id Id of the email template.
     * @return array|bool Returns the template data if the template exists or false if it doesn't exist.
     */

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

    /**
     * Set Subject
     *
     * Enables you to set a subject for the current email you intend to send.
     *
     * @param string $subject The subject you wish give the email.
     */
    public function setSubject($subject) {

        $this->mail->Subject = $subject;

    }

    /**
     * Add Content
     *
     * Enables you to set the content for the email template you selected. Must include all the required pieces of data the template requires.
     *
     * @param array $data The array of data for the email.
     */

    public function addContent($data) {

        $required = $this->template["required"];

        // Checking the required data has been provided.
        foreach($required as $item) {

            if(!$data[$item]) {

                die("Missing $item from data array (" . __LINE__ . ")");

            }

        }

        $this->content = $data;

    }

    /**
     * Build email
     *
     * Generates the html for the email using the template and data.
     *
     * @return null
     */
    private function buildEmail() {

        $content = str_replace($this->template["required"], $this->content, file_get_contents($this->template["path"]));

        $this->mail->Body = $content;

    }

    /**
     * Send Email
     *
     * Triggers the sending of the current email.
     *
     * @return bool
     */
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

    /**
     * Test connection
     *
     * Allows us to test a SMTP configuration to ensure we can connect and successfully send an email using it.
     *
     * @param array $server Server configuration information. Used to test the SMTP connection.
     * @return array Returns the status and if present an error message for the request.
     * @throws Exception
     */
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

    /**
     * Clear
     *
     * Remove any set data from the email to prepare it for another iteration.
     */
    public function clear() {

        $this->mail->clearAllRecipients();
        $this->mail->clearAddresses();
        $this->mail->clearCCs();
        $this->mail->clearBCCs();
        $this->mail->clearReplyTos();
        $this->mail->clearAttachments();
        $this->mail->clearCustomHeaders();

    }

    /**
     * Help
     *
     * Provides some information about how the email class works.
     *
     * @return null
     */
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