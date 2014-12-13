<?php

require_once 'MQF/Log.php';

/**
* Global registry for MultiQueue
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: Registry.php 1115 2009-03-13 12:31:05Z mortena $
*
*/
final class MQF_Registry
{
    private static $instance;           ///< The MQF_Registry instance

    private $databases = array();       ///< array of MQF_Database
    private $webservices = array();     ///< array of MQF_WebService
    private $objects = array();         ///< array of all types of objects

    private $caches = array();          ///< array of MQF_Cache
    private $mq = null;                 ///< MQF_MultiQueue

    private $sessionid;                 ///< SessionId

    private $clientversion = 0;         ///< Version of the Windows Client

    private $values = array();          ///< Values

    public $timer = null;              ///< Benchmark_Timer

    private $lastmarker = '';           ///< Last timer marker set

    private $markercount = 1;

    public $config;                     ///< Configuration object

    private $request_params;            ///< Request parameters from GET, POST or SHELL (args)

    public $logger = null;              ///< Zend_Log

    public $logid = '';

    public $agent = null;               ///< MQF_Agent

    /**
    * \brief Constructor
    */
    private function __construct()
    {
        if (class_exists('Benchmark_Timer', false)) {
            $this->timer = new Benchmark_Timer();
            $this->timer->start();
        }

        $this->logger = new Zend_Log(new Zend_Log_Writer_Null());
    }

    /**
    * \brief Create new static instance of Registry or return previously created instance.
    *
    * \return   Registry    Registry instance
    */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
    * \brief Get database
    *
    * \param    string              Database Id
    * \return   Object              Database object
    * \throw   Exception
    */
    public function getDatabaseById($id, $autocreate = true)
    {
        $tid = strtoupper(trim($id));

        if (!$tid) {
            throw new Exception("No Id given!");
        }

        if (!isset($this->databases[$tid])) {
            if (!$autocreate) {
                return false;
            }

            $class = 'MQF_Database_'.$id;

            $db = new $class();

            $this->registerDatabase($tid, $db);
        }

        return $this->databases[$tid];
    }

    /**
    *
    */
    public function registerDatabase($id, $database)
    {
        $tid = strtoupper($id);

        if (isset($this->databases[$tid])) {
            throw new Exception("Database $tid is already registered");
        }

        $this->databases[$tid] = $database;

        return $database;
    }

    /**
    *
    */
    public function unregisterDatabase($id)
    {
        $tid = strtoupper($id);

        if (isset($this->databases[$tid])) {
            $this->databases[$tid]->disconnect(true);
            unset($this->databases[$tid]);
        }
    }

    /**
    * \brief returns old cache or creates new based on id. User can freely use MQF_Cache's genKey for this...
    *
    * \param    string      id
    * \param    integer     ttl (optional)
    * \return   MQF_Cache    Cache
    */
    public function getCacheById($id, $ttl = 60)
    {
        if (!isset($this->caches[$id])) {
            $this->caches[$id] = new MQF_Cache($ttl);
        }

        return $this->caches[$id];
    }

    /**
    * \brief Get Webservice
    *
    * \param    string              WS Id
    * \return   Object              Webservice object
    * \throw   Exception
    */
    public function getWebserviceById($id)
    {
        $tid = strtoupper($id);

        if (!isset($this->webservices[$tid])) {
            if ($ws_service = $this->getConfigSettingDefault('webservices', "service_{$id}", MQF_MultiQueue::CONFIG_VALUE, false)) {
                $ws_internal = $this->getConfigSettingDefault('webservices', "internal_{$id}", MQF_MultiQueue::CONFIG_VALUE, false);
                $ws_timeout  = $this->getConfigSettingDefault('webservices', "timeout_{$id}", MQF_MultiQueue::CONFIG_VALUE, false);

                $opts = array('mqfinternal' => $ws_internal, 'mqfwsid' => $id);

                if ($ws_timeout and is_numeric($ws_timeout) and $ws_timeout > 0 and $ws_timeout < 60) {
                    $opts['connection_timeout'] = $ws_timeout;
                }

                MQF_Log::log("Trying to create webservice $id => $ws_service ($ws_internal)");

                $this->webservices[$tid] = MQF_Webservice::factory($ws_service, $opts);
            } else {
                $class = 'MQF_Webservice_'.$id;

                $this->webservices[$tid] = new $class();
            }
        }

        return $this->webservices[$tid];
    }

