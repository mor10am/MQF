<?php

/**
 *
 * \class MQF_UI_Canvas_UTP
 *
 * \version $Id: UTP.php5 953 2008-01-20 17:03:52Z mortena $
 */


class MQF_UI_Canvas_UTP extends MQF_UI_Canvas
{
    private $sales = null;

    protected $projectmodule = '';
    protected $projectcustomername = '';

    protected $dialer; ///< MQF_Dialer object

    public $calldata_out; ///< Parsed data from dialer

    protected $dialerdata = array();

    protected $application_title = 'MQF UTP';

    protected $agent = null;

    protected $call_start = 0;
    protected $call_end   = 0;

    protected $followup            = false;
    protected $allowfollowup       = false;
    protected $allowcalldataupdate = false;

    protected $manualapp      = false;
    protected $allowmanualapp = false;

    protected $transferlist = array();      ///< Assoc array of STATUS => PHONENUMBER to do on terminate call

    protected $sdfclass;

    const RECORDER_START_APP      = 1;
    const RECORDER_START_VER      = 2;
    const RECORDER_STOP_VER      = 3;
    const RECORDER_STOP_APP      = 4;

    /**
    *
    */
    public function __construct($options = array())
    {
        $options['tplplugin'] = $this;

        parent::__construct($options);

        $this->call_start = time();
    }

    /**
    *
    */
    protected function init()
    {

        $reg = MQF_Registry::instance();

        $req_params = $reg->getRequestParams();

        $this->project   = $reg->getConfigSetting('mqf', 'project');
        $this->sessionid = $reg->getSessionId();

        if(isset($req_params['followup']) and $req_params['followup']) $this->setFollowup(true);
        $this->export('followup');

        if(isset($req_params['manualapp']) and $req_params['manualapp']) $this->setManualApp(true);
        $this->export('manualapp');

        if ($this->isFollowup() and $this->isManualApp()) {
            throw new Exception("Unable to start application which is both FollowUp and ManualApp!");
        }

        $this->allowfollowup = $reg->getConfigSettingDefault('dialer', 'allowfollowup', MQF_MultiQueue::CONFIG_VALUE, false);
        $this->export('allowfollowup');

        $this->allowmanualapp = $reg->getConfigSettingDefault('dialer', 'allowmanualapp', MQF_MultiQueue::CONFIG_VALUE, false);
        $this->export('allowmanualapp');

        $sdf = $reg->getConfigSetting('dialer', 'sdf', MQF_MultiQueue::CONFIG_VALUE);

        $sdfclass = 'MQF_Dialer_EShare_SDF_' . strtoupper(trim($sdf));

        $this->allowcalldataupdate = $reg->getConfigSettingDefault('dialer', 'allowcalldataupdate', MQF_MultiQueue::CONFIG_VALUE, false);
        $this->export('allowcalldataupdate');

        try {
            $this->dialer = MQF_Dialer::getDialer();
        } catch (Exception $e) {
            if (isset($req_params['manualappnewid'])) {

                if (!is_numeric($req_params['calldata'])) {
                    throw new Exception("Calldata for New Manual App should be a TPNID!");
                }

                $this->dialer = new MQF_Dialer_CTCFollowup(trim($req_params['calldata']));

                $sdf = new $sdfclass;

                $sdf->CAMP     = $this->project;
                $sdf->ID       = $req_params['calldata'];
                $sdf->LISTNAME = 'M0';

                foreach ($sdf as $field => $value) {
                    $this->dialer->setDataField($field, $value, MQF_OVERWRITE);
                }

            } else {
                throw $e;
            }
        }

        $this->export('dialerdata');

        $moduleclass = "Module_" . $this->project;

        $this->projectmodule = $moduleclass;

        $module = new $moduleclass($this->dialerdata);

        $this->projectcustomername = $module->getProjectCustomerName();
        $this->export('projectcustomername');

        if (!$module instanceof MQF_Sales) throw new Exception("The project module for project {$this->project} is not a child of MQF_Sales!");

        if (!$agent = $reg->getMQ()->getAgent()) {
            throw new Exception("No agent object in MQ. Check parameters: selgerkode, avdeling2, rrid, client_ip");
        }

        $module->registerAgent($agent);

        $this->addModule($module);

        $reg->getMQ()->setLogId($module->getTPNID());

        $this->agent = $agent->getData();

        if ($module instanceof MQF_Sales and $module->getRecorderVersion() == MQF_Sales::RECORDER_V2) {
            $id      = $module->getTPNID();
            $rectest = file_get_contents(MQF_Sales::RECORDER_V2_URL . "?callerid={$req_params['anummer']}&client_ip={$req_params['client_ip']}&step=2&agentid={$req_params['selgerkode']}&tpnid={$id}&prosjektkode={$req_params['prosjektkode']}");
        }

        $this->template = $reg->getConfigSettingDefault('gui', 'canvas_template', MQF_MultiQueue::CONFIG_VALUE, 'file:' . MQF_APPLICATION_PATH . "/UTP/templates/UTP.tpl");

        $this->application_title = $this->project;
        $this->export('application_title');
        $this->export('project');
        $this->export('sessionid');
        $this->export('projectmodule');
        $this->export('agent');

        $this->exportalias("module_".strtolower($this->project)."_id", 'module_project_id');
        $this->exportalias("module_".strtolower($this->project)."_html", 'module_project_html');

        $this->time_of_day_string = 'dag';

        $h = date('G');

        if ($h < 11) {
            $this->time_of_day_string = 'morgen';
        } elseif ($h >= 11 and $h <= 16) {
            $this->time_of_day_string = 'dag';
        } elseif ($h > 16 and $h < 18) {
            $this->time_of_day_string = 'ettermiddag';
        } else {
            $this->time_of_day_string = 'kveld';
        }
        $this->export('time_of_day_string');

        $this->setVisible();
    }

