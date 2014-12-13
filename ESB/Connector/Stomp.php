<?php
/**
 *
 * Copyright 2005-2006 The Apache Software Foundation
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once 'PEAR.php';
require_once 'Net/Socket.php';
require_once 'MQF/ESB/Connector/Stomp/Frame.php';

/**
 * \class MQF_Stomp
 *
 * A Stomp Connection
 * The class wraps around HTTP_Request providing a higher-level
 * API for performing multiple HTTP requests
 *
 * \author Hiram Chirino <hiram@hiramchirino.com>
 * \author Morten Amundsen <mortena@tpn.no>
*
 */
class MQF_ESB_Connector_Stomp
{
    const DEFAULT_PORT = 61613;

    private $hosts         = array();                ///< Addresses of servers
    private $currentHost   = -1;
    private $port          = self::DEFAULT_PORT;      ///< Port, 61613 is the default ActiveMQ port
    private $attempts      = 10;
    private $subscriptions = array();

    private $params = array(
        'randomize'     => false,
    );

    private $socket       = null;
    private $connected    = false;

    private $debug   = false;

    private $connect_timeout = 1;
    private $read_timeout    = 5;
    private $persistent      = false;

    /**
    *
    */
    public function __construct($brokerUri, $options = array())
    {
        if (isset($options['connect-timeout'])) {
            $this->connect_timeout = $options['connect-timeout'];
        }

        if (isset($options['persistent'])) {
            $this->persistent = $options['persistent'];
        }

        if (isset($options['read-timeout'])) {
            $this->read_timeout = $options['read-timeout'];
            if ($this->read_timeout < 5) {
                $this->read_timeout = 5;
            }
        }

        if (isset($options['debug'])) {
            $this->debug = $options['debug'];
        }

        $ereg = "^(([a-zA-Z]+)://)+\(*([a-zA-Z0-9\.:/i,-]+)\)*\??([a-zA-Z0-9=]*)$";

        if (eregi($ereg, $brokerUri, $regs)) {
            $scheme = $regs[2];
            $hosts  = $regs[3];
            $params = $regs[4];

            if ($scheme != "failover") {
                $this->_processUrl($brokerUri);
            } else {
                $urls = explode(",", $hosts);

                foreach ($urls as $url) {
                    $this->_processUrl($url);
                }
            }

            if ($params != null) {
                parse_str($params, $this->params);
            }

            $this->_makeConnection();
        } else {
            throw new Exception("Bad broker URI!");
        }
    }

    /**
    *
    */
    public function __destruct()
    {
        try {
            $this->disconnect();
        } catch (Exception $e) {
        }
    }

    /**
    *
    */
    private function _processUrl($url)
    {
        $parsed = parse_url($url);

        if ($parsed) {
            $scheme = $parsed['scheme'];
            $host   = $parsed['host'];
            $port   = $parsed['port'];

            array_push($this->hosts, array($parsed['host'], $parsed['port'], $parsed['scheme']));
        } else {
            throw new Exception("Bad Broker URL $url");
        }
    }

    /**
    *
    */
    private function _makeConnection()
    {
        if (count($this->hosts) == 0) {
            throw new Exception("No broker defined");
        }

        $i = $this->currentHost;
        $att = 0;
        $connected = false;

        while (!$connected && $att++ < $this->attempts) {
            if ($this->params['randomize'] != null and $this->params['randomize'] == 'true') {
                $i = rand(0, count($this->hosts) - 1);
            } else {
                $i = ($i + 1) % count($this->hosts);
            }

            $broker = $this->hosts[$i];

            $host = $broker[0];
            $port = $broker[1];
            $scheme = $broker[2];

            if ($port == null) {
                $port = self::DEFAULT_PORT;
            }

            if ($this->socket instanceof Net_Socket) {
                $this->socket->disconnect();

                $this->socket    = null;
                $this->connected = false;
            }

            $errno  = 0;
            $errstr = '';

            $this->socket = new Net_Socket();

            if (PEAR::isError($this->socket->connect($host, $port, $this->persistent, $this->read_timeout))) {
                MQF_Log::log("Could not connect to $host:$port ({$att}/{$this->attempts}) : {$errno}:{$errstr}", MQF_WARN);
            } else {
                MQF_Log::log("Connected to ActiveMQ $scheme://$host:$port");

                $this->socket->setBlocking(false);
                $this->socket->setTimeout($this->read_timeout, 0);

                $connected = true;

                $this->currentHost = $i;

                break;
            }
        }

        if (!$connected) {
            throw new Exception("Could not connect to a broker");
        }
    }

