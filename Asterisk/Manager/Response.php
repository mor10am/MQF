<?php

/**
*
*/
class MQF_Asterisk_Manager_Response
{
    public $RESPONSE;
    public $MESSAGE;
    public $ACTIONID;
    public $SERVER;

    /**
    *
    */
    public function isSuccess()
    {
        return ($this->RESPONSE == 'Success');
    }

    /**
    *
    */
    public function isError()
    {
        return ($this->RESPONSE == 'Error');
    }

    /**
    *
    */
    public function getResponse()
    {
        return $this->RESPONSE;
    }

    /**
    *
    */
    public function getMessage()
    {
        return $this->MESSAGE;
    }

    /**
    *
    */
    public function hasActionId($id)
    {
        if ($this->ACTIONID != $id) {
            return false;
        }

        return true;
    }

    /**
    *
    */
    public function getServer()
    {
        return $this->SERVER;
    }

    public function __toString()
    {
        $server = '';

        $a = get_object_vars($this);

        if (isset($a['SERVER']) and $a['SERVER']) {
            $server = $a['SERVER'].' ';
        }

        $string = "{$server}Response ".$a['RESPONSE'].": ".$a['MESSAGE'];

        return $string;
    }
}
