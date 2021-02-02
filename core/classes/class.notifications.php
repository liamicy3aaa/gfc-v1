<?php

/**
 * Class notifications
 *
 * @author Liam McClelland
 */
    class notifications {

        /**
         * Add Notification
         *
         * Allows you to create a notification to display to the user.
         *
         * @param string $type Id of the type of notification you wish to add. Refer to bootstrap notification types for help.
         * @param string $content Text you wish to include in the notification
         * @param array $settings An optional array to control additional settings.
         */

        public static function add($type = "info", $content, $settings = array()) {

            // Check the notifications session is active. If not, create it.
            if(!isset($_SESSION["_notifications"])) {

                $_SESSION["_notifications"] = array();
                
            }

            // Create the notification object
            $note = array(
                "type" => $type,
                "content" => $content                                                           
            );

            // Is the dismiss setting set in the $settings array
            if(isset($settings["dismiss"])) {
                
                $note["dismiss"] = false;
                
            }

            // Add notification to the session array.
            $_SESSION["_notifications"][] = $note; 
            
        }

        /**
         * Display Notifications
         *
         * Allows you to display any outstanding notifications to the user.
         *
         * @param int $loc Control how the notification is displayed. 1 = normal, 2 = fixed to the top of the window.
         * @return string Returns the notification html to display on the page.
         */

        public static function display($loc = 1) {

            // Check if we have any notifications to show
            if(isset($_SESSION["_notifications"]) && count($_SESSION["_notifications"]) >= 1) {
                
                $html = "";

                // Loop through each row and generate html
                foreach($_SESSION["_notifications"] as $index => $alert) {
                    
                    $html .= "<div class='m-0 alert alert-" . $alert["type"] . " text-center " . ((isset($alert["dismiss"])) ? "alert-dismissible " : "") . (($loc == 2) ? "fixed-top " : "") . "fade show' role='alert'>";
                    
                        $html .= $alert["content"];

                            $html .= "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
                            
                                $html .= "<span aria-hidden='true'>&times;</span>";
                            
                            $html .= "</button>";
                        
                    $html .= "</div>";
                    
                    unset($_SESSION["_notifications"][$index]);            
                    
                }
                
                return $html;
                
            } else {
                
                return "";
                
            }
            
        }
        
        
    }