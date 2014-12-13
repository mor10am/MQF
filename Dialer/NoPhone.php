<?php

final class MQF_Dialer_NoPhone extends MQF_Dialer
{
    private $tpnid; /// < The Qlist TpnId

    private $field_map_eShare2MQF; /// < eShare to MQF fieldnames

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
    public function __construct($id = false)
    {
        parent::__construct();

        $this->field_map_eShare2MQF = array_flip($this->field_map_MQF2eShare);

        if ($id and is_numeric($id) and !$this->tpnid) {
            $this->tpnid = $id;
        }
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

        $ws = MQF_Registry::instance()->getWebserviceById('TPF29');

        $ret = $ws->getFollowupListRecord($this->tpnid);

        if ($ret->statuscode == 200) {
            if ($ret->listdata) {
                $record = @unserialize($ret->listdata);
            }
        } else {
            throw new Exception($ret->message);
        }

        if (!is_object($record)) {
            throw new Exception("Failed to get object from FollowupList!");
        }

        $fields = get_object_vars($record);

        foreach ($fields as $field => $value) {
            $this->setDataField($field, $value, MQF_THROW_EXCEPTION, true);
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
        if (!$this->tpnid) {
            throw new Exception("Has no TPNID!");
        }

        $this->saveToStorage($this->tpnid);

        return true;
    }

    /**
    *
    */
    public function dial()
    {
    }

    /**
    *
    */
    public function extendCall()
    {
    }

    /**
    *
    */
    public function shortCall()
    {
    }

    public function releaseCall()
    {
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
}
