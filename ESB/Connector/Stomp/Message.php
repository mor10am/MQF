<?php

/**
 * \class MQF_ESB_Connector_Stomp_Message
 *
 * \author Hiram Chirino <hiram@hiramchirino.com>
 * \author Morten Amundsen <mortena@tpn.no>
 *
 */
class MQF_ESB_Connector_Stomp_Message extends MQF_ESB_Connector_Stomp_Frame
{
    /**
    *
    */
    public function __construct($headers = null, $body = null)
    {
        $this->_init('SEND', $headers, $body);
    }
}
