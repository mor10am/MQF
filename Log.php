<?php

define('MQF_DEBUG', 1);
define('MQF_INFO', 2);
define('MQF_NOTICE', 3);
define('MQF_WARN', 4);
define('MQF_WARNING', 4);
define('MQF_ERROR', 5);
define('MQF_ERR', 5);
define('MQF_FAILURE', 6);
define('MQF_FAIL', 6);
define('MQF_FATAL', 7);

require_once 'Zend/Log/Writer/Abstract.php';
require_once 'Zend/Log/Writer/Null.php';
require_once 'Zend/Log.php';

/**
 * \class MQF_Log
 * The MultiQueue logger
 *
 * \author Morten Amundsen <mortena@tpn.no>
 * \author Ken-Roger Andersen <kenny@tpn.no>
 * \author Magnus Espeland <magg@tpn.no>
 * \author Gunnar Graver <gunnar.graver@teleperformance.no>
 * \remark Copyright 2006-2007 Teleperformance Norge AS
 * \version $Id: Log.php 1109 2009-03-05 18:27:39Z mortena $
 *
 * There needs to be an entry in your /etc/syslog.conf file
 *
 * local0.*          /path/to/your/logfile.log
 *
 * When using FireBug and FirePHP logging
 * we need to manipulate the headers!
 *
 * <code>
 * $request = new Zend_Controller_Request_Http();
 * $response = new Zend_Controller_Response_Http();
 * $channel = Zend_Wildfire_Channel_HttpHeaders::getInstance();
 * $channel->setRequest($request);
 * $channel->setResponse($response);
 * </code>
 *
 * We run our own code, and log as before,
 * but have to flush the logged data at the end:
 *
 * <code>
 * $channel->flush();
 * $response->sendHeaders();
 * </code>
 *
 */
class MQF_Log extends Zend_Log_Writer_Abstract
{
    private $ident = 'MQF';    ///< Log Id

    /**
    * Constructor
    */
    public function __construct($ident = 'MQF')
    {
        $this->ident = $ident;
    }

    /**
    *
    */
    public function open()
    {
    }

    /**
    *
    */
    public function close()
    {
    }

    /**
    * \brief Write string to SYSLOG
    */
    protected function _write($fields)
    {
        $msg   = $fields['message'];
        $level = $fields['level'];

        switch ($level) {
        case Zend_Log::CRIT:
            $level = LOG_CRIT;
            break;
        case Zend_Log::ERR:
            $level = LOG_ERROR;
            break;
        case Zend_Log::WARN:
            $level = LOG_WARNING;
            break;
        case Zend_Log::INFO:
            $level = LOG_INFO;
            break;
        case Zend_Log::DEBUG:
        default:
            $level = LOG_DEBUG;
            break;
        }

        openlog($this->ident, LOG_PID, LOG_LOCAL0);
        syslog($level, $msg);
    }

    /**
    *
    */
    public function setOption($optionKey, $optionValue)
    {
    }

    /**
    *
    */
    public static function registerLogger($logfile, $project = 'MQF', $firebug = false)
    {
        define('MQF_PROJECT', $project);

        $reg = MQF_Registry::instance();

        if (!$reg->logger instanceof Zend_Log) {
            $reg->logger = new Zend_Log();
        }

        if ($logfile == 'syslog') {
            $reg->logger->addWriter(new MQF_Log($project));
        } else {
            if ($logfile) {
                $reg->logger->addWriter(new Zend_Log_Writer_Stream($logfile));
            }
        }

        if ($firebug) {
            require_once 'Zend/Version.php';

            if (Zend_Version::compareVersion('1.6.0') == -1) {
                $reg->logger->addWriter(new Zend_Log_Writer_Firebug());
            }
        }
    }

