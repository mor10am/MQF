<?php

/**
* Executor. Executes methods in modules and returns result to client
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: Executor.php 1026 2008-03-18 19:19:42Z mortena $
*
*/
final class MQF_Executor
{
    private $returntype = 'element';    ///< Return type. 'element' = HTML and 'object' = callback
    private $sessionid;                 ///< Session Id
    private $modulemethod;              ///< Module.Method we're going to execute
    private $module;                    ///< Module
    private $method;                    ///< Method
    private $target;                    ///< Target DOM ID for this request
    private $callback;                  ///< Target JavaScript function callback
    private $createnew = false;         ///< Create new session?
    private $params = array();
    private $options = array();
    private $execid = 0;
    private $_ajaxtype = 'MQX';
    /**
    * \brief Constructor
    */
    public function __construct($request)
    {
        if (!is_array($request) or count($request) == 0) {
            throw new Exception("Input should be request array");
        }

        $this->_setupExecutor($request);
    }

    /**
     *
     */
    public function getAjaxType()
    {
        return $this->_ajaxtype;
    }

    /**
    *
    */
    public function getExecId()
    {
        return $this->execid;
    }

    /**
    * \brief Set return type
    *
    * \param string type ('element' or 'object')
    */
    public function setReturnType($type)
    {
        $this->returntype = $type;
    }

    /**
    * \brief Get JavaScripts from module that are automatically created.
    */
    public function getJavaScripts()
    {
        $mq = MQF_Registry::instance()->getMQ();

        if (($module = MQF_UI::find($this->module)) === false) {
            $msg = "$modname not found! : ".$module->getMessage();
            MQF_Log::log($msg, MQF_ERROR);
            throw new Exception($msg);
        }

        // Method is set in "_setupExecutor". Should be "getJavascripts".
        $ret = $module->executeMethod($this->method, array());

        if ($module->hasException()) {
            $e   = $module->getException();
            $ret = '';
        }

        return $ret;
    }

    /**
    * \brief Execute method on selected module
    *
    * \param string mode
    */
    public function execute($mode = 'http')
    {
        MQF_Log::log("Trying to execute '{$this->modulemethod}()'", MQF_DEBUG);

        $path = explode('.', $this->modulemethod);

        if (count($path) < 2) {
            throw new Exception("Error in method '$modulemethod'. Should be on the form '<modulename>.<method>'.");
        }

        $method = array_pop($path);

        $modname = implode('.', $path);

        $module = array_pop($path);

        $reg = MQF_Registry::instance();

        $mq = $reg->getMQ();
        $ui = $mq->getUI();

        if (strtolower($module) == 'executor' and strtolower($method) == 'getxml') {
            MQF_Log::log("$modname.$method()", MQF_DEBUG);

            return $this->getXML();
        } elseif (strtolower($module) == 'ui' and strtolower($method) == 'changecanvas') {
            MQF_Log::log("$modname.$method()", MQF_DEBUG);

            $ui->setCurrentCanvas($this->params[0]);

            return true;
        } elseif (strtolower($module) == 'ui' and strtolower($method) == 'insertdynamicmodule') {
            MQF_Log::log("$modname.$method()", MQF_DEBUG);

            $module = $ui->getCurrentCanvas()->getId();
        }

        if ($reg->getValue('execmode') == 'console') {
            $parentcanvas = $reg->getConfigSettingDefault('mqf', 'canvas', MQF_MultiQueue::CONFIG_VALUE, 'MQF_Canvas');

            if (($module = $ui->getModuleById($module, MQF_UI::MQF_FIND_MODULE_ALL, $parentcanvas)) === false) {
                $msg = "$modname not found! : ".$module->getMessage();
                MQF_Log::log($msg, MQF_ERROR);
                throw new Exception($msg);
            }
        } else {
            if (($module = MQF_UI::find($module)) === false) {
                $msg = "$modname not found! : ".$module->getMessage();
                MQF_Log::log($msg, MQF_ERROR);
                throw new Exception($msg);
            }
        }

        if ($this->target != '') {
            MQF_Log::log("Target for module $modname = '{$this->target}'", MQF_DEBUG);
            $module->setTargetId($this->target);
        }

        try {
            if ($mq->getAuthObject() and $mode == 'http') {
                if (!$mq->isModuleAuth($module->getId())) {
                    throw new Exception("You are not authorized to run method $method in module ".$module->getId());
                }
            }

            $module->clearReturnValue();

            $ret = $module->executeMethod($method, $this->params);
        } catch (Exception $e) {
            MQF_Log::log('Exception: '.$e->getMessage(), MQF_ERROR);

            if ($mode == 'http') {
                $module->setErrorMessage($e->getMessage());
                $module->setException($e);
                $module->noRefresh();
            } else {
                throw new Exception($e->getMessage());
            }
        }

        if ($mode == 'http') {
            switch ($this->getAjaxType()) {
            case 'Scriptaculous.AutoCompleter':
                $module->noRefresh();

                return $ret;
                break;
            case 'Scriptaculous.EditInPlace':
                $module->noRefresh();
            default:
                if ($module->hasException()) {
                    return MQF_Executor::getExceptionXML($module->getException(), $this->execid);
                }
                if ($this->returntype == 'object') {
                    if (!$module->hasReturnValue()) {
                        $module->setReturnValue($ret, $this->callback);
                        $this->options['retvalxml'] = $module->getReturnValue(MQF_UI_Module::RETVAL_AS_XML);
                    }
                }

                return $ui->getCanvasXML($this->options, $this->execid);
            }
        } else {
            return $ret;
        }
    }

