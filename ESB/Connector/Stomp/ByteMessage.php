<?php

/**
 * \class MQF_ESB_Connector_Stomp_MapMessage
 *
 * \author Hiram Chirino <hiram@hiramchirino.com>
 * \author Morten Amundsen <mortena@tpn.no>
 *
 */
class MQF_ESB_Connector_Stomp_MapMessage extends MQF_ESB_Connector_Stomp_Message
{
    /**
    *
    */
    public function __construct($headers = null, $body = null)
    {
        $this->_init("SEND", $headers, $body);

        if ($this->headers == null) {
            $this->headers = array();
        }

        $this->headers['content-length'] = count($body);
    }
}
