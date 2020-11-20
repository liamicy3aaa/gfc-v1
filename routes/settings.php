<?php

$app->group("/Manage/settings", function(){

    $this->get("", function($request, $response, $args){

        $user = $this->get("user");
        $user->loginRequired();

        $cinema = $this->get("cinema");

        $returnObj = array(
            "_title" => "Settings",
            "_user" => $_SESSION["user"],
            "_page" => "settings",
            "socialDistancing" => (($cinema->getConfigItem("social_distancing")["value"] == 1) ? "text-success" : "")
        );

        return $this->manageView->render($response, "/settings/view.phtml", $returnObj);

    });

    $this->get("/{op}", function($request, $response, $args){

        $user = $this->get("user");
        $user->loginRequired();

        $cinema = $this->get("cinema");
        $bc = array();


        switch($args["op"]) {

            case "social-distancing":
                $bc["mod"] = "bookings";
                $bc["setting"] = "Social Distancing";

                // Getting items
                $socialDistancing = $cinema->getConfigItem("social_distancing")["value"];
                $socialDistancingH = $cinema->getconfigItem("social_distancing_spacing_horizontal")["value"]; // Horizontal distancing
                $socialDistancingV = $cinema->getconfigItem("social_distancing_spacing_vertical")["value"]; // Vertical distancing
                $socialDistancingD = $cinema->getconfigItem("social_distancing_spacing_diagonal")["value"]; // Diagonal distancing

                $html = str_replace(
                    array("%SD%", "%SDCHECK%", "%DCHECK%", "%SDV%", "%SDH%"),
                    array(
                        (($socialDistancing == 1) ? "" : "d-none"),
                        (($socialDistancing == 1) ? "checked" : ""),
                        (($socialDistancingD == 1) ? "checked" : ""),
                        $socialDistancingV,
                        $socialDistancingH
                    ),
                    file_get_contents("../templates/Manage/settings/partials/social-distancing.phtml"));
                break;

            case "email":
                $bc["mod"] = "General";
                $bc["setting"] = "Email Configuration";

                $email = parse_ini_file("../app/email.ini");

                $html = str_replace(
                    array("%HOST%", "%PORT%", "%ACCOUNT%", "%USERNAME%"),
                    array($email["host"], $email["port"], $email["account"], $email["username"]),
                    file_get_contents("../templates/Manage/settings/partials/email.phtml"));
                break;

            default:
                throw new \Slim\Exception\NotFoundException($request, $response);
                break;

        }

        $returnObj = array(
            "_title" => $bc["setting"] . "- Settings",
            "_user" => $_SESSION["user"],
            "_page" => "settings",
            "bc_mod" => $bc["mod"],
            "bc_setting" => $bc["setting"],
            "html" => $html
        );
        //var_dump($returnObj);
        //die();

        return $this->manageView->render($response, "/settings/viewSetting.phtml", $returnObj);


    });

    $this->post("/social-distancing/{op}", function($request, $response, $args){

        $user = $this->get("user");
        $user->loginRequired();

        $body = $request->getParsedBody();

        //return $response->withJson($body, 400);

        if(!isset($body["param"])) {

            return $response->withJson(array(
                "error" => "missing_data",
                "error_desc" => "Missing param parameter."
            ),400);

        }

        $cinema = $this->get("cinema");

        switch($args["op"]) {

            case "status":

                $allowedInput = array(0, 1);

                if (!in_array($body["param"], $allowedInput)) {

                    return $response->withJson(array(
                        "error" => "invalid_data",
                        "error_desc" => "Invalid param parameter."
                    ), 400);

                }

                $current = $cinema->getConfigItem("social_distancing")["value"];
                $update = $cinema->updateConfigItem("social_distancing", $body["param"]);

                $cinema->updateDistancingStatus("showing");

                return $response->withJson(array(
                    "status" => 200
                ), 200);
                break;

            case "distanceHorizontal":

                if(!ctype_digit($body["param"])) {

                    return $response->withJson(array(
                        "error" => "invalid_data",
                        "error_desc" => "Invalid param parameter."
                    ), 400);

                }

                $current = $cinema->getConfigItem("social_distancing_spacing_horizontal")["value"];
                $update = $cinema->updateConfigItem("social_distancing_spacing_horizontal", $body["param"]);

                $cinema->updateDistancingStatus("booking");

                return $response->withJson(array(
                    "status" => 200
                ), 200);
                break;

            case "distanceVertical":
                if(!ctype_digit($body["param"])) {

                    return $response->withJson(array(
                        "error" => "invalid_data",
                        "error_desc" => "Invalid param parameter."
                    ), 400);

                }

                $current = $cinema->getConfigItem("social_distancing_spacing_vertical")["value"];
                $update = $cinema->updateConfigItem("social_distancing_spacing_vertical", $body["param"]);

                $cinema->updateDistancingStatus("booking");

                return $response->withJson(array(
                    "status" => 200
                ), 200);
                break;

            case "distanceDiagonal":

                $allowedInput = array(0, 1);

                if (!in_array($body["param"], $allowedInput)) {

                    return $response->withJson(array(
                        "error" => "invalid_data",
                        "error_desc" => "Invalid param parameter."
                    ), 400);

                }

                $current = $cinema->getConfigItem("social_distancing_spacing_diagonal")["value"];
                $update = $cinema->updateConfigItem("social_distancing_spacing_diagonal", $body["param"]);

                $cinema->updateDistancingStatus("booking");

                return $response->withJson(array(
                    "status" => 200
                ), 200);
                break;

            default:
                return $response->withJson(array(
                    "error" => "server_error",
                    "error_desc" => "Unknown command provided"
                ), 500);

        }



    });

    $this->post("/email/{op}", function($request, $response, $args){

        $user = $this->get("user");
        $user->loginRequired();

        if($args["op"] == "test") {

            $required = array("account", "host", "port", "username", "password");
            $body = $request->getParsedBody();

            foreach($required as $item) {

                if(!isset($body[$item])) {

                    return $response->withJson(array(
                        "status" => 400,
                        "error" => "missing_data",
                        "error_desc" => "$item is missing from request."
                    ), 400);


                }

                if($item == "port") {

                    if(!ctype_digit($body[$item])) {

                    return $response->withJson(array(
                        "status" => 400,
                        "error" => "invalid_data",
                        "error_desc" => "Invalid port number provided."
                    ), 400);

                }

                } else {

                    $body[$item] = strip_tags($body[$item]);

                }

            }

            $userEmail = $user->getUserInfo(array(
                "user_email",
                "user_name"
            ));

            if(!$userEmail["status"]) {

                die("SERVER ERROR");

            }

            if(strlen($body["password"]) < 1) {

                $password = parse_ini_file("../app/email.ini")["password"];

            } else {

                $password = $body["password"];

            }

            $test = email::testConnection(array(
                "account" => $body["account"],
                "port" => $body["port"],
                "host" => $body["host"],
                "username" => $body["username"],
                "password" => $password,
                "recipient" => array(
                    "name" => $userEmail["data"]["user_name"],
                    "email" => $userEmail["data"]["user_email"]
                )
            ));

            //return $response->withJson($test, 400);

            if(!$test["status"]) {

                return $response->withJson(array(
                    "status" => 406,
                    "error" => "email_failed",
                    "error_desc" => $test["error"]
                ), 406);

            } else {

                return $response->withJson(array(
                    "status" => 200,
                ), 200);

            }

        } elseif($args["op"] == "save") {

            // SAVE STUFF HERE

        } else {

            return $response->withJson(array(
                "status" => false,
                "error" => "invalid_command",
                "error_desc" => "Invalid command provided to endpoint."
            ), 400);

        }

    });





});