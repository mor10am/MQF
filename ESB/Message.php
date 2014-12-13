<?php

/**
*
*/
final class MQF_ESB_Message
{
    private $fields   = array();
    private $commands = array('NOOP', 'ADD', 'MOVE', 'ACK');
    private $command  = 'NOOP';

    /**
    *
    */
    public function __construct($cmd = 'NOOP', $fields = array())
    {
        $this->setCommand($cmd);
        if (!is_array($fields)) {
            throw new Exception("Fields is not given as array!");
        }

        foreach ($fields as $f => $v) {
            $this->setField($f, $v);
        }
    }

    /**
    *
    */
    public function setCommand($cmd)
    {
        $cmd = strtoupper(trim($cmd));
        if (!in_array($cmd, $this->commands)) {
            throw new Exception("Unknown command '$cmd'");
        }
        $this->command = $cmd;
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
    public function hasField($field)
    {
        $field = strtoupper(trim($field));

        return isset($this->fields[$field]);
    }

    /**
    *
    */
    public function getField($field)
    {
        $field = strtoupper(trim($field));
        if (!isset($this->fields[$field])) {
            throw new Exception("Unknown field '$field'");
        }

        return $this->fields[$field];
    }

    /**
    *
    */
    public function setField($field, $data)
    {
        $field = strtoupper(trim($field));
        $this->fields[$field] = $data;
    }
}