    /**
    * \brief Log string
    *
    * \param    string      Text string
    * \param    string      level
    * \param    string      Modulename
    */
    public static function log($string, $level = MQF_DEBUG, $module = '')
    {
        if (is_object($string)) {
            if ($string instanceof Exception) {
                $level = MQF_ERROR;

                if (isset($string->faultstring)) {
                    $faultstring = $string->faultstring;
                    $faultcode   = $string->faultcode;
                    $string      = $string->getMessage();

                    $string .= " [$faultcode] $faultstring";
                } else {
                    $code   = $string->getCode();
                    $string = "[$code] ".$string->getMessage();
                }
            } else {
                $string = "Instance of <".get_class($string).">";
            }
        } elseif (!is_string($string)) {
            $string = 'No log message';
        }

        $string = html_entity_decode($string);

        if (defined('MQF_LOGLEVEL_FILTER') === true) {
            if ($level != MQF_FORCE_DEBUG and $level < MQF_LOGLEVEL_FILTER) {
                return $string;
            }
        }

        $reg = MQF_Registry::instance();

        if (!$reg->logger) {
            throw new Exception("There is no logger registered!");
        }

        switch ($level) {
        case MQF_FATAL:
        case 'fatal':
        case 'critical':
            $level  = Zend_Log::CRIT;
            $levstr = 'CRITICAL';
            break;
        case MQF_ERROR:
        case 'error':
        case 'err':
        case 'e':
            $level  = Zend_Log::ERR;
            $levstr = 'ERROR';
            break;
        case MQF_WARN:
        case 'warn':
        case 'warning':
        case 'w':
            $level  = Zend_Log::WARN;
            $levstr = 'WARNING';
            break;
        case MQF_FAILURE:
        case 'fail':
        case 'failure':
        case 'f':
            $level  = Zend_Log::ALERT;
            $levstr = 'ALERT';
            break;
        case MQF_NOTICE:
        case 'notice':
        case 'n':
            $level  = Zend_Log::NOTICE;
            $levstr = 'NOTICE';
            break;
        case MQF_INFO:
        case 'info':
        case 'i':
            $level  = Zend_Log::INFO;
            $levstr = 'INFO';
            break;
        case MQF_DEBUG:
        case 'debug':
        case 'd':
        default:
            $level  = Zend_Log::DEBUG;
            $levstr = 'DEBUG';
            break;
        }

        $logstring = '';
        $destructor = false;

        if ($module == '') {
            $backtrace = debug_backtrace();
            $method    = '';

            foreach ($backtrace as $b) {
                if ($b['function'] == '__destruct') {
                    $destructor = true;
                    break;
                }
            }

            if (isset($backtrace[1]) and isset($backtrace[1]['class'])) {
                $method = $backtrace[1]['class'].$backtrace[1]['type'].$backtrace[1]['function'];
                $line   = $backtrace[0]['line'];
            } elseif (isset($backtrace[1])) {
                $method = $backtrace[1]['function'];
                $line   = $backtrace[0]['line'];
            }

            if ($method) {
                $logstring = "[$method";
                if ($line) {
                    $logstring .= "/$line";
                }
                $logstring .= "] ";
            }

            $logstring .= $string;
        } else {
            $logstring = "[$module] $string";
        }

        if (!$project = $reg->getValue('project')) {
            if (defined('MQF_PROJECT')) {
                $project = MQF_PROJECT;
            } else {
                $project = 'MQF';
            }
        }

        if ($logid = $reg->getLogId()) {
            $logid = " [$logid] ";
        }

        $ip = $reg->getClientIp();

        $logstring = "$ip [$project]{$logid}$logstring";

        if (!$destructor) {
            $reg->logger->log($logstring, $level);
        }

        return $string;
    }
}

/**
* \brief Output ADODB queries to the log
*/
function mqfADODBOutput($msg, $newline)
{
    MQF_Log::log("\n".strip_tags($msg)."\n", MQF_DEBUG);
}

define('ADODB_OUTP', 'mqfADODBOutput');
