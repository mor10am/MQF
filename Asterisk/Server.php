<?php

require_once 'MQF/Asterisk/Manager.php';

/**
*
*
*/
final class MQF_Asterisk_Server
{
    private $_astman   = null;
    private $_server   = '';
    private $_port     = 5038;
    private $_username = '';
    private $_password = '';
    private $_tech     = 'zap';
    private $_maxch    = 30;

    private $_techmax = array(
                                'zap'  => 30,
                                'iax2' => 100,
                                'sip'  => 100,
    );

    /**
    *
    */
    public function __construct($parameters = array())
    {
        $this->_address  = $parameters['server'];
        $this->_port     = $parameters['port'];
        $this->_username = $parameters['username'];
        $this->_password = $parameters['password'];

        if (isset($parameters['tech'])) {
            if (!in_array(strtolower($parameters['tech']), array_keys($this->_techmax))) {
                throw new Exception("Unknown technology '{$parameters['tech']}'!");
            }

            $this->_tech  = $parameters['tech'];
            $this->_maxch = $this->_techmax[$this->_tech];
        }

        $this->_astman = new MQF_Asterisk_Manager($this->_address, $this->_port);

        if (!$this->_astman->login($this->_username, $this->_password)) {
            throw new Exception("Unable to login to Asterisk at {$this->_address}:{$this->_port}. Check username/password.");
        }
    }

    /**
    *
    */
    public function __toString()
    {
        return  get_class($this).'('.$this->_address.':'.$this->_port.')';
    }

    /**
    *
    */
    public function originate($number, $options = array())
    {
        if ($number instanceof Medusa2_ContactData) {
            $phone    = $contact->getPhone();
            $actionid = $contact->getActionId();
        } else {
            $phone = $number;

            $actionid = false;

            if (isset($options['actionid'])) {
                $actionid = $options['actionid'];
            }
        }

        MQF_Log::log("Originate call to number $phone with actionId $actionid on server {$this->_address}");

        $this->_astman->action('Originate', array(
            'Channel'   => "Zap/r1/".$phone,
            'Context'   => 'mqf-originate',
            'Exten'     => 's',
            'Priority'  => 1,
        ));
    }
}
