<?php

abstract class MQF_Dialer
{
    const CTCTPNSYSTEM = 1;
    const ESHARE       = 2;
    const CTCFOLLOWUP  = 3;
    const NOPHONE      = 4;

    private static $dialer; ///< Dialer object, when instanciated

    protected $callid; ///< Dialers id
    protected $data; ///< Dialer data
    protected $dataobject; ///< Object version of dialerdata
    private $dirty = false; ///< True, if unsaved changes
    protected $dirty_fields; /// < Array with names of dirty fields as KEY

    protected $callback_time;        ///< Callback time, in dd.mm.yyyy hh:mm:ss format
    protected $callback_time_updated; ///< CB time changed?


    protected $prefix_mode = false; ///< How do we handle prefixes?
    protected $prefix      = null; ///< Prefix used in the phonenumber

    protected $using_debug_data = false;

    /**
    * Constructor
    */
    public function __construct()
    {
        // What do we do with prefixes?
        $this->prefix_mode = MQF_Registry::instance()->getConfigSettingDefault('dialer', 'prefix_mode', MQF_MultiQueue::CONFIG_VALUE, false);

        $this->dirty_fields = array();
    }

    /**
    *
    */
    public static function getDialer()
    {
        // Just return object, if it exist
        if (self::$dialer) {
            return self::$dialer;
        }

        $reg        = MQF_Registry::instance();
        $mq         = $reg->getMQ();
        $req_params = $reg->getRequestParams();

        $using_debug_data = false;

        $type = false;

        if ($mq->hasValue('calldata')) {
            $calldata = $mq->getValue('calldata');
        } elseif ($req_params['calldata']) {
            $calldata = stripslashes($req_params['calldata']);
            $mq->setValue('calldata', $calldata);
        } else {
            $calldata         = file_get_contents('calldata2.xml'); /// \todo REMOVE DEBUG CODE
            $using_debug_data = true;
        }

        if (isset($req_params['multidialer'])) {
            $type = $req_params['multidialer'];
        }

        if (isset($req_params['followup']) and isset($req_params['manualapp'])) {
            throw new Exception("Unable to start application with both 'Followup' and 'Manualapp' defined!");
        }

        // override multidialer, if it's a followup!
        if ($req_params['followup'] == 1) {
            if (($futype = self::determineDialerType($calldata)) !== false) {
                $type = $futype;
            } else {
                $type = MQF_Dialer::CTCTPNSYSTEM;

                MQF_Log::log("Unable to find dialertype, so we hardcode CTCTPNSYSTEM", MQF_WARN);
            }
        } elseif ($req_params['manualapp'] == 1) {
            $type = MQF_Dialer::NOPHONE;
        }

        switch ($type) {
        case MQF_Dialer::ESHARE:
            MQF_Log::log("Create an eShare dialer");

            self::$dialer = new MQF_Dialer_EShare();

            if (!strlen(trim($calldata))) {
                $calldata = stripslashes($req_params['calldata']);
            }

            self::$dialer->loadData($calldata);

            break;

        case MQF_Dialer::CTCTPNSYSTEM:
            MQF_Log::log("Create a CTC TPNSYSTEM dialer");

            self::$dialer = new MQF_Dialer_CTCTPNSYSTEM();

            $tpnid = $calldata;

            self::$dialer->loadData($tpnid);

            break;

        case MQF_Dialer::CTCFOLLOWUP:
            MQF_Log::log("Create a CTC Followup dialer");

            self::$dialer = new MQF_Dialer_CTCFollowup();

            $tpnid = $calldata;

            self::$dialer->loadData($tpnid);

            break;
        case MQF_Dialer::NOPHONE:
            MQF_Log::log("Create a NoPhone dialer. Used for Manual Application");

            self::$dialer = new MQF_Dialer_NoPhone();

            $tpnid = $calldata;

            self::$dialer->loadData($tpnid);
            break;

        default:
            throw new Exception('Unknown dialer type: '.$type.". Check 'multidialer' parameter in request.");
        }

        self::$dialer->setDebug($using_debug_data);

        return self::$dialer;
    }