    /**
    * \brief Create a callback XML for a given MQF_ReturnValue
    *
    * \param MQF_ReturnValue
    * \return string XML data
    */
    public static function getCallbackXML(MQF_UI_ReturnValue $returnvalue, $execid = 0)
    {
        $xml = '<?xml version="1.0" encoding="utf-8" ?>';

        $xml .= "\n<ROOTNODE>\n";
        $xml .= "<ajax-response execid='{$execid}'>\n";
        $xml .= "<response id='mqfCallbackBroker' type='object'><![CDATA[\n";
        $xml .= MQF_Tools::JsonEncode($returnvalue);
        $xml .= "\n]]></response>";
        $xml .= "</ajax-response>\n</ROOTNODE>";

        return $xml;
    }

    /**
    * \brief Create a callback XML document for a given Exception
    *
    * \param Exception
    * \param integer execution id
    * \param string  JavaScript callback method
    * \return string XML data
    */
    public static function getExceptionXML($e, $execid = 0, $jsmethod = '')
    {
        MQF_Log::log(print_r($e, true));

        if ($jsmethod == '') {
            $jsmethod = MQF_Registry::instance()->getConfigSettingDefault('gui', 'jsexeptionmethod', MQF_MultiQueue::CONFIG_VALUE, 'mqfThrowException');
        }

        $e->client_ipaddress = MQF_Registry::instance()->getClientIp();
        $e->server_ipaddress = $_SERVER["SERVER_ADDR"];
        $e->server_port      = $_SERVER["SERVER_PORT"];

        return MQF_Executor::getCallbackXML(new MQF_UI_ReturnValue($e, $jsmethod), $execid);
    }

