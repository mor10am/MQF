<?php

require_once 'Zend/Controller/Request/Http.php';

final class MQF_Controller_Front
{
    private static $_instance     = null;

    private $_request      = null;
    private $executionmode = false;
    private $_ajax         = false;
    /**
    *
    */
    private function __construct()
    {
        $this->_request = new Zend_Controller_Request_Http();

        if ($this->_request->getHeader('X-Requested-With') == 'XMLHttpRequest') {
            $this->_ajax = true;
        }

        $this->_init();
    }

    /**
    *
    */
    public static function instance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
    *
    */
    public function getRequest()
    {
        return $this->_request;
    }

    public function isAjax()
    {
        return $this->_ajax;
    }

    /**
    *
    */
    private function _init()
    {
        $reg = MQF_Registry::instance();

        $reg->initConfig();

        $this->setExecutionMode('http');

        $reg->setRequestParams($this->_request->getParams());

        $project = $reg->getConfigSetting('mqf', 'project');

        $reg->setValue('logfile', $logfile);

        $logprofile = $reg->getConfigSettingDefault('logging', 'log_profiling', MQF_MultiQueue::CONFIG_VALUE, false);

        $reg->setValue('logprofile', $logprofile);

        $reg->setValue('project', $project);

        // Logging
        $logfile  = $reg->getConfigSettingDefault('logging', 'logfile', MQF_MultiQueue::CONFIG_VALUE, $project.'.log');
        $loglevel = $reg->getConfigSettingDefault('logging', 'loglevel', MQF_MultiQueue::CONFIG_VALUE, MQF_DEBUG);

        if ($logfile != 'syslog') {
            $logfile = MQF_APPLICATION_PATH.'/log/'.$logfile;
        }

        define('MQF_LOGLEVEL_FILTER', $loglevel);

        MQF_Log::registerLogger($logfile, $project);

        $cache_enable = $reg->getConfigSetting('mqf', 'cache_enable');

        /// \todo Cleanup needed: move to MQF_Cache->init()
        if ($cache_enable) {
            $cachedir = "tmp/cache";
            if (!file_exists($cachedir)) {
                mkdir($cachedir, 0777, true);
            }

            /// \todo Change registry to find this internally
            $reg->setValue('cachedir', $cachedir);
            MQF_Log::log('Cachedir is '.$cachedir);
        }

        if ($this->getExecutionMode() == 'http') {
            $reg->setValue('scripturl', 'http://'.$_SERVER['HTTP_HOST'].$_SERVER["SCRIPT_NAME"]);
            $reg->setValue('baseurl', 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER["SCRIPT_NAME"]));
            $reg->setValue('basedir', dirname($_SERVER["SCRIPT_FILENAME"]));
        }

        if (version_compare(phpversion(), "5.1.0", ">")) {
            if (version_compare(phpversion(), "5.2.0", ">")) {
                define('MQF_PHP_5_2_OK', true);
            } else {
                define('MQF_PHP_5_2_OK', false);
            }
        } else {
            throw new Exception("PHP5.1+ is required");
        }

