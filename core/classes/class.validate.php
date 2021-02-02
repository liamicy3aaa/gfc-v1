<?php

/**
 * Class validate
 *
 * @author Liam McClelland
 */

class validate {

    /**
     * Date Validator
     *
     * Check a date matches a chosen format.
     *
     * @param string $mydate The date you wish to validate
     * @param string $format The date format you wish to validate the $mydate input with.
     * @return bool Returns true if it is in the set format, false if it isn't.
     */

    public static function date($mydate,$format = 'DD-MM-YYYY') {

        if ($format == 'YYYY-MM-DD') list($year, $month, $day) = explode('-', $mydate);
        if ($format == 'YYYY/MM/DD') list($year, $month, $day) = explode('/', $mydate);
        if ($format == 'YYYY.MM.DD') list($year, $month, $day) = explode('.', $mydate);

        if ($format == 'DD-MM-YYYY') list($day, $month, $year) = explode('-', $mydate);
        if ($format == 'DD/MM/YYYY') list($day, $month, $year) = explode('/', $mydate);
        if ($format == 'DD.MM.YYYY') list($day, $month, $year) = explode('.', $mydate);

        if ($format == 'MM-DD-YYYY') list($month, $day, $year) = explode('-', $mydate);
        if ($format == 'MM/DD/YYYY') list($month, $day, $year) = explode('/', $mydate);
        if ($format == 'MM.DD.YYYY') list($month, $day, $year) = explode('.', $mydate);

        if (is_numeric($year) && is_numeric($month) && is_numeric($day))
            return checkdate($month,$day,$year);
        return false;
    }

    /**
     * Time Validator
     *
     * Check the time is a valid time string.
     *
     * @param string $myTime
     * @return bool Returns true if its a valid time input, false if not.
     */

    public static function time($myTime) {

        $split = explode(":", $myTime);

        if(count($split) < 2 || count($split) > 2 || !self::numeric($split[0]) || !self::numeric($split[1])) {
            return false;
        }

        return true;
    }

    /**
     * Numeric Validator
     *
     * Check the value only contains digits.
     *
     * @param int|string $number
     * @return bool Returns true if the input only contains digits, false if not.
     */

    public static function numeric($number){

        return ctype_digit($number);

    }


}