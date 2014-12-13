<?php

/**
 * \class MQF_ESB_Connector_Stomp_Frame
 * A MQF_ESB_Connector_Stomp_Frame are the messages that are sent and received on a MQF_Stomp.
 *
 * \author Hiram Chirino <hiram@hiramchirino.com>
 * \author Morten Amundsen <mortena@tpn.no>
 * \version $Id: Stomp.php 789 2007-11-23 18:08:35Z mortena $
 */
class MQF_ESB_Connector_Stomp_Frame
{
    public $command;    ///< Command name
    public $headers;    ///< Array of headers
    public $body;       ///< Body of frame

    /**
    * \brief Create new frame
    *
    * \param string Command
    * \param array Headers
    * \param string Body
    */
    public function __construct($command = null, $headers = null, $body = null)
    {
        $this->_init($command, $headers, $body);
    }

    /**
    *
    */
    protected function _init($command = null, $headers = null, $body = null)
    {
        $this->command = $command;

        if ($headers != null) {
            $this->headers = $headers;
        }

        $this->body = $body;
    }

    /**
    *
    */
    public function __toString()
    {
        $str = "<Object";

        if (isset($this->headers['persistent']) and $this->headers['persistent'] == 'true') {
            $str .= ' PERSISTENT';
        }

        $str .= " MQF_ESB_Connector_Stomp_Frame cmd={$this->command}/";

        if (isset($this->headers['correlation-id'])) {
            $str .= 'corr-id='.$this->headers['correlation-id'].'/';
        }

        $str .= $this->getMessageId();

        if (isset($this->headers['MESSAGE']) and strlen($this->headers['MESSAGE'])) {
            $str .= ' : '.$this->headers['MESSAGE'];
        }

        $str .= ' Size='.strlen($this->body);

        $str .= '>';

        return $str;
    }

    /**
    *
    */
    public function getMessageId()
    {
        if (is_array($this->headers) and isset($this->headers['message-id'])) {
            return $this->headers['message-id'];
        }

        return false;
    }

    /**
    *
    */
    public function getCommand()
    {
        return $this->command;
    }

    /**
    *
    */
    public function getBody()
    {
        return $this->body;
    }

    /**
    *
    */
    public function getHeader($name)
    {
        if (isset($this->headers[$name])) {
            return $this->headers[$name];
        } else {
            return false;
        }
    }
}
