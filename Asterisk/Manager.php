<?php

require_once 'Net/Socket.php';
require_once 'MQF/Asterisk/Manager/Response.php';
require_once 'MQF/Asterisk/Manager/Event.php';
require_once 'MQF/Asterisk/Exception.php';

/**
*
*/
class MQF_Asterisk_Manager
{
    protected $socket = null;

    const FILTER_NONE       = 0;
    const FILTER_EVENT      = 1;
    const FILTER_RESPONSE   = 2;
    const FILTER_ACTIONID   = 4;

    /**
    *
    */
    public function __construct($host, $port = 5038)
    {
        $this->socket = new Net_Socket();

        if (PEAR::isError($ret = $this->socket->connect($host, $port))) {
            throw new Exception($ret->getMessage()." [{$host}:{$port}]");
        }
    }

    /**
    *
    */
    public function __destruct()
    {
        if ($this->socket) {
            $this->action('Logout');
            $this->socket->disconnect();
        }
    }

    /**
    *
    */
    public function login($username, $password)
    {
        $result = $this->action('Login', array('Username' => $username, 'Secret' => $password));

        if ($result->isSuccess() and $result->getMessage() == 'Authentication accepted') {
            return true;
        } else {
            return false;
        }
    }

    /**
    *
    */
    public function ping()
    {
        try {
            $result = $this->action('Ping', array('Parameters' => 'None'));

            if ($result->getResponse() == 'Pong') {
                return true;
            }
        } catch (Exception $e) {
        }

        return false;
    }

    /**
    *
    */
    public function action($command, $params = array(), $until = false)
    {
        MQF_Log::log("Action: $command");

        $packet = "Action: {$command}\r\n";

        $has_action_id = false;

        if (count($params)) {
            foreach ($params as $p => $val) {
                if (strtoupper($p) == 'ACTIONID') {
                    $has_action_id = true;
                    $id            = $val;
                }

                if (strlen($p)) {
                    $packet .= "{$p}: {$val}\r\n";
                }
            }
        }

        if (!$has_action_id) {
            $id = $this->_genActionId();

            $packet .= "ActionId: {$id}\r\n";
        }

        $packet .= "\r\n";

        $result = $this->_sendAction($packet);

        $events = array();

        if ($result->isSuccess()) {
            if ($until) {
                $events = array();

                while (1) {
                    if (!$event = $this->read(self::FILTER_EVENT, $id)) {
                        continue;
                    }
                    if ($event->getEvent() == $until) {
                        break;
                    }
                    $events[] = clone $event;
                }

                return $events;
            } else {
                return $result;
            }
        } else {
            return $result;
        }
    }

    /**
    *
    */
    protected function _genActionId()
    {
        $id = microtime(true).'.'.mt_rand(0, 1000000);

        return $id;
    }

    /**
    *
    */
    protected function _sendAction($packet)
    {
        if (PEAR::isError($ret = $this->socket->write($packet))) {
            throw new Exception($ret->getMessage());
        }

        return $this->read(self::FILTER_RESPONSE);
    }

    /**
    *
    */
    public function read($filter = self::FILTER_NONE, $actionid = false)
    {
        $waitforend = false;
        $endseen    = false;
        $object     = false;

        while (true) {
            if (PEAR::isError($tmp = $this->socket->readLine())) {
                throw new Exception($tmp->getMessage());
            }

            if ($object) {
                if (trim($tmp) == '' and (!$waitforend or ($waitforend and $endseen))) {
                    break;
                }

                if ($waitforend and !$endseen and trim($tmp) == '--END COMMAND--') {
                    $endseen = true;
                    continue;
                } elseif ($waitforend and !$endseen) {
                    $response->MESSAGE .= $tmp."\n";
                }
            }

            $matches = array();

            preg_match("/^(.+?)\:(.*)$/", $tmp, $matches);

            if (count($matches) == 3 and !$waitforend) {
                $field = strtoupper(trim($matches[1]));
                $value = trim($matches[2]);

                if (!$object and $field == 'RESPONSE' and ($filter == self::FILTER_NONE or $filter == self::FILTER_RESPONSE)) {
                    $object = new MQF_Asterisk_Manager_Response();
                } elseif (!$object and $field == 'EVENT' and ($filter == self::FILTER_NONE or $filter == self::FILTER_EVENT)) {
                    $object = new MQF_Asterisk_Manager_Event();
                }

                if ($object) {
                    $object->$field = $value;

                    if ($field == 'RESPONSE' and $value == 'Follows' and ($filter == self::FILTER_NONE or $filter == self::FILTER_RESPONSE)) {
                        $waitforend = true;
                    }
                }
            }
        }

        if ($actionid and ($filter == self::FILTER_NONE or $filter == self::FILTER_EVENT)) {
            if ($actionid != $object->ACTIONID) {
                unset($object);

                return false;
            }
        }

        MQF_Log::log((string) $object);

        return $object;
    }
}
