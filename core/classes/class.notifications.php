<?php

    class notifications {

        
        public static function add($type = "info", $content, $settings) {
            
            if(!isset($_SESSION["_notifications"])) {
                  //exit("STOPPED");
                $_SESSION["_notifications"] = array();
                
            }
            
            $note = array(
                "type" => $type,
                "content" => $content                                                           
            );
            
            if(isset($settings["dismiss"])) {
                
                $note["dismiss"] = false;
                
            }
            
            $_SESSION["_notifications"][] = $note; 
            
        }
        
        public static function display() {
            
            if(isset($_SESSION["_notifications"]) && count($_SESSION["_notifications"]) >= 1) {
                
                $html = "";
                
                foreach($_SESSION["_notifications"] as $index => $alert) {
                    
                    $html .= "<div class='m-0 alert alert-" . $alert["type"] . " text-center " . ((isset($alert["dismiss"])) ? "alert-dismissible " : "") . "fade show' role='alert'>";
                    
                        $html .= $alert["content"];
                        
                        if(isset($alert["dismiss"])) {
                       
                            $html .= "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
                            
                                $html .= "<span aria-hidden='true'>&times;</span>";
                            
                            $html .= "</button>";
                        
                        }
                        
                    $html .= "</div>";
                    
                    unset($_SESSION["_notifications"][$index]);            
                    
                }
                
                return $html;
                
            } else {
                
                return "";
                
            }
            
        }
        
        
    }