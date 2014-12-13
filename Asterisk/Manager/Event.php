<?php

/**
*
*/
class MQF_Asterisk_Manager_Event
{
    public $EVENT;
    public $MESSAGE;
    public $ACTIONID;
    public $SERVER;

    /**
    *
    */
    public function getEvent()
    {
        return $this->EVENT;
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

        $string = "{$server}(".$a['UNIQUEID'].") Event ".$a['EVENT'].": ";

        switch ($a['EVENT']) {
        case 'Newexten':
            $string .= $a['CONTEXT'].','.$a['EXTENSION'].','.$a['PRIORITY'].' : '.$a['APPLICATION'].'('.$a['APPDATA'].')';
            break;
        case 'Newstate':
            $string .= $a['STATE'];
            break;
        case 'Hangup':
            $string .= $a['CAUSE-TXT'].' ('.$a['CAUSE'].')';
            break;
        case 'Newcallerid':
            $string .= $a['CALLERID'].' <'.$a['CALLERIDNAME'].'> : '.$a['CID-CALLINGPRES'];
            break;
        case 'Newchannel':
            $string .= $a['CHANNEL'].' ('.$a['STATE'].')';
            break;
        default:
        }

        return $string;
    }
}
