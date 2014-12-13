<?php

final class MQF_Dialer_EShare extends MQF_Dialer
{
    private $ms15id              = null; ///< eShare's unique call id. Always 40 chars.
    private $incoming_datafields = null; ///< The fields we got from the Delphi app. We'll return exactly the same fields if they are changed
    private $sdftype             = null; ///< The SDF type for this record

    private $field_map_eShare2MQF; /// < eShare to MQF fieldnames

    private $forbidden_eshare_fields = array(  ///< Not allowed to change these fields
            'CAMP'     => true,
            'ID'       => true,
            'LISTNAME' => true,
        );

    private $termination_code_map = array(   /// < MQF to eShare termination codes
                'NO'            => 'C1',
                'YES'           => 'C2',
                'HAS'           => 'C3',
                'DEAD'          => 'C4',
                'BUSY'          => 'NOANSWER',
                'NOANSWER'      => 'NOANSWER',
                'CALLBACK'      => 'CALLBACK1',
                'ANSWERMACHINE' => 'ANSWERMACHINE',
                'FAX'           => 'ANSWERMACHINE',
                'WRONGNUMBER'   => 'C3',
                'FOLLOWUP'      => 'C1',
        );

    private $field_map_MQF2eShare = array(   /// < MQF to eShare fieldnames
                'COMPANY'    => 'FIRMA',
                'CUSTOMERID' => 'ACCOUNT',
                'PHONE1'     => 'NUMBER',
                'PHONE2'     => 'NUMBER2',
                'FAX'        => 'FAX',
                'FIRSTNAME'  => 'FNAVN',
                'LASTNAME'   => 'ENAVN',
                'ADDRESS1'   => 'ADR_1',
                'ADDRESS2'   => 'ADR_2',
                'ZIP'        => 'PNR',
                'CITY'       => 'STED',
        );

    /**
    * Constructor
    */
    public function __construct()
    {
        parent::__construct();

        // flip map
        $this->field_map_eShare2MQF = array_flip($this->field_map_MQF2eShare);
    }

    /**
    *
    */
    protected function realLoadData($xmldata)
    {
        $this->incoming_datafields = array();

        $xmldoc = new DOMDocument();

        $cv = MQF_Registry::instance()->getClientVersion();

        if ($cv >= 2040) {
            $xmldata = str_replace(' ', '+', $xmldata);
            $xmldata = base64_decode($xmldata);
            $xmldata = str_replace('&', '', $xmldata);
        }

        $xmldoc->loadXML($xmldata);

        $xpath = new DOMXPath($xmldoc);

        $calldata_element = $xpath->query('/REQUEST/CALLDATA')->item(0);

        // check the calldata element
        if (!$calldata_element) {
            throw new Exception('Cant find valid calldata element. XML broken?');
        }

        // check that we're on the correct eShare campaign / MQF project
        $eshare_camp = $xpath->query('/REQUEST/CALLDATA/CAMP')->item(0)->nodeValue;
        $mqf_project = MQF_Registry::instance()->getConfigSetting('mqf', 'project');

        if ($eshare_camp != $mqf_project) {
            throw new Exception("Project mismatch! eShare : $eshare_camp vs. MQF : $mqf_project");
        }

        // if we get a sdftype, remember it
        if ($calldata_element->hasAttribute('sdftype')) {
            $this->sdftype = $calldata_element->getAttribute('sdftype');
        }

        // Find all the fields
        $fields = $xpath->query('/REQUEST/CALLDATA/*');

        foreach ($fields as $node) {
            $field = $node->tagName;

            $this->incoming_datafields[] = $field;

            $value = $node->nodeValue;

            // Map to the MQF fieldnames
            if ($this->field_map_eShare2MQF[$field]) {
                $mqf_field = $this->field_map_eShare2MQF[$field];
            } else {
                $mqf_field = $field;
            }

            $this->setDataField($mqf_field, $value, MQF_THROW_EXCEPTION, true);
        }
    }

    /**
     * \fn realSaveData()
     * \returns void
     *
     *
     * Convert data and save back to dialer
     * Do the prefix addition here, since we don't want to change the dialerdata bject
     *
     * \todo Make it so that we only save data wich is modified. (and check if any MOCA data is)
     */