    /**
    * \brief Connect to Queue Server
    *
    * \param string Username
    * \param string Password
    */
    public function connect($userName = "", $password = "")
    {
        if ($this->connected) {
            return true;
        }

        $frame = new MQF_ESB_Connector_Stomp_Frame("CONNECT", array("login" => $userName, "passcode" => $password));

        $this->_writeFrame($frame);

        $frame = $this->readFrame();

        if (!$frame instanceof MQF_ESB_Connector_Stomp_Frame) {
            $msg = MQF_Log::log("Did not read any frame back after trying to connect", MQF_ERROR);
            throw new Exception($msg);
        }

        switch ($cmd = $frame->getCommand()) {
        case 'CONNECTED':
            MQF_Log::log("Connected user '$userName'");
            $this->connected = true;

            return true;

        case 'ERROR':
            if ($msg = $frame->getHeader('message')) {
                if ($msg == 'Allready connected.') {
                    $this->connected = true;

                    return true;
                } else {
                    throw new Exception($msg);
                }
            } else {
                throw new Exception($frame->body);
            }

            break;
        default:
            throw new Exception("Unable to connect to QueueServer. [{$cmd}]");
        }
    }

    /**
    *
    */
    private function _reconnect()
    {
        $this->_makeConnection();

        $this->connected = false;

        $this->connect();

        foreach ($this->subscriptions as $dest => $properties) {
            $this->subscribe($dest, $properties);
        }
    }

    /**
    * \brief Send data to queue
    */
    public function send($destination, $msg, $properties = null)
    {
        if ($msg instanceof MQF_ESB_Connector_Stomp_Frame) {
            $msg->headers["destination"] = $destination;

            return $this->writeFrame($msg);
        } else {
            $headers = array();

            if (is_array($properties)) {
                foreach ($properties as $name => $value) {
                    $headers[$name] = $value;
                }
            }

            if (is_object($msg) or is_array($msg)) {
                $headers['amq-msg-type'] = 'MapMessage';

                $headers['content-length'] = strlen(serialize($msg));
            }

            $headers["destination"] = $destination;

            if (!isset($headers['content-length'])) {
                $headers['content-length'] = strlen($msg);
            }

            if (isset($headers['amq-msg-type']) and $headers['amq-msg-type'] == 'MapMessage') {
                return $this->_writeFrame(new MQF_ESB_Connector_Stomp_MapMessage($msg, $headers));
            } else {
                return $this->_writeFrame(new MQF_ESB_Connector_Stomp_Frame("SEND", $headers, $msg));
            }
        }
    }

    /**
    * \brief Subscribe to queue
    */
    public function subscribe($destination, $properties = null)
    {
        $headers = array("ack" => "client");

        if (isset($properties)) {
            foreach ($properties as $name => $value) {
                $headers[$name] = $value;
            }
        }

        $headers["destination"] = $destination;

        MQF_Log::log("Subscribing to queue '$destination'");

        $frame = $this->_writeFrame(new MQF_ESB_Connector_Stomp_Frame("SUBSCRIBE", $headers));

        $this->subscriptions[$destination] = $properties;

        return $frame;
    }

    /**
    * \brief unsubscribe from queue
    */
    public function unsubscribe($destination, $properties = null)
    {
        if (!strlen(trim($destination))) {
            MQF_Log::log("No destination given to unsubscribe from", MQX_WARN);

            return false;
        }

        $headers = array();

        if (is_array($properties)) {
            foreach ($properties as $name => $value) {
                $headers[$name] = $value;
            }
        }

        $headers["destination"] = $destination;

        MQF_Log::log("Unsubscribing from queue '$destination'");

        $frame = $this->_writeFrame(new MQF_ESB_Connector_Stomp_Frame("UNSUBSCRIBE", $headers));

        unset($this->subscriptions[$destination]);

        return $frame;
    }

    /**
    * \brief begin transaction
    */
    public function begin($transactionId = null)
    {
        $headers = array();

        if ($transactionId !== null) {
            $headers["transaction"] = $transactionId;
        }

        return $this->_writeFrame(new MQF_ESB_Connector_Stomp_Frame("BEGIN", $headers));
    }

    /**
    * \brief Commit transaction
    */
    public function commit($transactionId = null)
    {
        $headers = array();

        if ($transactionId !== null) {
            $headers["transaction"] = $transactionId;
        }

        return $this->_writeFrame(new MQF_ESB_Connector_Stomp_Frame("COMMIT", $headers));
    }

