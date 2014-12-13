<?php

/**
*
*/
final class MQF_Agent
{
    const   MODE_ITP = 3;
    const   MODE_UTP = 2;

    private $agentid   = false;
    private $name      = '...';
    private $rrid      = false;
    private $dept      = false;
    private $extension = false;
    private $ip        = false;
    private $waittime  = 0;

    private $mode;

    /**
    *
    */
    public function __construct($input)
    {
        if (strlen($input['selgerkode']) != 5) {
            throw new Exception("Parameter 'selgerkode' should be 5 chars! Starting with either 2 or 3.");
        }

        $agent_type = substr($input['selgerkode'], 0, 1);

        MQF_Log::log("Agent ".$input['selgerkode']." is a type $agent_type agent");

        switch ($agent_type) {

        case 2:
            $mode = self::MODE_UTP;
            break;

        case 3:
            $mode = self::MODE_ITP;
            break;

        default:
            throw new Exception("Unknown agent type ".$agent_type);
        }

        if ($mode != self::MODE_ITP and $mode != self::MODE_UTP) {
            throw new Exception("Wrong mode for agent in application (ITP or UTP)");
        }

        $this->mode = $mode;

        if (isset($input['selgerkode'])) {
            $this->agentid = substr($input['selgerkode'], -4);
        }

        if (is_numeric($this->agent) and strlen($this->agent) != 4) {
            throw new Exception("AgentId '".$input['selgerkode']."' is illegal");
        }

        if (isset($input['rrid'])) {
            $this->rrid = substr($input['rrid'], -2);
        }

        if (is_numeric($this->rrid) and strlen($this->rrid) != 2) {
            throw new Exception("RRId '".$input['rrid']."' is illegal");
        }

        if (isset($input['avdeling2'])) {
            $this->dept = trim($input['avdeling2']);
        }

        if (strlen($this->dept) == 0) {
            throw new Exception("Department '".$input['avdeling2']."' is illegal");
        }

        if (isset($input['client_ip'])) {
            $this->ip = trim($input['client_ip']);
        } else {
            throw new Exception("No input was give for Ip address.");
        }

        if (isset($input['agent_name']) and strlen(trim($input['agent_name'])) > 0) {
            $this->name = trim($input['agent_name']);
        } else {
            $this->name = '...';
        }

        if (isset($input['wait_time']) and is_numeric($input['wait_time'])) {
            $this->waittime = $input['wait_time'];

            if ($this->waittime > 3600) {
                $this->waittime = 0;
            }
        } else {
            $this->waittime = 0;
        }

        /*
        $v = new Zend_Validate_Hostname(Zend_Validate_Hostname::ALLOW_IP);

        if (!$v->isValid($this->ip)) {
            $msgs = implode(' ', $v->getMessages());
            throw new Exception($msgs);
        }
        */

        if (isset($input['ext'])) {
            $this->extension = substr(trim($input['extension']), -4);
        }

        if (is_numeric($this->extension) and strlen($this->extension) != 4) {
            throw new Exception("Extension '".$input['ext']."' is illegal");
        }

        MQF_Log::log("Created agent {$this->agentid}/{$this->name} with client address '{$this->ip}'");
    }

    /**
    * @desc
    */
    public function getWaitTime()
    {
        return $this->waittime;
    }

    /**
    * @desc
    */
    public function getMode()
    {
        return $this->mode;
    }

    /**
    *
    */
    public function getIp()
    {
        return $this->ip;
    }

    /**
    *
    */
    public function getAgentId()
    {
        return $this->agentid;
    }

    /**
    *
    */
    public function getRRId()
    {
        return $this->rrid;
    }

    /**
    *
    */
    public function getDept()
    {
        return $this->dept;
    }

    /**
    * @desc
    */
    public function getName()
    {
        return $this->name;
    }

    /**
    * @desc
    */
    public function getData()
    {
        $data = array();

        $data['ip']        = $this->ip;
        $data['name']      = $this->name;
        $data['extension'] = $this->extension;
        $data['agentid']   = $this->agentid;
        $data['rrid']      = $this->rrid;
        $data['mode']      = $this->mode;

        return $data;
    }
}
