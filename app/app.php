<?php
ob_end_clean();

// **PREVENTING SESSION HIJACKING**
// Prevents javascript XSS attacks aimed to steal the session ID
ini_set('session.cookie_httponly', 1);

// **PREVENTING SESSION FIXATION**
// Session ID cannot be passed through URLs
ini_set('session.use_only_cookies', 1);

// Uses a secure connection (HTTPS) if possible
ini_set('session.cookie_secure', 1);

session_name("GFC-AUTH");
session_start();
date_default_timezone_set("Europe/London");
error_reporting(0);
ini_set('display_errors', 1);
require '../vendor/autoload.php';
require '../core/write_ini_file.php';

// Registering autoloader //
require_once "../core/autoload.php";


$config['displayErrorDetails'] = false;
$config['addContentLengthHeader'] = false;
$config['debug'] = false;

$app = new \Slim\App($config);

$container = $app->getContainer();

$container['errorHandler'] = function ($container) {
    return function ($request, $response, $exception) use ($container) {
        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write("Oops, something's gone wrong!");
    };
};

$container['phpErrorHandler'] = function ($c) {
    return function ($request, $response, $error) use ($c) {
        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write(file_get_contents("../templates/errors/500.phtml"));
    };
};

$container["db"] = function() {



    try {

        $conn = parse_ini_file("../app/db.ini");

        if(!$conn) {

            throw new Exception("DEVELOPER: Database configuration file is missing. (LINE " . __LINE__ . " - " . __FILE__ . ")");

        }

    } catch(Exception $e) {

        die($e->getMessage());

    }

    return new db($conn["host"], $conn["username"], $conn["password"], $conn["database"]);

};

$container["files"] = function($container) {

    return new files();

};

$container["payments"] = function($container) {

    try {
        $keys = parse_ini_file("../app/stripe.ini");

        if (!$keys) {

            throw new Exception("DEVELOPER: Stripe configuration file is missing. (LINE " . __LINE__ . " - " . __FILE__ . ")");

        }
    } catch (Exception $e) {

        die($e->getMessage());

    }

    return new payments($keys["PUBLIC_KEY"], $keys["PRIVATE_KEY"], $container["db"]);

};

$container["cinema"] = function($container) {

    return new cinema(array(), $container["db"]);


};

$container["user"] = function($container) {
    
  return new user($container["db"]);  
    
};

$container["emailQueue"] = function($container) {

    return new emailQueue($container["db"], $container["cinema"]);
};

$container['notAllowedHandler'] = function ($container) {
    return function ($request, $response, $methods) use ($container) {

        if($request->getMethod() == "OPTIONS") {

            return $response->withStatus(200)->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');

        }
        return $response->withStatus(405)
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->write(json_encode(array("status"=>405,"error"=>"invalid_request_method","error_description"=>"Invalid request method used to call API.")));
    };
};

// Client info
//if(!isset($_SESSION["system"])) {

    $client = $container["cinema"]->getCinemaInfo();

    $_SESSION["system"] = $client;

//}

// UI Renderer
$phpView = new \Slim\Views\PhpRenderer('../templates/', [
    "title" => $_SESSION["system"]["name"],
    "__webTitle" => $_SESSION["system"]["name"]
    ]);

$phpView->setLayout("layout.phtml");

$container['view'] = $phpView;

// Manage view

$manageView = new \Slim\Views\PhpRenderer('../templates/Manage/', [
    "title" => $_SESSION["system"]["name"],
    "__webTitle" => $_SESSION["system"]["name"]
    ]);
    
    $manageView->setLayout("index.phtml");
    
    $container["manageView"] = $manageView;

//Override the default Not Found Handler after App
unset($app->getContainer()['notFoundHandler']);

$container['notFoundHandler'] = function ($c) {

    return function ($request, $response) use ($c) {

        $newResponse = new \Slim\Http\Response(404);
        $error = "";
        
        $error .= file_get_contents("../templates/errors/error_header.phtml");
        $error .= file_get_contents("../templates/errors/404.phtml");
        $error .= file_get_contents("../templates/errors/error_footer.phtml");
        
        $errorScreen = str_replace("##SYSTEMTITLE##", $_SESSION["system"]["name"], $error);
    
             return $newResponse->write($errorScreen);

    };
};