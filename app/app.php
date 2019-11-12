<?php
ob_end_clean();
session_name("GFC-AUTH");
session_start();
date_default_timezone_set("Europe/London");

require '../vendor/autoload.php';

// Registering autoloader //
spl_autoload_register('gfcAutoload');


// Autoloader function //
function gfcAutoload($className) {

    $class_name = strtolower($className);

    $default = "../core/classes/class." . $class_name . ".php";

    if(file_exists($default)) {

        require_once $default;

    }
}


$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;
$config['debug'] = true;

$app = new \Slim\App($config);

$container = $app->getContainer();

$container["db"] = function() {

    $conn = parse_ini_file("../app/db.ini");

    return new db($conn["host"], $conn["username"], $conn["password"], $conn["database"]);

};

$container["cinema"] = function($container) {

    return new cinema(array(), $container["db"]);


};

$container["user"] = function($container) {
    
  return new user($container["db"]);  
    
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