    /**
    *
    */
    public function getObjectById($id)
    {
        if (isset($this->objects[$id])) {
            return $this->objects[$id];
        }

        return false;
    }

    /**
    *
    */
    public function registerObject($id, $obj)
    {
        if (isset($this->objects[$id])) {
            throw new Exception("Object with id $id is already registered [".get_class($this->objects[$id])."]");
        }
        $this->objects[$id] = $obj;

        return $obj;
    }

    /**
    * \brief Set MQF_MultiQueue object
    */
    public function setMQ($mq)
    {
        if (!$this->mq instanceof MQF_MultiQueue) {
            $this->mq = $mq;
            $this->setAgent($mq->getAgent());
            $this->setClientIp($mq->getClientIp());

            return $mq;
        } else {
            throw new Exception("Trying to register MultiQueue in Registry when it is already registered!");
        }
    }

    /**
    * \brief get the MQF_MultiQueue object
    */
    public function getMQ()
    {
        if ($this->mq instanceof MQF_MultiQueue) {
            return $this->mq;
        } else {
            throw new Exception("No MultiQ object!");
        }
    }

    /**
     *
     */
    public function getUI()
    {
        if ($this->hasMQ()) {
            return $this->mq->getUI();
        }
        throw new Exception("MultiQ has no UI");
    }

    /**
    * \brief Do we have MQF_MultiQueue
    */
    public function hasMQ()
    {
        return ($this->mq instanceof MQF_MultiQueue) ? true : false;
    }

    /**
     *
     */
    public function setCRM(TP_CRM $crm)
    {
        if ($this->hasMQ()) {
            $this->getMQ()->setCRM($crm);
        }

        $this->setValue('__crm', $crm);

        return $crm;
    }

    /**
     *
     */
    public function getCRM()
    {
        if ($crm = $this->getValue('__crm')) {
            return $crm;
        } else {
            if ($this->hasMQ()) {
                if ($crm = $this->getMQ()->getCRM()) {
                    $this->setValue('__crm', $crm);

                    return $crm;
                }
            }
        }

        throw new Exception("There is no CRM object registered!");
    }

    /**
     *
     */
    public function setAgent(MQF_Agent $agent = null)
    {
        $this->agent = $agent;

        return $agent;
    }

    /**
     *
     */
    public function getAgent()
    {
        return $this->agent;
    }

    /**
    * \brief Set client IP address
    */
    public function setClientIp($ip)
    {
        $this->setValue('clientip', $ip);

        return $ip;
    }

    /**
    * \brief get client IP address
    */
    public function getClientIp()
    {
        if ($ip = $this->getValue('clientip')) {
            return $ip;
        } else {
            if (isset($_SERVER["REMOTE_ADDR"])) {
                return $_SERVER["REMOTE_ADDR"];
            } else {
                return false;
            }
        }
    }

    /**
    * \brief set company
    */
    public function setCompany($company)
    {
        $this->setValue('company', $company);

        return $company;
    }

    /**
    * \brief get company
    */
    public function getCompany()
    {
        return $this->getValue('company');
    }

    /**
    * \brief set session Id
    */
    public function setSessionId($id)
    {
        $this->sessionid = $id;

        return $id;
    }

    /**
    * \brief get session Id
    */
    public function getSessionId()
    {
        if ($this->getValue('execmode') == 'console') {
            return false;
        }
        if (!$this->sessionid) {
            throw new Exception("No SessionId in registry!");
        }

        return $this->sessionid;
    }

    /**
    * \brief get hwnd
    */
    public function getHWND()
    {
        if ($this->getValue('execmode') == 'console') {
            return false;
        }

        $sessionid = $this->getSessionId();
        $project   = $this->getValue('project');

        $hwnd = $project.'-'.$sessionid;

        return $hwnd;
    }

    /**
    * \brief set value
    */
    public function setValue($key, $value)
    {
        $this->values[$key] = $value;

        return $value;
    }

    /**
    * \brief get value
    */
    public function getValue($key)
    {
        if (!isset($this->values[$key])) {
            return false;
        }

        return $this->values[$key];
    }

    /**
    * \brief set marker in Benchmark_Timer
    */
    public function setMarker($marker, $addcount = true)
    {
        if ($this->timer) {
            if ($addcount) {
                $marker = $this->markercount.') '.$marker;
                $this->markercount++;
            }

            $this->timer->setMarker($marker);

            $this->lastmarker = $marker;
        }
    }

