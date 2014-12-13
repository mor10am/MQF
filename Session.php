<?php

/**
*
*/
final class MQF_Session
{
    private $project;
    public static $instance = null;

    private function __construct($project)
    {
        $project = trim($project);
        if (strlen($project) == 0) {
            throw new Exception("We need a valid project to start session. Project was blank.");
        }
        $this->project = $project;
    }

    /**
    *
    */
    public static function setup($project)
    {
        self::$instance = new self($project);

        $reg = MQF_Registry::instance();

        $sessionhandler = $reg->getConfigSettingDefault('session', 'handler', MQF_MultiQueue::CONFIG_VALUE, 'file');

        switch ($sessionhandler) {
        case 'adodb.session2':
            self::$instance->_adodbSession2();
            break;
        case 'file':
        default:
            break;
        }
    }

    /**
    *
    */
    private function _adodbSession2()
    {
        global $ADODB_SESSION_DRIVER;
        global $ADODB_SESSION_CONNECT;
        global $ADODB_SESSION_USER;
        global $ADODB_SESSION_PWD;
        global $ADODB_SESSION_DB;
        global $ADODB_SESS_DEBUG;

        $reg = MQF_Registry::instance();

        $ADODB_SESSION_DRIVER  = $reg->getConfigSetting('session', 'driver');
        $ADODB_SESSION_CONNECT = $reg->getConfigSetting('session', 'host');
        $ADODB_SESSION_USER    = $reg->getConfigSetting('session', 'username');
        $ADODB_SESSION_PWD     = $reg->getConfigSetting('session', 'password');
        $ADODB_SESSION_DB      = strtolower('sessions_'.$this->project);
        $ADODB_SESSION_DEBUG   = $reg->getConfigSettingDefault('session', 'debug', MQF_MultiQueue::CONFIG_VALUE, false);

        include_once 'adodb/adodb-exceptions.inc.php';
        include_once 'adodb/adodb.inc.php';
        include 'adodb/session/adodb-session2.php';

        if (!class_exists('ADODB_Session', false)) {
            throw new Exception("ADODB Session class was not loaded!");
        }

        MQF_Log::log("Session adodb.session2: host={$ADODB_SESSION_CONNECT} using driver {$ADODB_SESSION_DRIVER}");

        return true;
    }
}
