<?php

    class notifications {

        
        public static function add($type = "info", $content) {
            
            if(!isset($_SESSION["_alerts"])) {
                
                $_SESSION["_alerts"] = array();
                
            }
            
            $_SESSION["_alerts"][] = array(
                                "type" => $type,
                                "content" => $content                                                           
                                 );            
            
        }
        
        public static function display() {
            
            if(isset($_SESSION["_alerts"]) && count($_SESSION["_alerts"]) >= 1) {
                
                $html = "";
                
                foreach($_SESSION["_alerts"] as $index => $alert) {
                    
                    $html .= "<div class='m-0 alert alert-" . $alert["type"] . " text-center alert-dismissible fade show' role='alert'>";
                    
                        $html .= $alert["content"];
                        
                        $html .= "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
                        
                            $html .= "<span aria-hidden='true'>&times;</span>";
                        
                        $html .= "</button>";
                        
                    $html .= "</div>";
                    
                    unset($_SESSION["_alerts"][$index]);            
                    
                }
                
                return $html;
                
            } else {
                
                return "";
                
            }
            
        }
        
        
    }