    /**
    * @desc SKAL SLETTES 15. SEPTEMBER
    */
    public function determineDialerType($id)
    {
        $dialertype = false;

        $reg = MQF_Registry::instance();

        $request = $reg->getRequestParams();

        if (isset($request['selgerkode'])) {
            $agent = $request['selgerkode'];

            $ret = $reg->getWebserviceById('TPF29')->getPersonalCallbackForId($agent, $id);

            if ($ret->statuscode == 200 and is_object($ret->callback)) {
                $impl_date = strtotime('8/22/2007 21:00:00');
                $cb_date   = strtotime($ret->callback->DATO);

                if ($cb_date < $impl_date) {
                    $dialertype = MQF_Dialer::CTCTPNSYSTEM;
                } else {
                    $dialertype = MQF_Dialer::CTCFOLLOWUP;
                }
            } else {
                MQF_Log::log("DDP - ".$ret->message, MQF_ERROR);
            }

            MQF_Log::log("DDP - Callback done on {$ret->callback->DATO}. Should use CTC dialertype = ".$dialertype);
        } else {
            MQF_Log::log("DDP - No agentId in request", MQF_WARN);
        }

        return $dialertype;
    }

    /**
    * @desc
    */
    public function setDebug($onoff)
    {
        $this->using_debug_data = $onoff;
    }

    // #####################################
    // #########  DIALER CTRL  #############
    // #####################################


    /**
     * \fn dial()
     * \returns void
     * \brief Init dialing when in preview
     */

    abstract public function dial();

    /**
     * \fn extendCall()
     * \returns void
     * \brief Tell dialer that this call will be longer (it should not start dialing yet)
     */

    abstract public function extendCall();

    /**
     * \fn shortCall()
     * \returns void
     * \breif Tell dialer that this call will soon be done (it should dial some more)
     */

    abstract public function shortCall();

    /**
     * \fn releaseCall()
     * \returns void
     * \breif Hangup the call, without terminating (going into 'typing')
     */

    abstract public function releaseCall();

    /**
     * \fn terminateCall($termination_code)
     * \param $termination Termination
     * \returns void
     * \throws InvalidArgumentException Unknown call termination
     * \throws Exception Callback time not changed on new callback
     * \brief Force a valid termination, and the call the real implementation
     */

    public function terminateCall($termination)
    {
        MQF_Log::log('terminateCall: '.$termination);

        /// \todo This needs to be changed I think. This list, and the one in eShare, are duplicates..
        switch ($termination) {

        case 'YES':
        case 'NO':
        case 'HAS':
        case 'DEAD':
        case 'NOANSWER':
        case 'CALLBACK':
        case 'BUSY':
        case 'ANSWERMACHINE':
        case 'FAX':
        case 'WRONGNUMBER':
        case 'FOLLOWUP':
            break;

        default:
            throw new InvalidArgumentException('Unknown call termination');
            break;

        }

        // validate that the callback time has been updated
        if ($termination == 'CALLBACK') {
            if (!$this->callback_time_updated) {
                throw new Exception('Callback time not changed on new callback');
            }
        }

        // Pass on to the real implementation
        $this->realTerminateCall($termination);
    }

    /**
     * \fn _terminateCall($termination)
     * \param $termination Termination
     * \returns void
     * \brief The dialer specific implementation of call termination
     */

    abstract protected function realTerminateCall($termination);

    // #####################################
    // #########  MISC CALL SPEC  ##########
    // #####################################


    /**
     * \fn setCallbackTime($time)
     * \param $time Callback time, in dd.mm.yyyy hh:mm:ss format
     * \returns void
     *
     */

    public function setCallbackTime($time_string)
    {
        if ($this->callback_time == $time_string) {
            return;
        }

        $parsed_unixtime = strtotime($time_string);

        if ($time_string != strftime('%d.%m.%Y %T', $parsed_unixtime)) {
            throw new Exception('Invalid timeformat: '.$time_string);
        }

        $this->callback_time_updated = true;
        $this->callback_time         = $time_string;

        $this->dirty = true;
        // Don't add to dirty $dirty_fields, as CB is handled separatly
        MQF_Log::log('dialerdata is now dirty. New callbacktime: '.$time_string);
    }

    /**
     * \fn getCallbackTime($default)
     * \param $default Default time for callback, if none set
     * \returns Callback time, in dd.mm.yyyy hh:mm:ss format
     *
     *
     *
     */

    public function getCallbackTimeWithDefault($default)
    {
        if ($this->callback_time) {
            return $this->callback_time;
        } else {
            return $default;
        }
    }

    // #####################################
    // #########  DIALER DATA  #############
    // #####################################

    /**
     * \fn loadData($rawdata_or_dialerid)
     * \param $data_or_id Raw data, or id of dialer data
     * \returns void
     *
     */

