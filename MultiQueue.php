<?php

define('MQF_FORCE_DEBUG', 100);

/**
* \class MQF_MultiQueue
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: MultiQueue.php 1017 2008-03-13 17:07:17Z mortena $
*
*/
class MQF_MultiQueue
{
    const MQF_EXECUTE_METHOD = 1;
    const MQF_STARTUP = 2;
    const MQF_BACK_HANDLER = 3;
    const MQF_JAVASCRIPT = 4;
    const MQF_STARTUP_INHERIT = 5;

    const CONFIG_VALUE = 1;
    const CONFIG_GROUP = 2;

    private $app_version = '2.0b4';               ///< Version
    private $app_date = '21.02.2008';           ///< Version date

    private $config = null;                     ///< Config object
    private $configname = 'config';             ///< Config filename

    private $values = array();                  ///< Collection of userdefined key/value pairs

    private $clientversion = false;             ///< Version of MQ exe file if used

    private $project;   ///< MQ Project ID

    private $company;   ///< MQ Company

    private $ui;        ///< MQF_UI

    private $crm;       ///< TP_CRM

    private $request;   ///< Current request

    private $clientip;  ///< IP address of calling client

    private $auth = null;   ///< MQF_Auth or null

    private $agent = null;  ///< MQF_Agent or null

    private $logid = '';    ///< Used for prefixing loglines to easily follow a session

    /**
    * \brief Constructor
     * \todo $mqenabled should be strict (no default)
     */
    public function __construct()
    {
        $reg = MQF_Registry::instance();

        if (!defined('MQF_INCPATH_SEP')) {
            $os = new OS_Guess();

            if ($os->getSysname() == 'windows') {
                define('MQF_INCPATH_SEP', ';');
            } else {
                define('MQF_INCPATH_SEP', ':');
            }

            $reg->setValue('os', $os);
        }

        ini_set('include_path', ini_get('include_path').MQF_INCPATH_SEP.dirname(__FILE__));

        $reg->setMQ($this);

        $this->ui = new MQF_UI();
        $this->ui->init();
    }

    /**
    * \brief Get project ID
    *
    * \return   string  Project Id
    */
    public function getProject()
    {
        if (!$this->project) {
            // DO NOT LOG HERE! It'll just loop, since the logger asks every time
            return 'MQF_NO_PROJECT_ID';
        }

        return $this->project;
    }

    /**
    * \brief Set current project for this MQ session
    *
    * \param string Project
    */
    public function setProject($project)
    {
        $project = trim(strtoupper($project));
        $this->project = $project;
    }

    /**
    *
    */
    protected function registerAgent(MQF_Agent $agent)
    {
        $this->agent = $agent;

        MQF_Registry::instance()->setAgent($agent);

        $this->setClientIp($agent->getIp());
    }

    /**
    *
    */
    public function getAgent()
    {
        return $this->agent;
    }

    /**
     *
     */
    public function setCRM(TP_CRM $crm)
    {
        $this->crm = $crm;
    }

    /**
     *
     */
    public function getCRM()
    {
        return $this->crm;
    }

    /**
    * @desc
    */
    public function setLogId($id)
    {
        $this->logid = $id;
        MQF_Registry::instance()->setLogId($id);
    }

    /**
    * @desc
    */
    public function getLogId()
    {
        if ($this->logid) {
            MQF_Registry::instance()->setLogId($this->logid);
        }

        return $this->logid;
    }

