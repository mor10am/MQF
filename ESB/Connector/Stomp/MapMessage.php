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
    public $map;

    /**
    *
    */
    public function __construct($msg, $headers = null)
    {
        if ($msg instanceof MQF_ESB_Connector_Stomp_Frame) {
            $this->_init($msg->command, $msg->headers, $msg->body);
            $this->map = unserialize($msg->body);
        } else {
            $this->_init("SEND", $headers, $msg);

            if ($this->headers == null) {
                $this->headers = array();
            }

            $this->headers['amq-msg-type'] = 'MapMessage';

            $this->map = $msg;

            $this->body = serialize($msg);
        }
    }
}