    /**
    * \brief Stop the Benchmark_Timer
    */
    public function stopTimer()
    {
        if ($this->timer) {
            $this->timer->stop();
            $this->lastmarker = 'Stop';
        }
    }

    /**
    * \brief get ouput from Benchmark_Timer
    */
    public function getTimerOutput($timeelapsed = false, $stoptimer = false)
    {
        if ($this->timer) {
            if ($this->lastmarker != '' or $stoptimer) {
                $this->timer->stop();
                $this->lastmarker = 'Stop';
            }

            if ($timeelapsed) {
                return $this->timer->timeElapsed('Start', $this->lastmarker);
            } else {
                return $this->timer->getOutput(true, 'plain');
            }
        } else {
            return "No timer output. Benchmark_Timer probably not loaded!";
        }
    }

    /**
    *
    */
    public function logTimeSinceMarker($marker, $string = false, $level = MQF_DEBUG)
    {
        if ($this->timer) {
            $newmarker = "Time since {$marker}";

            $this->setMarker($newmarker, false);

            $sec = $this->timer->timeElapsed($marker, $newmarker);

            $sec = round($sec, 4);

            if ($string) {
                MQF_Log::log($string." : Elapsed ".$sec.' sec ('.round($sec*1000)." ms)", $level);
            } else {
                MQF_Log::log("Elapsed since marker '{$marker}': ".$sec.' sec ('.round($sec*1000)." ms)", $level);
            }

            return $sec;
        } else {
            MQF_Log::log("No timer output. Benchmark_Timer probably not loaded!");

            return false;
        }
    }

    /**
    * @desc
    */
    public function setLogId($id)
    {
        $this->logid = $id;
    }

    /**
    * @desc
    */
    public function getLogId()
    {
        return $this->logid;
    }

    /**
     *
     * \fn setRequestParams($request_params)
     * \param $request_params array
     * \return void
     *
     * \throws Exception Request parameters allready set
     *
     */
    public function setRequestParams($request_params)
    {
        if ($this->request_params) {
            throw new Exception('Request parameters already set');
        }
        if (!is_array($request_params)) {
            throw new Exception('No request parameters to set.');
        }

        $this->request_params = $request_params;

        // Error handler needs this as a global variable
        global $GLOBAL_REQUEST_PARAMS;
        $GLOBAL_REQUEST_PARAMS = $request_params;
    }

    /**
     *
     * \fn getRequestParams()
     * \return the request params (from $_GET for example)
     *
     * \throws Exception Request parameters not set
     *
     */
    public function getRequestParams()
    {
        if (!isset($this->request_params)) {
            throw new Exception('Request parameters not set');
        }

        return $this->request_params;
    }

    /**
    *
    */
    public function getExecMode()
    {
        if ($mode = $this->getValue('execmode')) {
            return $mode;
        }

        if (isset($_SERVER['HTTP_HOST']) and isset($_SERVER['REQUEST_METHOD'])) {
            $mode = 'http';
        } elseif (isset($_SERVER['SHELL'])) {
            $mode = 'console';
        } else {
            throw new Exception("Unknown execution mode!");
        }

        $this->setValue('execmode', $mode);

        return $mode;
    }

    /**
     *
     * \fn getNextUniqueId()
     * \return int The next systemwide unique id
     *
     * \brief This id is based on the integer value of session id as string + counter value as string
     */

    public function getNextUniqueId()
    {
        // implement preg to check session id [0-9A-F] and check that it's dividable by 2
        if (!session_id()) {
            throw new Exception("Session id isn't hexadecimal: ".session_id());
        }

        $counter = $this->getMQ()->getValue('unique_counter');

        if (!$counter > 0) {
            $counter = 1;
        }

        $counter++;

        $this->getMQ()->setValue('unique_counter', $counter);

        $uid = base_convert(session_id(), 16, 10)."$counter";

        return $uid;
    }

    /**
     *
     * \fn initConfig()
     * \throws Exception Config allready loaded
     * \throws Exception Config file (config.ini) not found
     * \throws Exception Config file errors
     * \return void
     *
     * \brief Read default config file
     */
    public function initConfig()
    {
        $configfile = MQF_APPLICATION_PATH.'/config.ini';

        // Check that file exists
        if (!file_exists($configfile)) {
            throw new Exception("Config file ($configfile) not found");
        }

        $this->config = new Zend_Config_Ini($configfile, null);

        return true;
    }