    public function loadData($rawdata_or_dialerid)
    {
        $this->realLoadData($rawdata_or_dialerid);

        // Do prefix stuff
        if ($this->prefix_mode == DIALER_PREFIX_MODE_HIDE) {
            $number = $this->getDataField('PHONE1');
            if (strlen($number) != 8) {
                $number = $this->removePrefix($number);
                $this->setDataField('PHONE1', $number, MQF_OVERWRITE, true);
            }

            if ($this->hasDataField('PHONE2')) {
                $number = $this->getDataField('PHONE2');
                if (strlen($number) != 8) {
                    $number = $this->removePrefix($number);
                    $this->setDataField('PHONE2', $number, MQF_OVERWRITE, true);
                }
            }
        }
    }

    /**
     * \fn realLoadData($rawdata_or_dialerid)
     * \param $data_or_id Raw data, or id of dialer data
     * \returns void
     *
     */

    abstract protected function realLoadData($rawdata_or_dialerid);

    /**
     * \fn saveData()
     * \returns void
     *
     * Saves data, if dirty
     * Do the prefix addition in the concrete class when building the packet,
     * since we don't want to changes the dialerdata in this object
     *
     *
     */
    public function saveData($force = false)
    {
        if (!$this->dirty and !$force) {
            MQF_Log::log("Data isn't dirty, so not saving");

            return;
        }

        $this->realSaveData();
        $this->dirty = false;
        $this->dirty_fields = array(); // reset array
        MQF_Log::log('Dialerdata saved, and is now clean');
    }

    /**
     * \fn realSaveData()
     * \returns void
     *
     */

    abstract protected function realSaveData();

    /**
     * \fn getData()
     * \returns array
     *
     */

    protected function getData()
    {
        return $this->data;
    }

    /**
     * \fn getData()
     * \returns object
     *
     */

    protected function getDataObject()
    {
        if (!isset($this->dataobject)) {
            $this->dataobject = new StdClass();

            foreach ($this->data as $k => $v) {
                $this->dataobject->$k = $v;
            }
        }

        return $this->dataobject;
    }

    /**
     * \fn getDataObjectClone()
     * \returns array
     *
     */

    public function getDataObjectClone()
    {
        return clone $this->getDataObject();
    }

    /**
     * \fn hasDataField($fieldname)
     * \param $fieldname Name of field we want to check
     * \returns boolean
     *
     */

    public function hasDataField($fieldname)
    {
        return isset($this->data[$fieldname]);
    }

    /**
     * \fn setDataField($fieldname, $fieldvalue, $overwritemode)
     * \param $fieldname Name of field we want to set
     * \param $fieldvalue Value of field we want to set
     * \param $overwritemode What to do if the field is allready set?
     * \param $importing If we're importing, we do not set data to be dirty
     * \returns boolean Field set or not.
     *
     * \throws InvalidArgumentException Invalid overwritemode mode
     *
     * Valid overwritemodes:
     *
     * MQF_THROW_EXCEPTION
     * MQF_DONT_OVERWRITE
     * MQF_OVERWRITE
     *
     *
     */

    public function setDataField($fieldname, $fieldvalue, $overwritemode, $importing = false)
    {
        // Check for correct modes:
        if ($overwritemode != MQF_THROW_EXCEPTION
            and $overwritemode != MQF_DONT_OVERWRITE
            and $overwritemode != MQF_OVERWRITE) {
            throw new InvalidArgumentException('Invalid overwritemode mode');
        }

        // Check if field is set, and if so throw exeption
        if (isset($this->data[$fieldname]) and $overwritemode == MQF_THROW_EXCEPTION) {
            throw new Exception('Field '.$fieldname.'allready set');
        }

        // Check if field is set, and if so return false
        if (isset($this->data[$fieldname]) and $overwritemode == MQF_DONT_OVERWRITE) {
            return false;
        }

        // The field isn't set, or we're in overwrite mode

        if (!isset($this->data[$fieldname]) or $this->data[$fieldname] != $fieldvalue) {
            $this->data[$fieldname] = $fieldvalue;

            if ($importing) {
                MQF_Log::log("We're importing, so dialerdata isn't dirty anyway: $fieldname = $fieldvalue");
            } else {
                $this->dirty = true;
                $this->dirty_fields[$fieldname] = true;
                MQF_Log::log('dialerdata is now dirty:'.$fieldname.' = '.$fieldvalue);
            }

            // Reset (unset) the dataobject, so that it will be regenerated
            unset($this->dataobject);
        } else {
            MQF_Log::log("Someone wanted to set $fieldname to the value it ALLREADY had ($fieldvalue), but I didn't bother");
        }
    }