    /**
    * \brief abort transaction
    */
    public function abort($transactionId = null)
    {
        $headers = array();

        if ($transactionId !== null) {
            $headers["transaction"] = $transactionId;
        }

        return $this->_writeFrame(new MQF_ESB_Connector_Stomp_Frame("ABORT", $headers));
    }

    /**
    * \brief acknowledge message
    */
    public function acknowledge($messageId, $transactionId = null)
    {
        if ($messageId instanceof MQF_ESB_Connector_Stomp_Frame) {
            return $this->_writeFrame(new MQF_ESB_Connector_Stomp_Frame("ACK", $message->headers));
        }

        $headers = array();

        if ($transactionId !== null) {
            $headers["transaction"] = $transactionId;
        }

        $headers["message-id"] = $messageId;

        MQF_Log::log("Acknowledging message '$messageId'");

        return $this->_writeFrame(new MQF_ESB_Connector_Stomp_Frame("ACK", $headers));
    }

    /**
    * \brief disconnect from ActiveMQ server
    */
    public function disconnect($force = false)
    {
        if (!$this->connected) {
            return true;
        }

        if (!$this->socket instanceof Net_Socket) {
            $this->subscriptions = array();
            $this->connected = false;

            return true;
        }

        if (count($this->subscriptions)) {
            $this->unsubscribe(current(array_keys($this->subscriptions)));
        }

        $this->subscriptions = array();

        $this->_writeFrame(new MQF_ESB_Connector_Stomp_Frame("DISCONNECT"));

        $this->socket->disconnect();

        $this->connected = false;

        MQF_Log::log("Disconnected from ActiveMQ");

        return true;
    }

    /**
    * \brief Write data to server
    */
    private function _writeFrame(MQF_ESB_Connector_Stomp_Frame $frame)
    {
        if (!is_resource($this->socket->fp)) {
            return false;
        }

        $data = $frame->command."\n";

        if (isset($frame->headers)) {
            foreach ($frame->headers as $name => $value) {
                $data .= $name.": ".$value."\n";
            }
        }

        $data .= "\n";

        if (isset($frame->body)) {
            $data .= $frame->body;
        }

        $l1 = strlen($data);
        $data .= "\x00\n";
        $l2 = strlen($data);

        $noop = "\x00\n";

        fwrite($this->socket->fp, $noop, strlen($noop));

        $r = fwrite($this->socket->fp, $data, strlen($data));

        if ($r === false || $r == 0) {
            MQF_Log::log("Could not send stomp frame to server. Trying alternative...");
            $this->_reconnect();

            $this->_writeFrame($frame);
        }

        if ($this->debug) {
            MQF_Log::log(print_r($frame, true));
        }
    }

    /**
    * \brief read data from server
    */
    public function readFrame()
    {
        $rc = $this->socket->read(1);

        $metadata = stream_get_meta_data($this->socket->fp);

        if (isset($metadata['timed_out']) and $metadata['timed_out'] === true) {
            return false;
        }

        if ($rc === false) {
            $this->_reconnect();

            return $this->readFrame();
        }

        $data = $rc;
        $prev = '';

        $last_read = microtime(true);

        while (!$this->socket->eof()) {
            $rc = $this->socket->read(1);

            $metadata = stream_get_meta_data($this->socket->fp);
            if (isset($metadata['timed_out']) and $metadata['timed_out'] === true) {
                return false;
            }

            if ($rc === false) {
                $this->_reconnect();

                return $this->readFrame();
            }

            $data .= $rc;

            if (ord($rc) == 10 and ord($prev) == 0) {
                break;
            } elseif (ord($rc) == 0 and ord($prev) == 0) {
                if ($this->read_timeout) {
                    if ((microtime(true) - $last_read) > $this->read_timeout) {
                        return false;
                    } else {
                        continue;
                    }
                }
            }

            $last_read = microtime(true);
            $prev = $rc;
        }

        list($header, $body) = explode("\n\n", $data, 2);
        $header = explode("\n", $header);
        $headers = array();

        $command = null;

        foreach ($header as $v) {
            if (isset($command)) {
                list($name, $value) = explode(':', $v, 2);
                $headers[$name] = $value;
            } else {
                $command = $v;
            }
        }

        $frame = new MQF_ESB_Connector_Stomp_Frame($command, $headers, trim($body));

        if (isset($frame->headers['amq-msg-type']) and $frame->headers['amq-msg-type'] == 'MapMessage') {
            $frame = new MQF_ESB_Connector_Stomp_MapMessage($frame);
        }

        if ($this->debug) {
            MQF_Log::log(print_r($frame, true));
        }

        return $frame;
    }
}
