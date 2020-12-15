<?php


class files
{

    public function __construct() {


    }

    public function validateFileType($file, $allowedTypes) {

        if(!file_exists($file)) {
            throw new Exception("Provided file link doesn't exist.");

        //print "error_" . __LINE__;
        }

        if(!is_array($allowedTypes) || count($allowedTypes) < 1) {
            throw new Exception("No allowed types have been provided.");
        }

        $type = mime_content_type($file);
        if(!in_array($type, $allowedTypes)){
            throw new Exception("$type is not an allowed type.");

        } else {

            return true;

        }

    }

}