        MQF_Session::setup($project);
    }

    /**
     *
     * \fn  setExecutionMode($mode)
     * \param $mode Execution mode (console or http)
     * \return void
     * \brief set the execution mode, both as instance variable, and global
     *
     *
     *
     */
    public function setExecutionMode($mode)
    {
        $this->executionmode = $mode;

        $reg = MQF_Registry::instance();
        $reg->setValue('execmode', $mode);
    }

    /**
     *
     * \fn getExecutionMode()
     * \throw Exception No execution mode set
     * \return string Execution mode
     *
     * \brief return the execution mode
     *
     */
    public function getExecutionMode()
    {
        if (!$this->executionmode) {
            throw new Exception('No execution mode set');
        }

        return $this->executionmode;
    }

    /**
     *
     * \fn  execute()
     *
     * \brief Executes the application
     *
     * \todo I've only changed the needed parts from index.php, so clean up the rest
     * \todo Remove $reg->setValue('starting'
     * \todo Consider creating a session class. Too much session stuff in here
     *
     */
    public function run()
    {
        $reg = MQF_Registry::instance();

        $request_params  = $reg->getRequestParams();
        $session_timeout = $reg->getConfigSetting('session', 'session_timeout');
        $project         = $reg->getConfigSetting('mqf', 'project');
        $logprofile      = $reg->getConfigSettingDefault('logging', 'log_profiling', MQF_MultiQueue::CONFIG_VALUE, false);

        /// \todo THIS SESSION TIMEOUT MAY BE VERY BUGGED
        session_cache_expire($session_timeout);
        ini_set("session.gc_maxlifetime", ($session_timeout*60));
        MQF_Log::log("Cache expire: ".session_cache_expire(), MQF_DEBUG);

        $mode = MQF_MultiQueue::analyzeRequest($request_params);

        switch ($mode) {
        // Execute method called from JavaScript
        case MQF_MultiQueue::MQF_EXECUTE_METHOD:

            if ($this->getExecutionMode() == 'http') {
                $exec = MQF_Executor::sessionStartPrevious($request_params);

                $mq = $reg->getMQ();

                if (!$mq->authenticate()) {
                    MQF_Authentication::notAuthenticated();
                }

                $reg->setMarker("Application initialized");

                $result = $exec->execute();

                $_SESSION['mqf'] = $mq;

                if ($mq instanceof MQF_MultiQueue) {
                    switch ($exec->getAjaxType()) {
                    case 'Scriptaculous.AutoCompleter':
                        header('Content-Type: text/html');
                        print $result;
                        break;
                    default:
                        $mq->getUI()->display($result, false, $exec->getExecId());
                    }
                } else {
                    throw new Exception("MQF_MultiQueue object not found!");
                }
            } else {
                $mq = new MQF_MultiQueue();
                $mq->init($request_params);

                $reg->setMarker("Application initialized");

                $exec = new MQF_Executor($request_params);

                print $exec->execute('console');
            }
            break;

        // Generate javascripts
        case MQF_MultiQueue::MQF_JAVASCRIPT:
            if ($this->getExecutionMode() == 'console') {
                throw new Exception("Mode MQF_JAVASCRIPT is not defined for console mode.");
            }

            $exec = MQF_Executor::sessionStartPrevious($request_params);

            $mq = $reg->getMQ();

            if (!$mq->authenticate()) {
                MQF_Authentication::notAuthenticated();
            }

            $reg->setMarker("Application initialized");

            $result = $exec->getJavaScripts();

            $_SESSION['mqf'] = $mq;

            header('Content-Type: text/javascript');
            print $result;

            break;

        // Start new MultiQueue session
        case MQF_MultiQueue::MQF_STARTUP:

            MQF_Log::log("MQF_STARTUP _GET: ".print_r($request_params, true), MQF_DEBUG);

            if ($this->getExecutionMode() == 'http') {
                /// \todo This needs more documentation

                $sessionidsrc = $reg->getConfigSettingDefault('session', 'session_id_source', MQF_MultiQueue::CONFIG_VALUE, SESSION_ID_SOURCE_REQUEST);

                switch ($sessionidsrc) {

                case SESSION_ID_SOURCE_COOKIE:

                    if (isset($_COOKIE['mqfsid'])) {
                        $sessionid = $_COOKIE['mqfsid'];
                    } else {
                        $sessionid = md5(time().mt_rand(1, 1000000).mt_rand(1, 10000));
                    }

                    setcookie('mqfsid', $sessionid, time() + $session_timeout);
                    break;

                case SESSION_ID_SOURCE_PARAMETER:

                    if (isset($request_params['sessionid'])) {
                        $sessionid = isset($request_params['sessionid']);
                    } else {
                        throw new Exception("No session id was given in request parameter!");
                    }
                    break;

                case SESSION_ID_SOURCE_REGENERATE:

                    $sessionid = md5(serialize($request_params.time()));
                    break;

                case SESSION_ID_SOURCE_REQUEST:
                default:

                    $sessionid = md5(serialize($request_params));
                    break;
                }

                MQF_Log::log("Created session Id: $sessionid", MQF_DEBUG);

                session_id($sessionid);
                session_name($project);
                session_start();

                $reg->setSessionId($sessionid);

                if (!isset($_SESSION['mqf'])) {
                    MQF_Log::log("Creating new MultiQueue object");

                    // No previous MQ found, so we create a new one
                    $mq = new MQF_MultiQueue();
                    $mq->init($request_params);

                    $_SESSION['mqf'] = $mq;
                } else {
                    // Use previous instance
                    $mq = $_SESSION['mqf'];

                    if ($mq instanceof MQF_MultiQueue) {
                        MQF_Log::log("Restarting previous MultiQueue object");
                        $reg->setMQ($mq);
                    } else {
                        throw new Exception("Session data is not MQ instance!");
                    }
                }

                $reg->setMarker("Application initialized");

                if (!$mq->authenticate()) {
                    MQF_Authentication::notAuthenticated();
                }

                $mq->getUI()->display(null, true);
            } else {
                $mq = new MQF_MultiQueue();
                $mq->init($request_params);

                if (!$mq->authenticate()) {
                    MQF_Authentication::notAuthenticated();
                }

                $reg->setMarker("Application initialized");
            }

            break;
        default:
            throw new Exception("Unknown action");
        }

        $logprofile = $reg->getValue('logprofile');
        $logfile    = $reg->getValue('logfile');

        $sec = $reg->getTimerOutput(true);

        MQF_Log::log('Time used: '.$sec.' sec ('.round($sec*1000)." ms)\n", MQF_INFO);

        if ($logprofile and $logfile != 'syslog') {
            MQF_Log::log("\n".$reg->getTimerOutput(), MQF_DEBUG);
        }
    }
}
