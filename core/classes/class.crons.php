<?php

class crons {

    public static $salt = "4324jl342jldfFDf4fdasAdwe342423";

    public static function generateSecret($cronName) {

        if(!self::cronExists($cronName)){
            return false;
        }

        return cipher::encrypt($cronName . ":" . self::$salt);

    }

    public static function validateSecret($secret, $cronName) {

        $decrypted = explode(":", cipher::decrypt($secret));
        $cron = $decrypted[0];
        $salt = $decrypted[1];

        if($cron !== $cronName || $salt !== self::$salt) {
            return false;
        } else {
            return true;
        }

    }

    public static function startHandler($request, $response, $args, $app) {

        if(!isset($_GET["auth"])){
            return $response->withStatus(400);
        } else {
            if(!self::validateSecret($_GET["auth"], $args["id"])) {

                return $response->withStatus(401);

            }
        }

        $name = self::sanitizeName($args["id"]);

        if(self::cronExists($args["id"])) {
            self::loadCron($args["id"], $request, $response, $args, $app);
        } else {
            throw new \Slim\Exception\NotFoundException($request, $response);
        }


    }

    public static function sanitizeName($name) {

        return preg_replace('/[^A-Za-z0-9 \?!]/', '', $name);

    }

    public static function cronExists($cronName) {

        return file_exists("../core/crons/cron." . $cronName . ".php");

    }

    public static function loadCron($cronName, $request, $response, $args, $app) {

        include "../core/crons/cron." . $cronName . ".php";

    }

}