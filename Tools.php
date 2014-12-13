<?php

/**
* \class MQF_Tools
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: Tools.php 1111 2009-03-09 11:08:13Z mortena $
*
*/
abstract class MQF_Tools
{
    const MON = 1;
    const TUE = 2;
    const WED = 3;
    const THU = 4;
    const FRI = 5;
    const SAT = 6;
    const SUN = 7;

    const FIRST  = 1;
    const SECOND = 2;
    const THIRD  = 3;
    const FOURTH = 4;
    const FIFTH  = 5;

    /**
    *
    */
    public static function isPhone($phone)
    {
        if (!is_numeric($phone)) {
            return false;
        }
        if (strlen($phone) != 8) {
            return false;
        }
        if (($phone > 20000000 and $phone < 80000000) or $phone > 90000000) {
            return true;
        }

        return false;
    }

    /**
    * \brief
    */
    public static function isMobilePhone($phone)
    {
        if (!MQF_Tools::isPhone($phone)) {
            return false;
        }
        if (substr($phone, 0, 1) == '4' or substr($phone, 0, 1) == '9') {
            return true;
        }

        return false;
    }

    /**
    * \brief
    */
    public static function isSocialSecurityNumber($ssnum, $countrycode = 'nor')
    {
        switch ($countrycode) {
        case 'nor':
            return self::_isSoSecNor($ssnum);
        default:
            throw new Exception("Unknown country code '$countrycode'");
        }
    }