    /**
    * \brief Initalize new MultiQueue
    *
    * \param    Array               MQ startup variables. Can for example be $_GET or $_POST.
    * \throw    Exception
    *
    */
    public function init($input = array())
    {
        $this->request = $input;

        $reg      = MQF_Registry::instance();
        $execmode = $reg->getValue('execmode');

        if (isset($input['selgerkode']) and isset($input['avdeling2']) and isset($input['rrid']) and isset($input['client_ip'])) {
            $this->registerAgent(new MQF_Agent($input));
        }

        if (isset($input['mqver'])) {
            $parts = explode('.', trim($input['mqver']));
            if (count($parts) > 1) {
                $mq_version = implode('', $parts);
            } else {
                $mq_version = $parts[0];
            }

            $this->setClientVersion($mq_version);
        }

        if (!$company = $reg->getConfigSetting('mqf', 'company')) {
            throw new Exception("Company not defined in Config!");
        }

        $this->setCompany($company);

        if (!$project = $reg->getConfigSetting('mqf', 'project')) {
            throw new Exception("Project not defined in Config!");
        }

        $this->setProject($project);

        if ($authclass = $reg->getConfigSettingDefault('mqf', 'authclass', MQF_MultiQueue::CONFIG_VALUE, false)) {
            $this->auth = new MQF_Authentication(new $authclass());
        }

        if ($reg->getConfigSettingDefault('mqf', 'crmenabled', MQF_MultiQueue::CONFIG_VALUE, false)) {
            MQF_Registry::instance()->setCRM(TP_CRM::instance());
        }

        return true;
    }

    /**
     *
     */
    public function getUI()
    {
        return $this->ui;
    }

    /**
    * \brief Check if a module is authorized
    */
    public function isModuleAuth($id)
    {
        if ($this->auth instanceof MQF_Authentication) {
            return $this->auth->isModuleAuth($id);
        } else {
            return true;
        }
    }

    /**
    * \brief Get MQF_Auth object
    */
    public function getAuthObject()
    {
        if (!$this->hasAuthObject()) {
            return false;
        } else {
            return $this->auth;
        }
    }

    /**
    * \brief Check if MultiQueue has an auth object
    */
    public function hasAuthObject()
    {
        if ($this->auth) {
            return true;
        } else {
            return false;
        }
    }

    /**
    *
    */
    public function authenticate()
    {
        if ($this->hasAuthObject()) {
            return $this->auth->authenticate($this->request);
        } else {
            return true;
        }
    }

    /**
    * \brief Returns the original HTTP request
    *
    * \return array Request
    */
    public function getRequest()
    {
        return $this->request;
    }

    /**
    * \brief Set company
    *
    * \param string company
    */
    public function setCompany($company)
    {
        $reg = MQF_Registry::instance();
        $reg->setCompany($company);
        $this->company = $company;
    }

    /**
    * \brief Get the company
    *
    * \return string company
    */
    public function getCompany()
    {
        return $this->company;
    }

    /**
    * \brief Set client IP address
    */
    public function setClientIp($ip)
    {
        $this->clientip = $ip;
        $reg = MQF_Registry::instance();
        $reg->setClientIp($ip);
    }

    /**
    * \brief Get client IP address
    */
    public function getClientIp()
    {
        if ($this->clientip) {
            return $this->clientip;
        } else {
            if (isset($_SERVER["REMOTE_ADDR"])) {
                $ip = $_SERVER["REMOTE_ADDR"];
                $this->setClientIp($ip);

                return $ip;
            } else {
                $ip = '127.0.0.1';
                $this->setClientIp($ip);

                return $ip;
            }
        }
    }

    /**
    * \brief Analyze the request to see what mode we are in
    *
    * \param array request
    * \return string mode
    */
    public static function analyzeRequest($req)
    {
        if (isset($req['F']) and isset($req['R'])) {
            MQF_Log::log(print_r($req, true));
            MQF_Log::log("MQF_EXECUTE_METHOD");

            return self::MQF_EXECUTE_METHOD;
        } else {
            MQF_Log::log("default MQF_STARTUP");

            return self::MQF_STARTUP;
        }

        return false;
    }

    /**
    * \brief Set value
    */
    public function setValue($name, $value)
    {
        $this->values[$name] = $value;
    }

    /**
    * \brief get value
    */
    public function getValue($name)
    {
        if (isset($this->values[$name])) {
            return $this->values[$name];
        }
    }

    /**
    * \brief has value
    */
    public function hasValue($name)
    {
        return isset($this->values[$name]);
    }

    /**
    * @desc
    */
    public function setClientVersion($v)
    {
        $this->clientversion = $v;
        MQF_Log::log("Client version: $v");
    }

    /**
    * @desc
    */
    public function getClientVersion()
    {
        return $this->clientversion;
    }
}
