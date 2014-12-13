<?php

/**
*
*/
class MQF_Asterisk_Exception extends Exception
{
    public function __construct($message = '', $code = false)
    {
        parent::__construct($message, $code);
    }
}