    /**
     *
     * \fn getConfig()
     * \return the static config object
     * \brief Implemented to ensure backwards compatiblity
     * \todo Remove this when all code is updated
     * \deprecated
     */
    public function getConfig()
    {
        $reg = MQF_Registry::instance();

        if (!$reg->config) {
            $reg->initConfig();
        }

        return $reg->config;
    }

    /**
     *
     * \fn getConfigValue($group, $setting = '', $type = MQF_MultiQueue::CONFIG_VALUE)
     * \param string group
     * \param string field
     * \param int type (MQF_MultiQueue::CONFIG_VALUE or MQF_MultiQueue::CONFIG_GROUP)
     * \return mixed|false Returns false if the config value does not exist.
     *
     * \todo actually fix this and getConfigValueDefault
     * \brief Get value from config file
     */
    public function getConfigSetting($group, $setting = '', $type = MQF_MultiQueue::CONFIG_VALUE)
    {
        if ($type == MQF_MultiQueue::CONFIG_VALUE) {
            return $this->_internalGetConfigValue($group, $setting, true, null);
        } elseif ($type == MQF_MultiQueue::CONFIG_GROUP) {
            return $this->_internalGetConfigGroup($group, true, null);
        }
    }

    /**
     * \fn getConfigValueDefault($group, $setting = '', $type = MQF_MultiQueue::CONFIG_VALUE, $default = false)
     *
     * \param string group
     * \param string field
     * \param int type (MQF_MultiQueue::CONFIG_VALUE or MQF_MultiQueue::CONFIG_GROUP)
     * \param string default
     * \return mixed|false Returns false if the config value does not exist.
     *
     * \brief Get value from config file, don't throw exception
     * \todo Really implement (wrapper call right now)
     */
    public function getConfigSettingDefault($group, $setting = '', $type = MQF_MultiQueue::CONFIG_VALUE, $default = false)
    {
        if ($type == MQF_MultiQueue::CONFIG_VALUE) {
            return $this->_internalGetConfigValue($group, $setting, false, $default);
        } elseif ($type == MQF_MultiQueue::CONFIG_GROUP) {
            if (!is_array($default)) {
                throw new Exception("Default value for group has to be an array!");
            }

            return $this->_internalGetConfigGroup($group, false, $default);
        }
    }

    /**
     *
     * \fn _internalGetConfigValue($group, $setting, $throw, $default)
     * \param $group Config group
     * \param $setting Config setting
     * \param $throw Throw exception if it doesn't exist?
     * \param $default Default value, if it doesn't exist, and we aren't throwing
     * \return mixed
     *
     * \throws Exception No Config loaded
     * \throws Exception No such setting in group
     *
     * \brief internal method to get config values
     */
    private function _internalGetConfigValue($group, $setting, $throw, $default)
    {
        if (!$this->config instanceof Zend_Config_Ini) {
            return $default;
        }

        $confgroup = $this->config->$group;

        if (!$confgroup instanceof Zend_Config) {
            if ($throw) {
                throw new Exception("No such group ($group)");
            } else {
                MQF_Log::log("No {$group}.{$setting} so returning default ({$default})");

                return $default;
            }
        }

        $value = $confgroup->$setting;

        if (!$value and $default) {
            $value = $default;
        }

        MQF_Log::log("{$group}.{$setting} = $value");

        return $value;
    }

    /**
     *
     * \fn _internalGetConfigGroup($group, $throw, $default)
     * \param $group Config group
     * \param $throw Throw exception if it doesn't exist?
     * \param $default Default value, if it doesn't exist, and we aren't throwing
     * \return mixed
     *
     * \throws Exception No Config loaded
     * \throws Exception No such group
     *
     * \brief internal method to get config groups
     */
    private function _internalGetConfigGroup($group, $throw, $default)
    {
        if (!$this->config instanceof Zend_Config_Ini) {
            return $default;
        }

        $confgroup = $this->config->$group;

        if (!$confgroup instanceof Zend_Config) {
            if ($throw) {
                throw new Exception("No such setting group ($group)");
            } else {
                MQF_Log::log("No such {$group}, so returning default:\n".print_r($default, true));

                return $default;
            }
        }

        $array = array();

        if ($confgroup->count()) {
            foreach ($confgroup as $key => $setting) {
                $array[$key] = $setting;
            }

            return $array;
        } else {
            return $default;
        }
    }
}
