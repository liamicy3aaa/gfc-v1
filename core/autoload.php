<?php

spl_autoload_register('gfcAutoload');
// Autoloader function //
function gfcAutoload($className) {

    $class_name = strtolower($className);

    $default = "../core/classes/class." . $className . ".php";

    if(file_exists($default)) {

        require_once $default;

    }
}
?>