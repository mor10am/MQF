<?php

require_once 'MQF/Asterisk/Server.php';

/**
* @author Morten Amundsen <mortena@tpn.no>
*/
class MQF_Asterisk_Cluster extends ArrayObject
{
    private $_strategy;
    private $_currindex = 0;

    const ROUND_ROBIN_ALL    = 1;      ///< Try to queue all contacts to one server before advancing
    const ROUND_ROBIN_SINGLE = 2;      ///< Try to queue one call on each server in list
    const ADVANCE_ON_FAILURE = 3;      ///< Change server only if failure

    /**
    *
    */
    public function __construct($strategy = self::ROUND_ROBIN_ALL)
    {
        parent::__construct(array());

        $this->_strategy = $strategy;
    }

    /**
    * Add Asterisk Server
    */
    public function addAsterisk($obj)
    {
        if (!$obj instanceof MQF_Asterisk_Server) {
            throw new Exception("Not Asterisk Server");
        }

        MQF_Log::log("Added Asterisk ".(string) $obj);

        $this->append($obj);
    }

    /**
    *
    */
    public function call($contact)
    {
        if ($this->count() == 0) {
            MQF_Log::log("Unable to call - No Asterisk Servers");

            return false;
        }

        if ($this->offsetExists(0)) {
            $asterisk = $this->offsetGet(0);

            $asterisk->originate($contact);
        }
    }
}
