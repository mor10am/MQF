<?php

/**
* \class MQF_UI_ReturnValue
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: ReturnValue.php 949 2008-01-18 20:21:07Z mortena $
*
*/
class MQF_UI_ReturnValue
{
    public $value;              ///< Value to return
    public $callback_hook;      ///< Callback in browser
    public $client_ipaddress;
    public $server_ipaddress;
    public $server_port;

    /**
    * \brief
    */
    public function __construct($value, $callback_hook = '')
    {
        $this->value = $value;

        if ($callback_hook != '') {
            $this->callback_hook = $callback_hook;
        }

        $this->client_ipaddress = MQF_Registry::instance()->getClientIp();
        $this->server_ipaddress = $_SERVER["SERVER_ADDR"];
        $this->server_port      = $_SERVER["SERVER_PORT"];
    }

    /**
    * \brief Has callback?
    */
    public function hasCallback()
    {
        if ($this->callback_hook == '') {
            return false;
        }

        return true;
    }

    /**
    * \brief Get callback name
    */
    public function getCallback()
    {
        if (!$this->hasCallback()) {
            return false;
        }

        return $this->callback_hook;
    }

    /**
    * \brief Set callback name
    */
    public function setCallback($cb)
    {
        $this->callback_hook = $cb;
    }

    /**
    * \brief Get the value to return
    */
    public function getReturnValue()
    {
        return $this->value;
    }
}
