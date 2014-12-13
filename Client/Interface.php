<?php

class MQF_Client_Interface
{
    private $client_socket;

    /**
    * Constructor
    */
    public function __construct()
    {
    }

    public function connect()
    {
        $reg = MQF_Registry::instance();

        $target = $reg->getClientIp();
    }

    public function sendPacket($packet)
    {
        // add moca stuff

        $this->_sendCommand($cmd);
    }

    private function _sendCommand($cmd)
    {
        // format and send packet?
        $packet = new MQF_Client_Packet();
    }
}
