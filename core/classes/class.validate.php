<?php

class validate {

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

    public static function time($myTime) {

        $split = explode(":", $myTime);

        if(count($split) < 2 || count($split) > 2 || !self::numeric($split[0]) || !self::numeric($split[1])) {
            return false;
        }

        return true;
    }

    public static function numeric($number){

        return ctype_digit($number);

    }


}