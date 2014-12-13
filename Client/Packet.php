<?php


class MQF_Client_Packet
{
    protected $xmldoc; ///< The XML DOM object
    protected $xpath; ///< Lazily loaded XPath object


    protected $type; ///< Packet type (eg mocaresponse)

    private $packetid; ///< The id of this packet
    private $preparer; ///< Object that will prepare the packet

    /**
    * Constructor
    */
    public function __construct()
    {
        // create XML DOM object
        $this->xmldoc = new DOMDocument('1.0', 'utf-8');
    }

    /**
     *
     * \fn create()
     * \return void
     *
     * \brief Create a standard ClientProtocol packet (xmlns: http://dev.tpn.no/XML/MQX/ClientProtocol)
     *
     */

    public function create($type, $packetid, $attributes)
    {
        MQF_Log::log('create packet of type '.$type);

        if (!is_array($attributes)) {
            throw new InvalidArgumentException('$attributes must be an array');
        }

        $this->type = $type;

        // set packet id
        $this->packetid = $packetid;

        // add root element
        $root_element = $this->xmldoc->createElement($type);

        $root_element->setAttribute('ActionId', $this->packetid);

        foreach ($attributes as $name => $value) {
            $root_element->setAttribute($name, $value);
        }

        $this->xmldoc->appendChild($root_element);
    }

    /**
     *
     * \fn load($xmlstring)
     * \param $xmlstring A string containing the xml
     * \return void
     *
     *
     *
     */

    public function load($xmlstring)
    {
        // Load XML data
        $this->xmldoc->loadXML($xmlstring);

        // XXX : get packet type from somewhere
        //$this->type = $type;
    }

    /**
     *
     * \fn setPreparer($preparer)
     * \param $preparer Parent object or name of parent element
     * \return void
     *
     * \throw new InvalidArgumentException Preparer must be an object
     * \throw new InvalidArgumentException Preparer has no preparePacket function
     *
     *
     */

    public function setPreparer($preparer)
    {
        if (!is_object($preparer)) {
            throw new InvalidArgumentException('Preparer must be an object');
        }
        if (!method_exists($preparer, 'preparePacket')) {
            throw new InvalidArgumentException('Preparer has no preparePacket function');
        }

        $this->preparer = $preparer;
    }

    /**
     *
     * \fn hasPreparer()
     * \return boolean
     *
     */

    public function hasPreparer()
    {
        return isset($this->preparer);
    }

    /**
     *
     * \fn prepare()
     * \return void
     *
     * \throw Exception Preparer not set
     *
     * \brief Run the preparePacket($packet) on the preparer object
     */

    public function prepare()
    {
        if (!isset($this->preparer)) {
            throw new Exception('Preparer not set');
        }

        $this->preparer->preparePacket($this);
    }

    /**
     *
     * \fn addField($parent, $name, $value)
     * \param $parent Parent object or name of parent element
     * \param $name Name of new field
     * \param $value Value of new field
     * \return void
     *
     * \throw new InvalidArgumentException Unknown parent
     *
     *
     * $real_parent is an DOMElement
     *
     */

    public function addField($parent, $name, $value)
    {
        MQF_Log::log("Adding field |$name| as child of a ".gettype($parent)." with value |$value|");

        // find real parent
        if (is_object($parent)) {
            $real_parent = $parent;
        } elseif (is_string($parent)) {
            $real_parent = $this->xmldoc->getElementsByTagName($parent)->item(0);
        } else {
            throw new InvalidArgumentException('Unknown parent. (Did you forget to create it?)');
        }

        $new_field = $this->xmldoc->createElement($name, $value);
        $real_parent->appendChild($new_field);
    }

    /**
     *
     * \fn getField($name)
     * \param $name Name of field
     * \return void
     *
     *
     */

    public function getField($name)
    {
        return $this->xmldoc->getElementsByTagName($name);
    }

    /**
     *
     * \fn createField($parent, $name)
     * \param $parent Parent object or name of parent element
     * \param $name Name of new field
     * \return field (DOMElement)
     *
     * \throw new InvalidArgumentException Unknown parent
     *
     * $real_parent is an DOMElement
     *
     */

    public function createField($name, $parent = false)
    {
        if ($parent) {
            if (is_object($parent)) {
                $real_parent = $parent;
            } elseif (is_string($parent)) {
                $real_parent = $this->xmldoc->getElementsByTagName($parent)->item(0);
            } else {
                throw new InvalidArgumentException('Unknown parent. (Did you forget to create it?)');
            }
        } else {
            $real_parent = $this->xmldoc;
        }

        $new_field = $this->xmldoc->createElement($name);
        $real_parent->appendChild($new_field);

        return $new_field;
    }

    public function getXML()
    {
        return $this->xmldoc->saveXML();
    }

    public function query($query)
    {
        if (!$this->xpath) {
            $this->xpath = new DOMXPath($this->xmldoc);
        }

        return $this->xpath->query($query);
    }
}