    /**
    *
    */
    public function getDialerData()
    {
        $this->dialerdata = $this->dialer->getDataObjectClone();

        return $this->dialerdata;
    }

    /**
    * @desc
    */
    public function setTransferNumberForReason($reason, $number)
    {
        $reason = trim(strtoupper($reason));
        $number = trim($number);

        if ($reason == '' or $number == '' or !is_numeric($number)) throw new Exception("Unable to set transfernumber: STATUS=$reason NUMBER=$number");

        $this->transferlist[$reason] = $number;
    }

    /**
     *
     */
    public function terminateCall($reason, $options = null)
    {
        $ret = new stdClass;
        $ret->SCRIPT = '';
        $ret->STATUS = '';
        $ret->MESSAGE = 'OK';

        $this->end_call = time();

        $reason      = strtoupper(trim($reason));
        $ret->REASON = $reason;

        $status        = '';
        $projectobject = MQF_Module::find($this->projectmodule);

        try {
            // If no TPNID is defined for the project, there must be something wrong?!
            $tpnid        = $projectobject->getTPNID(MQF_Sales::TPNID_FAIL);
            $dialerreason = $projectobject->translateReason($reason);

            $status = $projectobject->save($reason, $options);

        } catch (IllegalSalesException $e) {
            $ret->MESSAGE = $e->getMessage();
            $ret->STATUS = 'ERROR';
            return $ret;
        }

        $ret->STATUS = $status;

        try {
            if ($status === MQF_Sales::IS_VERIFYING) {
                $this->extendCall();
                $ret->SCRIPT = MQF_Tools::utf8($projectobject->getScript());

            } else {

                $this->dialer->saveData();

                $req_params = MQF_Registry::instance()->getMQ()->getRequest();

                if ($projectobject->getRecorderVersion() == MQF_Sales::RECORDER_V2) {
                    $rectest = file_get_contents(MQF_Sales::RECORDER_V2_URL . "?callerid={$req_params['anummer']}&utfall={$dialerreason}&client_ip={$req_params['client_ip']}&step=5&agentid={$req_params['selgerkode']}&tpnid={$tpnid}&prosjektkode={$req_params['prosjektkode']}");
                }

                if (isset($this->transferlist[$dialerreason]) and is_numeric($this->transferlist[$dialerreason])) {
                    MQF_Client::instance()->transferPhone($this->transferlist[$dialerreason]);
                }

                $this->dialer->terminateCall($reason);

                MQF_Client::instance()->closeWindow();
            }

        } catch (MQF_MultiQueueClientException $e) {
            $msg = $e->getMessage();

            // Her kan vi analysere error koden via $e->getCode() hvis den er utfylt, og muligens
            // gjøre noen smarte valg...

            MQF_Log::log($e->getMessage(), MQF_WARN);

            if (strstr($msg, 'ctcInvChannel') !== false and strstr($msg, 'ERROR_INVALID_HANDLE') !== false) {
                MQF_Log::log("Exception from MultiQ, but we continue anyway: ".$msg, MQF_WARN);
            } else {
                throw $e;
            }
        }

        return $ret;
    }

