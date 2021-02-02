<?php

/**
 * Class crons
 *
 * @author Liam McClelland
 * @property string $salt Used for storing the unique salt code that forms part of the secret unencrypted string.
 */

class crons {

    public static $salt = "4324jl342jldfFDf4fdasAdwe342423";

    /**
     * Generate Secret
     *
     * Generate the secret for a cron to allow us to validate a cron run request.
     *
     * @param string $cronName Name of the cron you wish to generate a secret for
     * @return bool|string If cron doesn't exist, it returns false otherwise it returns the secret.
     */

    public static function generateSecret($cronName) {

        // Check the cron exists
        if(!self::cronExists($cronName)){
            return false;
        }

        // Returned the encrypted secret
        return cipher::encrypt($cronName . ":" . self::$salt);

    }

    /**
     * Validate Secret
     *
     * Validate a secret to ensure the requested cronjob run is requested using a valid secret.
     * @param string $secret The secret for the cron you wish to run
     * @param string $cronName The name of the secret you intend to run
     * @return bool Will return true if the secret is valid or false if it doesn't.
     */

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

    /**
     * Start Cron Handler
     *
     * This handles all the server logic for the cronjob requests.
     *
     * @param \Slim\Http\Request $request A Slim Request object
     * @param \Slim\Http\Response $response A Slim Response object
     * @param array $args Arguments provided within the request URI
     * @param \Slim\App $app A Slim App object for accessing global items from the container.
     * @return \Slim\Http\Response A Slim Response object
     * @throws \Slim\Exception\NotFoundException
     */

    public static function startHandler($request, $response, $args, $app) {

        // Check auth parameter has been provided with the request
        if(!isset($_GET["auth"])){

            return $response->withStatus(400);

        } else {

            // Check the secret provided in the auth parameter matches the cron name provided in the request URI.
            if(!self::validateSecret($_GET["auth"], $args["id"])) {

                return $response->withStatus(401);

            }
        }

        // Sanitize the name
        $name = self::sanitizeName($args["id"]);

        // Check the cron exists and can be ran.
        if(self::cronExists($args["id"])) {

            // Load the cron
            self::loadCron($args["id"], $request, $response, $args, $app);

        } else {

            // Return a 404 error.
            throw new \Slim\Exception\NotFoundException($request, $response);

        }


    }

    /**
     * Sanitize Name
     *
     * Ensures the name of the cron only contains letters and digits.
     *
     * @param string $name
     * @return string
     */

    public static function sanitizeName($name) {

        return preg_replace('/[^A-Za-z0-9 \?!]/', '', $name);

    }

    /**
     * Cron Job Exists
     *
     * Check a cronjob with a provided name exists on the system.
     *
     * @param string $cronName
     * @return bool
     */

    public static function cronExists($cronName) {

        return file_exists("../core/crons/cron." . $cronName . ".php");

    }

    /**
     * Load Cron
     *
     * Load and run a cron on the system.
     *
     * @param string $cronName
     * @param \Slim\Http\Request $request
     * @param \Slim\Http\Response $response
     * @param array $args
     * @param \Slim\App $app
     * @return void
     */
    public static function loadCron($cronName, $request, $response, $args, $app) {

        include "../core/crons/cron." . $cronName . ".php";

    }

}