    /**
     * \fn getDataField($fieldname)
     * \param $fieldname Name of field we want
     * \returns mixed value of field
     *
     * \throws Exception Unknown data field
     */

    public function getDataField($fieldname)
    {
        return $this->_internalGetDataField($fieldname, MQF_THROW_EXCEPTION, null);
    }

    /**
     * \fn public getDataFieldDefault($fieldname, $default)
     * \param $fieldname Name of field we want
     * \param $default Value to return if field not set
     * \returns mixed value of field or default value
     *
     */

    public function getDataFieldDefault($fieldname, $default)
    {
        return $this->_internalGetDataField($fieldname, MQF_DONT_THROW_EXCEPTION, $default);
    }

    /**
     * \fn private _internalGetDataField($fieldname, $throwexception, $default)
     * \param $fieldname Name of field we want
     * \param $throwexception Shall we throw an exception if field isn't set?
     * \param $default Value to return if field not set
     * \returns mixed value of field or default value
     *
     * \throws Exception Unknown data field
     * \throws InvalidArgumentException Invalid $throwexception value
     *
     * \brief Internal function doing the actual getField() and  getFieldDefault() work
     */

    private function _internalGetDataField($fieldname, $throwexception, $default)
    {
        if (isset($this->data[$fieldname])) {
            return $this->data[$fieldname];
        } elseif ($throwexception == MQF_DONT_THROW_EXCEPTION) {
            return $default;
        } elseif ($throwexception == MQF_THROW_EXCEPTION) {
            throw new Exception('Unknown data field '.$fieldname);
        } else {
            throw new InvalidArgumentException('Invalid $throwexception value');
        }
    }

    /**
     * \fn public removePrefix($number)
     * \param $number_with_prefix The number we want to remove the prefix from
     * \returns string Number without prefix
     *
     */

    public function removePrefix($number_with_prefix)
    {
        if (!$number_with_prefix) {
            return;
        } /// XXX

        if ($this->prefix_mode != DIALER_PREFIX_MODE_HIDE) {
            throw new Exception("Trying to remove prefix, but we aren't in DIALER_PREFIX_MODE_HIDE mode");
        }

        if (strlen($number_with_prefix) != 15) {
            throw new Exception("Don't know how to handle prefixes on phonenumber that doesn't contain 15 digits");
        }

        $tmp_prefix = substr($number_with_prefix, 0, 7);

        if (substr($tmp_prefix, 0, 3) != 999) {
            throw new Exception($tmp_prefix."Doesn't look like a valid prefix");
        }

        if ($this->prefix and $this->prefix != $tmp_prefix) {
            throw new Exception("I allready have a prefix, and this one isn't the same. I've got ".$this->prefix." and the new one is ".$tmp_prefix);
        } else {
            $this->prefix = $tmp_prefix;
        }

        $number_without_prefix = substr($number_with_prefix, 7);

        return $number_without_prefix;
    }

    /**
     * \fn public addPrefix($number_without_prefix)
     * \param $number_without_prefix The number we want to add the prefix to
     * \returns string Number with prefix
     *
     */
    public function addPrefix($number_without_prefix)
    {
        return $this->prefix.$number_without_prefix;
    }

    /**
    * @desc
    */
    public function cloneDialerDataForId($id, $listname = false)
    {
        $obj = $this->getDataObjectClone();

        foreach ($obj as $field => $value) {
            switch ($field) {
            case 'ID':
            case 'TPNID':
                if ($value == $id) {
                    throw new Exception("The Id of the new blank object can not be the same as the original!");
                }
                $value = $id;
                break;
            case 'LISTNAME':
                if ($listname) {
                    $value = $listname;
                    MQF_Log::log("Changing listname from $value to $listname");
                }
                break;
            }

            $obj->$field = $value;
        }

        $this->saveToStorage($id, $obj);
    }

    /**
    * @desc
    */
    protected function saveToStorage($id, $obj = null)
    {
        if (!$obj or !is_object($obj)) {
            $obj = $this->getDataObjectClone();
        }

        $string = serialize($obj);

        $ws = MQF_Registry::instance()->getWebserviceById('TPF29');

        $ret = $ws->saveFollowupListRecord($id, $string);

        if ($ret->statuscode != 200) {
            throw new Exception("Unable to save Callback List: ".$ret->message);
        }

        return true;
    }
}