    protected function realSaveData()
    {
        $data_changed_at_all = false;

        $client = MQF_Client::instance();

        $packet = $client->createPacket('REQUEST', array('CallObject' => 'MOCA'));
        $packet->setPreparer($this);

        $calldatafield = $packet->createField('CALLDATA', 'REQUEST');

        $calldatafield->setAttribute('sdftype', $this->sdftype);

        // We trust that the callback time stored in our object is of correct format.
        /// \todo Do we really need a default?
        $callbacktime = $this->getCallbackTimeWithDefault(false);

        if ($callbacktime) {
            $callback_parsed_date = substr($callbacktime, 0, 10);
            $callback_parsed_time = substr($callbacktime, 11, 8); // remember the space between date and time :-)


            $callback_parsed_date = str_replace('/', '.', $callback_parsed_date);

            list($cday, $cmonth, $cyear) = explode('.', $callback_parsed_date);

            $ts = strtotime("{$cmonth}/{$cday}/{$cyear} $callback_parsed_time");

            $callback_parsed_date = date('m/d/y', $ts);
            $callback_parsed_time = date('H:i:00', $ts);

            MQF_Log::log("Adding callback fields to packet ($callback_parsed_date / $callback_parsed_time)");

            $packet->addField($calldatafield, 'CBDATE', $callback_parsed_date);
            $packet->addField($calldatafield, 'CBTIME', $callback_parsed_time);

            $data_changed_at_all = true;
        }

        foreach ($this->incoming_datafields as $eshare_fieldname) {
            // Find the MQF fieldname
            if ($this->field_map_eShare2MQF[$eshare_fieldname]) {
                $mqf_fieldname = $this->field_map_eShare2MQF[$eshare_fieldname];
            } else {
                $mqf_fieldname = $eshare_fieldname;
            }

            // Is the field dirty?
            if (!$this->dirty_fields[$mqf_fieldname]) {
                MQF_Log::log("$eshare_fieldname isn't dirty, not adding");
            }

            // Yes, add to packet
            else {
                // Is this a forbidden field?
                if ($this->forbidden_eshare_fields[$eshare_fieldname]) {
                    throw new Exception("It's forbidden to change $eshare_fieldname!");
                }

                $value = $this->getDataField($mqf_fieldname);

                if ($mqf_fieldname == 'PHONE1' or $mqf_fieldname == 'PHONE2') {
                    if ($this->prefix_mode == DIALER_PREFIX_MODE_HIDE) {
                        $value = $this->addPrefix($value);
                    }
                }

                $packet->addField($calldatafield, $eshare_fieldname, $value);
                $data_changed_at_all = true;
            }
        }

        // Has any data changed?
        if (!$data_changed_at_all) {
            MQF_Log::log("Data isn't changed, not sending packet");
        }

        // Yes, send packet
        else {
            $client->sendPacket($packet);
            MQF_Log::log("Data changed, sending packet");
        }
    }

    /**
    *
    */
    public function dial()
    {
        // noop
    }

    /**
    *
    */
    public function extendCall()
    {
        $this->_mocaFunction('extendCall', '', '');
    }

    /**
    *
    */
    public function shortCall()
    {
        $this->_mocaFunction('shortCall', '', '');
    }

    /**
    *
    */
    public function releaseCall()
    {
        // noop
    }

    /**
     * \fn realTerminateCall($termination)
     * \param $termination Termination
     * \returns void
     *
     * \throws Exception Undefined termination code mapping
     *
     *
     *
     */
    protected function realTerminateCall($termination)
    {
        $termination_code = $this->termination_code_map[$termination];

        if (!$termination_code) {
            throw new Exception('Undefined termination code mapping');
        }

        $this->_mocaFunction('terminateCall', '', $termination_code);
    }

    /**
     * \fn _mocaFunction($function, $field, $value)
     * \param $function Function to initiate
     * \param $field Field parameter (not calldata field)
     * \param $value Value parameter
     * \returns void
     *
     * \brief Shortcut for small MOCA functions
     *
     *
     */
    private function _mocaFunction($function, $field, $value)
    {
        $client = MQF_Client::instance();

        $packet = $client->createPacket('REQUEST', array('CallObject' => 'MOCA'));
        $packet->setPreparer($this);

        $packet->addField('REQUEST', 'CallFunction', $function);
        $packet->addField('REQUEST', 'CallFunctionField', $field);
        $packet->addField('REQUEST', 'CallFunctionValue', $value);

        $client->sendPacket($packet);
    }

    /**
     * \fn preparePacket($packet)
     * \param $packet Packet to be prepared
     * \returns void
     *
     * \throw Exception MS15ID missing
     *
     *
     *
     */
    public function preparePacket($packet)
    {
        if (strlen($this->ms15id) == 40) {
            throw new Exception('MS15ID invalid or missing');
        }
        $packet->addField('mocarequest', 'MS15ID', $this->ms15id);
    }
}