    /**
    *
    */
    private static function _isSoSecNor($ssnum)
    {
        if (strlen($ssnum) != 11 or !is_numeric($ssnum)) {
            return false;
        }

        $s = array();

        for ($i = 1; $i <= strlen($ssnum); $i++) {
            $s[$i] = substr($ssnum, $i-1, 1);
        }

        $k1 = $s[1]*3 + $s[2]*7 + $s[3]*6 + $s[4]*1 + $s[5]*8 + $s[6]*9 + $s[7]*4 + $s[8]*5 + $s[9]*2;

        $k1 = $k1 % 11;

        if ($k1 != 1) {
            if ($k1 != 0) {
                $k1 = 11 - $k1;
            }

            $k2 = $s[1]*5 + $s[2]*4 + $s[3]*3 + $s[4]*2 + $s[5]*7 + $s[6]*6 + $s[7]*5 + $s[8]*4 + $s[9]*3 + $k1*2;

            $k2 = $k2 % 11;

            if ($k2 != 1) {
                if ($k2 != 0) {
                    $k2 = 11 - $k2;
                }

                if ($s[10] == $k1 and $s[11] == $k2) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
    *
    */
    public static function isUTF8($string)
    {
        // From http://w3.org/International/questions/qa-forms-utf-8.html
        return preg_match('%^(?:
              [\x09\x0A\x0D\x20-\x7E]            # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
            |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
        )*$%xs', $string);
    }

    /**
    *
    */
    public static function utf8($string, $uppercase = false)
    {
        if (is_object($string)) {
            if (!$string instanceof stdClass) {
                return $string;
            }

            $arr = get_object_vars($string);
            foreach ($arr as $key => $value) {
                $string->$key = self::utf8($value, $uppercase);
            }

            return $string;
        } elseif (is_array($string)) {
            foreach ($string as $key => $value) {
                $string[$key] = self::utf8($value, $uppercase);
            }

            return $string;
        } else {
            if (self::isUTF8($string)) {
                $cnt = 0;
                do {
                    $org = $string;
                    $string = utf8_decode($string);
                    if (!self::isUTF8($string)) {
                        break;
                    }
                    $cnt++;
                } while (self::isUTF8($string) and $cnt < 4);

                $string = $org;

                if ($uppercase) {
                    return mb_strtoupper($string);
                }

                return $string;
            } else {
                if ($uppercase) {
                    return mb_strtoupper(utf8_encode($string));
                }

                return utf8_encode($string);
            }
        }
    }

    public static function iso88591($string, $uppercase = false)
    {
        if (is_object($string)) {
            if (!$string instanceof stdClass) {
                return $string;
            }

            $arr = get_object_vars($string);
            foreach ($arr as $key => $value) {
                $string->$key = self::iso88591($value, $uppercase);
            }

            return $string;
        } elseif (is_array($string)) {
            foreach ($string as $key => $value) {
                $string[$key] = self::iso88591($value, $uppercase);
            }

            return $string;
        } else {
            if (self::isUTF8($string)) {
                $cnt = 0;
                do {
                    $string = utf8_decode($string);
                    $cnt++;
                } while (self::isUTF8($string) and $cnt < 4);

                $string = utf8_encode($string);

                $string = mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');

                if ($uppercase) {
                    return mb_strtoupper($string);
                }

                return $string;
            } else {
                if ($uppercase) {
                    return mb_strtoupper($string);
                }

                return $string;
            }
        }
    }

    /**
    *
    */
    public static function jsonEncode($value)
    {
        if (defined('MQF_PHP_5_2_OK')) {
            return json_encode($value);
        } else {
            return Zend_Json::encode($value);
        }
    }

    /**
    *
    */
    public static function jsonDecode($value)
    {
        if (defined('MQF_PHP_5_2_OK')) {
            return json_decode($value);
        } else {
            return Zend_Json::decode($value);
        }
    }

    /**
    *
    */
    public static function decodeJSONObject($string)
    {
        $data = Zend_Json::decode($string, Zend_Json::TYPE_OBJECT);

        if ($data instanceof stdClass) {
            if ($data->mqfClassName) {
                $class = $data->mqfClassName;

                $obj = new $class();

                if (is_a($obj, $class)) {
                    $arr = get_object_vars($data);
                    unset($arr['mqfClassName']);
                    foreach ($arr as $key => $value) {
                        $obj->$key = $value;
                    }
                    $data = $obj;
                } else {
                    MQF_Log::log("JSON object is not instance of $class", MQF_WARNING);
                }
            }
        }

        return $data;
    }

    /**
    *
    */
    public static function convertExceptionToStdClass($e)
    {
        $ex = null;

        if ($e instanceof Exception) {
            $ex = new StdClass();
            $ex->message = htmlspecialchars($e->getMessage());
            $ex->file = $e->getFile();
            $ex->line = $e->getLine();

            $ex->trace = array();
            foreach ($e->getTrace() as $t) {
                $t['function_name'] = $t['function'];
                $t['class_name']    = $t['class'];

                if (is_array($t['args']) and count($t['args']) > 0) {
                    foreach ($t['args'] as $key => $arg) {
                        $t['args'][$key] = self::fixValue($arg);
                    }
                }

                $ex->trace[] = $t;
            }
        }

        return $ex;
    }

    /**
    *
    */
    public static function fixValue($value, $expand = true)
    {
        if (is_object($value)) {
            if ($value instanceof stdClass) {
                if ($expand) {
                    $f = array();
                    foreach ($value as $field => $val) {
                        $f[] = $field." => ".self::fixValue($val, false);
                    }

                    return "stdClass Object (".implode(', ', $f).")";
                } else {
                    return "stdClass Object (...)";
                }
            } else {
                return get_class($value)." Object";
            }
        } elseif (is_array($value)) {
            if ($expand) {
                $f = array();
                foreach ($value as $key => $val) {
                    $f[] = self::fixValue($val, false);
                }

                return "Array [".implode(', ', $f)."]";
            } else {
                return "Array [...]";
            }
        } else {
            return "'".$value."'";
        }
    }

    /**
    *
    */
    public static function toXML($data, $rootname = 'ROOT', $header = true)
    {
        if ($header) {
            $xml = '<?xml version="1.0" encoding="utf-8" ?>'."\n\n";
        } else {
            $xml = '';
        }

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (is_array($data)) {
            $xml .= "<$rootname>\n";

            if (count($data)) {
                foreach ($data as $nodename => $nodedata) {
                    if (is_numeric($nodename)) {
                        $nodename = $rootname.'_node_'.$nodename;
                    }

                    $xml .= MQF_Tools::toXML($nodedata, $nodename, false);
                }
            }

            $xml .= "</$rootname>\n";

            return $xml;
        } else {
            $xml .= "<$rootname>{$data}</{$rootname}>\n";

            return $xml;
        }
    }

    /**
     * Return data as a SimpleXML object
     *
     */
    public static function toXMLObject($data, $rootname = 'ROOT', $header = true)
    {
        $xml = self::toXML($data, $rootname, $header);

        return simplexml_load_string($xml);
    }

    /**
    * @desc Check if today is the Nth day of the month
    *
    * To check if today is the second tuesday of the month
    *
    * <code>
    *
    *  MQF_Tools::isTodayNthDayOfMonth(2, 2);
    *
    * </code>
    *
    * 1 = Monday
    * ..
    * ..
    * 7 = Sunday
    *
    */
    public static function isTodayNthDayOfMonth($n, $day)
    {
        if (!$n_day = self::_calcNthDate($n, $day, date('m'), date('Y'))) {
            return false;
        }

        return (date('j') == $n_day) ? true : false;
    }

    /**
    * @desc
    */
    public static function getTimestampForNthDayOfMonth($n, $day, $month = false, $year = false)
    {
        if (!$month) {
            $month = date('m');
        }
        if (!$year) {
            $year = date('Y');
        }

        if (!$n_day = self::_calcNthDate($n, $day, $month, $year)) {
            return false;
        }

        return strtotime("{$month}/{$n_day}/{$year}");
    }

    /**
    * @desc
    */
    protected static function _calcNthDate($n, $day, $month, $year)
    {
        if ($n < 1 or $n > 5) {
            return false;
        }
        if ($day < 1 or $n > 7) {
            return false;
        }

        $date = "{$month}/1/{$year}";

        $day_of_first = date('N', strtotime($date));

        if ($day_of_first > $day) {
            $n_day = ((7 - ($day_of_first - $day)) + 1) + (7 * ($n - 1));
        } else {
            $n_day = ((7 - ($day_of_first - $day)) + 1) + (7 * ($n - 2));
        }

        if ($n_day > date('t', strtotime($date))) {
            return false;
        }

        return $n_day;
    }
}
