<?php

final class MQF_Dialer_CTCTPNSYSTEM extends MQF_Dialer
{
    private $tpnid; /// < The Qlist Ukey


    private $field_map_MQF2TPNSYSTEM; /// < MQF to TPNSYSTEM fieldnames
    private $field_map_TPNSYSTEM2MQF; /// < TPNSYSTEM to MQF fieldnames


    /**
    * Constructor
    */
    public function __construct()
    {
        parent::__construct();

        $this->field_map_MQF2TPNSYSTEM = array(
            'COMPANY'    => 'FIRMA',        /// XXX : need to check if this is right
            'CUSTOMERID' => 'KUNDENUMMER',
            'PHONE1'     => 'TELEFON',
            'PHONE2'     => 'NUMBER2',
            'FAX'        => 'FAX',
            'FIRSTNAME'  => 'FORNAVN',
            'LASTNAME'   => 'ETTERNAVN',
            'ADDRESS1'   => 'ADRESSE1',
            'ADDRESS2'   => 'ADRESSE2',
            'ZIP'        => 'PNR',
            'CITY'       => 'STED',
        );

        $this->field_map_TPNSYSTEM2MQF = array_flip($this->field_map_MQF2TPNSYSTEM);
    }

    /**
    *
    */
    protected function realLoadData($tpnid)
    {
        $tpnid = (int) $tpnid; // XXX Could be better

        // check input
        if (!is_int($tpnid)) {
            throw new Exception("Invalid Qlist ukey ($tpnid). Must be integer");
        }

        $this->tpnid = $tpnid;
        // select from tpnsystem


        $ws = MQF_Registry::instance()->getWebserviceById('TPF29');

        $ret = $ws->getQListForId($this->tpnid);

        if ($ret->statuscode == 200) {
            $qlist_obj = $ret->qlist;
        } else {
            throw new Exception($ret->message);
        }

        $qlist_fields = get_object_vars($qlist_obj);

        foreach ($qlist_fields as $field => $value) {
            // Map to the MQF fieldnames
            if ($this->field_map_TPNSYSTEM2MQF[$field]) {
                $mqf_field = $this->field_map_TPNSYSTEM2MQF[$field];
            } else {
                $mqf_field = $field;
            }

            $this->setDataField($mqf_field, $value, MQF_THROW_EXCEPTION, true);
        }
    }

    /**
     * \fn saveData()
     * \returns void
     *
     *
     * Convert data and save back to dialer
     *
     */

    public function realSaveData()
    {
        // noop, sales has the responsibility
        // should check for a sales object though.. what about busy?
    }

    /**
    *
    */
    public function dial()
    {
        $number = $this->getDataField('PHONE1');

        $this->_ctcFunction('MakeCall', false, $number);
    }

    /**
    *
    */
    public function extendCall()
    {
        // noop
    }

    /**
    *
    */
    public function shortCall()
    {
        // noop
    }

    public function releaseCall()
    {
        $this->_ctcFunction('HangupCall', false, false);
    }

    /**
     * \fn terminateCall($termination_code)
     * \param $termination_code Termination code
     * \returns void
     *
     *
     *
     *
     */
    public function realTerminateCall($termination_code)
    {
        $this->releaseCall();
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
        // noop
    }

    /**
     * \fn _ctcFunction($function, $field, $value)
     * \param $function Function to initiate
     * \param $field Field parameter (not calldata field)
     * \param $value Value parameter
     * \returns void
     *
     * \brief Shortcut for small CTC functions
     *
     *
     */
    private function _ctcFunction($function, $field, $value)
    {
        $client = MQF_Client::instance();

        $packet = $client->createPacket('REQUEST', array('CallObject' => 'CTC'));
        $packet->setPreparer($this);

        $packet->addField('REQUEST', 'CallFunction', $function);
        $packet->addField('REQUEST', 'CallFunctionField', $field);
        $packet->addField('REQUEST', 'CallFunctionValue', $value);

        $client->sendPacket($packet);
    }
}
