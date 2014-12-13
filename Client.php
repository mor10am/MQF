<?php

class MQF_Client
{
    const XML = 1;
    const OBJ = 2;

    const TCP_PORT            = 8090;         ///< TCP port on client side
    const TCP_TIMEOUT_CONNECT = 5; ///< Timeout during tcp connect (seconds)
    const TCP_TIMEOUT_SECS    = 10;    ///< Timeout for tcp stream (seconds) (total timeout is sec+ms)
    const TCP_TIMEOUT_MS      = 0;    ///< Timeout for tcp stream (milli-seconds) (total timeout is sec+ms)

    private static $instance; ///< An instance of this client

    private $client_socket; ///< Client socket

    private $packets = array(); ///< List of active packets

    /**
    * Constructor
    */
    public function __construct()
    {
    }

    /**
    * Create new static instance of Client or return previously created instance.
    *
    */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
    *
    */
    private function _getConnection()
    {
        if (!isset($this->client_socket)) {
            $this->_connectRegularClient();
        }

        return $this->client_socket;
    }

    /**
    *
    */
    public function createPacket($type, $attributes = array())
    {
        $packetid = $this->_getNextPacketId();
        $packet   = new MQF_Client_Packet();

        $packet->create($type, $packetid, $attributes);

        return $packet;
    }

    /**
    *
    */
    public function loadPacket($data)
    {
        $packet = new MQF_Client_Packet();

        $packet->load($data);

        return $packet;
    }

    /**
    * @desc
    */
    public function sendPacket($packet, $return = MQF_Client::OBJ)
    {
        if ($return != MQF_Client::XML and $return != MQF_Client::OBJ) {
            throw new Exception("Unknown return type '$return'");
        }

        $response_raw = $this->_send($packet->getXML());

        $response_packet = $this->loadPacket($response_raw);

        // check response - is it exception?
        $response_type = $response_packet->query('/RESPONSE')->item(0)->getAttribute('TypeOfResponse');

        if ($response_type == 'EXCEPTION') {
            $exmsg = $response_packet->query('//RESPONSE')->item(0)->textContent;

            throw new MQF_MultiQueueClientException($exmsg);
        }

        /// \todo not good for garbage collection
        unset($this->packets[$packetid]);

        switch ($return) {
        case MQF_Client::XML:
            return $response_packet->saveXML();
            break;
        case MQF_Client::OBJ:
            return $response_packet;
            break;
        }
    }

    /**
    * @desc
    */
    public function closeWindow()
    {
        $reg = MQF_Registry::instance();

        $hwnd = $reg->getHWND();

        $cmd  = "960|$hwnd|007|";
        $cmd .= md5($cmd);
        $cmd .= "\n";

        $this->_send($cmd);
    }

    /**
    * @desc
    */
    public function transferPhone($phone)
    {
        $phone = trim($phone);
        if ($phone == '' or !is_numeric($phone)) {
            throw new Exception("Phone is blank or not a number!");
        }

        $cmd  = "908|$phone|";
        $cmd .= md5($cmd);
        $cmd .= "\n";

        $this->_send($cmd);
    }

    /**
    *
    */
    public function openURL($url)
    {
        $reg = MQF_Registry::instance();

        $hwnd = $reg->getHWND();

        $cmd  = "950|$url|";
        $cmd .= md5($cmd);
        $cmd .= "\n";

        $this->_send($cmd);
    }

    /**
    *
    */
    private function _connectRegularClient()
    {
        $reg = MQF_Registry::instance();

        $protocol = 'TCP';
        $ip       = $reg->getClientIp();
        $port     = MQF_Client::TCP_PORT;

        $this->_connect($protocol, $ip, $port);
    }

    /**
    *
    */
    private function _connect($protocol, $ip, $port)
    {
        $errnum = 0;
        $errstr = '';

        switch ($protocol) {

        case 'TCP': $uri = 'tcp://'.$ip;
            break;

        case 'UDP': $uri = 'udp://'.$ip;
            break;

        default: throw new InvalidArgumentException('Unknown protocol');

        }

        $this->client_socket = fsockopen($uri, $port, $errnum, $errstr, MQF_Client::TCP_TIMEOUT_CONNECT);

        if ($errnum !== 0) {
            throw new Exception("Failed to connect to client: $errstr ($errnum)");
        }

        if (!is_resource($this->client_socket)) {
            throw new Exception("Socket is not a resource!");
        }
    }

    /**
    *
    */
    private function _disconnect()
    {
        if (!$this->client_socket) {
            throw new Exception('No socket to close');
        }

        fclose($this->client_socket);

        $this->client_socket = null;
    }

    /**
    *
    */
    private function _getNextPacketId()
    {
        return MQF_Registry::instance()->getNextUniqueId();
    }

    /**
    *
    */
    private function _send($data)
    {
        MQF_Log::log("Sending data to client :\n".$data);

        $socket = $this->_getConnection();

        fwrite($socket, $data);

        if (!stream_set_timeout($socket, MQF_Client::TCP_TIMEOUT_SECS)) {
            throw new Exception('Could not run stream_set_timeout!?');
        }

        while (!feof($socket)) {
            $read .= fgets($socket);
        }

        $socket_metadata = stream_get_meta_data($socket);

        if ($socket_metadata['timed_out']) {
            throw new Exception('Timed out waiting for response. (timeout is '.MQF_Client::TCP_TIMEOUT_SECS.')');
        }

        $this->_disconnect();

        MQF_Log::log("Recived data from client :\n".$read);

        return $read;
    }

    /**
    * @desc
    */
    public static function getCallDurations()
    {
        $client = MQF_Client::instance();

        $packet = $client->createPacket('REQUEST', array('CallObject' => 'MQX'));

        $packet->addField('REQUEST', 'CallFunction', 'getCallDurations');
        $packet->addField('REQUEST', 'CallFunctionField', '');
        $packet->addField('REQUEST', 'CallFunctionValue', '');

        $client->sendPacket($packet);
    }
}

class MQF_MultiQueueClientException extends Exception
{
    public function __construct($message = null, $code = 0)
    {
        parent::__construct($message, $code);
    }
}
