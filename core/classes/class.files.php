<?php

/**
 * Class files
 *
 * @author Liam McClelland
 *
 */

class files
{

    /**
     * files constructor.
     */

    public function __construct() {


    }

    /**
     * Validate File Type
     *
     * Allows use to check that the type of the file provided is within the allowed types.
     *
     * @param $file
     * @param $allowedTypes
     * @return bool
     * @throws Exception
     */
    public function validateFileType($file, $allowedTypes) {

        if(!file_exists($file)) {

            throw new Exception("Provided file link doesn't exist.");

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