    /**
     *
     */
    public function changeCustomerField($field, $value)
    {
        $field = strtoupper(trim($field));

        $this->updateData($field, $value, MQF_OVERWRITE);
        $this->dialerdata = $this->getDialerData();

        if ($sales = MQF_Module::find($this->projectmodule)) {
            $sales->updateCustomerField($field, $value);
        }
    }

    /**
    *
    */
    public function updateData($field, $value)
    {
        $this->dialer->setDataField($field, $value, MQF_OVERWRITE);
    }

    /**
    *
    */
    public function updateTalkTo($firstname, $lastname, $birth)
    {
        MQF_Module::find($this->projectmodule)->updateTalkTo($firstname, $lastname, $birth);
    }

    /**
    *
    */
    public function setFollowupTime($date, $time)
    {
        if (!$this->allowfollowup) throw new Exception("This project is not allowed to create FollowUp. Check config 'dialer.allowfollowup'");

        MQF_Module::find($this->projectmodule)->setFollowupTime($date, $time);
    }

    /**
    *
    */
    public function setFollowupComment($comment)
    {
        if (!$this->allowfollowup) throw new Exception("This project is not allowed to create FollowUp. Check config 'dialer.allowfollowup'");

        MQF_Module::find($this->projectmodule)->setFollowupComment($comment);
    }


    /**
    * @desc
    */
    public function setCallbackTime($time)
    {
        $this->dialer->setCallbackTime($time);
    }

    /**
    * @desc
    */
    public function extendCall()
    {
        $this->dialer->extendCall();
    }

    /**
    * @desc
    */
    public function shortCall()
    {
        $this->dialer->shortCall();
    }

    /**
    *
    */
    public function setFollowup($bool)
    {
        $this->followup = $bool;
    }

    /**
    *
    */
    public function isFollowup()
    {
        return $this->followup;
    }

    /**
    *
    */
    public function setManualApp($bool)
    {
        $this->manualapp = $bool;
    }

    /**
    *
    */
    public function isManualApp()
    {
        return $this->manualapp;
    }

    /**
    * @desc
    */
    public function spawnManualApplication()
    {
        if (!$this->allowmanualapp) throw new Exception("This project is not allowed to start Manual App. Check config 'dialer.allowmanualapp'");

        $ret = MQF_Registry::instance()->getWebserviceById('TPF29')->getTPNID();

        $tpnid = $ret->TPNID;

        if (!$tpnid) {
            throw new Exception("Did not get a valid TPNID!");
        }

        $this->dialer->cloneDialerDataForId($tpnid, 'M0');

        $url = "http://multiq-01.teleperf.net:8005/ITP5/{$this->project}/index.php?manualapp=1&calldata={$tpnid}&ITPURL";

        $client = new MQF_Client();

        $client->openURL($url);

        MQF_Log::log("Creating new manual application with Id {$tpnid}", MQF_INFO);
    }