    /**
    * \brief Setup the MQF_Executor for a give request (GET or POST)
    *
    * \param array Request
    */
    private function _setupExecutor($request)
    {
        $target   = '';
        $callback = '';

        if (isset($request['F'])) {
            list($modulemethod, $sesstarget) = explode('@', $request['F']);

            $tmp = explode('!', $sesstarget);

            $sessionid = $tmp[0];
            if (isset($tmp[1])) {
                $target = $tmp[1];
            }
        } elseif (isset($request['JS'])) {
            list($module, $sessionid) = explode('@', $request['JS']);
            $method       = 'getJavascripts';
            $modulemethod = $module.'.'.$method;
        }

        if (isset($request['N']) and $request['N'] == 'true') {
            $create_new = true;
        } else {
            $create_new = false;
        }

        if (isset($request['R'])) {
            $returntype = $request['R'];
        }

        $params  = array();
        $options = array();

        foreach ($request as $key => $value) {
            $substr = substr(trim($key), 0, 2);

            if ($substr == 'P_') {
                list($tag, $offset) = explode('_', trim($key));
                if (($data = MQF_Tools::decodeJSONObject(stripslashes($value))) === false) {
                    $msg = MQF_Log::log("JSON decoding failed for parameter '$tag'", MQF_ERROR);
                    throw new Exception($msg);
                } else {
                    $params[$offset] = $data->p;
                }
            }

            if ($substr == 'O_') {
                list($tag, $optionkey) = explode('_', trim($key));

                if ($optionkey != '') {
                    if (($data = MQF_Tools::decodeJSONObject(stripslashes($value))) === false) {
                        MQF_Log::log("JSON decoding failed for option '$tag'", MQF_WARNING);
                    } else {
                        $options[$optionkey] = $data->o;
                    }
                }
            }
        }

        if (!isset($options['targetid'])) {
            if ($target != '') {
                $options['targetid'] = $target;
            }
        }

        if (isset($options['execid'])) {
            $this->execid = $options['execid'];
        } else {
            $this->execid = 0;
        }

        if (isset($options['urltype'])) {
            switch (strtoupper(trim($options['urltype']))) {
            case 'EIP':
                $params[] = $request['editorId'];
                $params[] = $request['value'];
                $this->_ajaxtype = 'Scriptaculous.EditInPlace';
                break;
            case 'AUTOCOMPLETE':
                $params[] = $request[$options['autocompletevar']];
                $this->_ajaxtype = 'Scriptaculous.AutoCompleter';
                break;
            default:
            }
        }

        if ($returntype == 'object' and isset($options['id'])) {
            $callback = $options['id'];
            MQF_Log::log("Javascript Callback = $callback");
        }

        $t      = explode('.', $modulemethod);
        $module = $t[0];
        $method = $t[1];

        $this->sessionid = $sessionid;
        $this->target = $target;
        $this->callback = $callback;
        $this->params = $params;
        $this->options = $options;
        $this->modulemethod = $modulemethod;
        $this->module = $module;
        $this->method = $method;
        $this->returntype = $returntype;
        $this->createnew = $create_new;

        return true;
    }

    /**
    * \brief Create new session?
    */
    public function createNew()
    {
        return $this->createnew;
    }

    /**
    * \brief Get registered SessionId
    */
    public function getSessionId($request = array())
    {
        if (!$this->sessionid) {
            $this->sessionid = md5(serialize($request.time()));
        }

        return $this->sessionid;
    }

    /**
    * \brief Start a previously created session based on a request
    *
    * \param array Request
    * \return MQF_Executor
    *
    */
    public static function sessionStartPrevious($request)
    {
        $reg = MQF_Registry::instance();

        $exec = new MQF_Executor($request);

        $sessionid = $exec->getSessionId($request);

        $reg->setSessionId($sessionid);

        session_id($sessionid);
        session_start();

        MQF_Log::log("Sessionid: '$sessionid'");

        if (!isset($_SESSION['mqf']) and !$exec->createNew()) {
            $ctrl = MQF_Controller_Front::instance();
            if ($ctrl->isAjax()) {
                throw new Exception("ERROR!\nUnable to start the application!\nYour session has probably timed out because of being unactive too long.\nTry pressing F5 or Refresh/Reload in your browser.");
            } else {
                header("Location: ".$reg->getValue('scripturl'));
                exit;
            }
        } else {
            if ($exec->createNew()) {
                MQF_Log::log("Creating new session", MQF_INFO);
                $_SESSION['mqf'] = new MQF_MultiQueue();
            }
        }

        $mq = $_SESSION['mqf'];

        if (!$mq instanceof MQF_MultiQueue) {
            throw new Exception("ERROR!\nUnable to start the application!\nData in session is not MQ instance. Try pressing F5 or Refresh/Reload in your browser.");
        }

        if (!$exec->createNew()) {
            $reg->setMQ($mq);
        }

        if ($logid = $mq->getLogId()) {
            MQF_Log::log("LogId: $logid");
        }

        return $exec;
    }
}