    /**
    * @desc
    */
    public function transferPhone($phone)
    {
        $client = new MQF_Client();

        $client->transferPhone($phone);
    }

    // ----------------------------------------
    // Smart template functions
    //

    public function tplfuncUTPField($params, $smarty)
    {
        if (!isset($params['type']) or !isset($params['actiontype']) or !isset($params['method'])) {
            throw new Exception("Type, actiontype or method not specified!");
        }

        $type = $params['type'];
        unset($params['type']);

        $actiontype = $params['actiontype'];
        unset($params['actiontype']);

        $method = $params['method'];
        unset($params['method']);

        $label = 'Button';

        if (isset($params['label'])) {
            $label = $params['label'];
            unset($params['label']);
        }

        $value = '';

        if (isset($params['value'])) {
            $value = $params['value'];
            unset($params['value']);
        }

        $function = '';

        if (isset($params['function'])) {
            $function = $params['function'];
            unset($params['function']);
        }

        $callback = '';

        if (isset($params['callback'])) {
            $callback = $params['callback'];
            unset($params['callback']);
        }

        $confirm = '';

        if (isset($params['confirm'])) {
            $confirm = $params['confirm'];
            unset($params['confirm']);
        }

        if (isset($params['id'])) {
            $id = $params['id'];
            unset($params['id']);
        } else {
            $id = $type.'_'.$actiontype.'_'.MQF_Registry::instance()->getNextUniqueId();
        }

        $domid = $id;

        if (isset($params['disable_domid'])) {
            $domid = $params['disable_domid'];
            unset($params['disable_domid']);
        }

        $class = 'MQF_UTPButton';

        if (isset($params['class'])) {
            $class .= ' '.$params['class'];
            unset($params['class']);
        }

        $extra = ' ';

        foreach ($params as $k => $v) {
            $extra = "{$k}='{$v}' ";
        }


        switch ($type) {
        case 'button':
            $html = "<span class='mqfUTPSpan'><button class='{$class}' id='$id' {$extra}>{$label}</button></span>";
            break;
        case 'text':
            $html = "<span class='mqfUTPSpan'><input type='text' id='$id' {$extra} /><label class='mqfUTPLabel'>{$label}</label></span>";
            break;
        default:
            throw new Exception("Unknown type $type");
        }

        $javascript = '';

        switch ($actiontype) {
        case 'value-none':
            if (strlen(trim($callback))) {
                $javascript = "mqfCallMethod('{$method}!{$domid}', [], 'function $callback')";
            } else {
                $javascript = "mqfCallMethod('{$method}!{$domid}', [])";
            }
            break;
        case 'value-of':
            if (strlen(trim($callback))) {
                $javascript = "mqfCallMethod('{$method}!{$domid}', [\$F('{$value}')], 'function $callback')";
            } else {
                $javascript = "mqfCallMethod('{$method}!{$domid}', [\$F('{$value}')])";
            }
            break;
        case 'value-const':
            if (strlen(trim($callback))) {
                $javascript = "mqfCallMethod('{$method}!{$domid}', ['{$value}'], 'function $callback')";
            } else {
                $javascript = "mqfCallMethod('{$method}!{$domid}', ['{$value}'])";
            }
            break;
        case 'function':
            if (!strlen(trim($function))) throw new Exception("No JavaScript function defined in the 'function' parameter");
            $javascript = $function;
        }

        if (strlen(trim($confirm))) {
            $javascript = "if (confirm('".addslashes($confirm)."')) { {$javascript} } else return false;";
        }

        $html .= "\n<script>Event.observe('{$id}', 'click', function(e) { {$javascript} });</script>\n";

        return $html;
    }
}

?>
