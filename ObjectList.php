<?php

/**
* \class MQF_ObjectList
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: ObjectList.php 806 2007-11-28 14:03:50Z mortena $
*
*/
class MQF_ObjectList extends ArrayObject
{
    private $accept_class; ///< only accept classes of this type

    /**
    * \brief Constructor
    */
    public function __construct($acceptclass)
    {
        parent::__construct();

        if (trim($acceptclass) == '') {
            throw new Exception("Need the name of a class");
        }

        $this->accept_class = $acceptclass;
    }

    /**
    * \brief Add object to list
    */
    public function addInstance($obj, $error_on_duplicate = false)
    {
        /*
        if (!$obj instanceof $this->accept_class) {
            throw new InvalidArgumentException("Only '{$this->accept_class}' object can be added!");
        }
        */

        $id = $obj->getId();

        if ($this->offsetExists($id)) {
            if ($error_on_duplicate) {
                throw new Exception("CampaignSale ".$id." already exists");
            }

            return $this->getInstance($id);
        } else {
            $this->offsetSet($id, $obj);
            MQF_Log::log("Added instance with Id $id [{$this->accept_class}]");
        }

        return $obj;
    }

    /**
    * \brief Remove object from list
    */
    public function remInstance($id_or_obj)
    {
        if (is_object($id_or_obj) /* and is_a($id_or_obj, $this->accept_class) */) {
            $id = $id_or_obj->getId();
        } else {
            $id = $id_or_obj;
        }

        if ($this->offsetExists($id)) {
            $this->offsetUnset($id);
            MQF_Log::log("Removed instance with Id $id [{$this->accept_class}]");
        }

        return true;
    }

    /**
    * \brief Get object instance by Id
    */
    public function getInstance($id)
    {
        if ($this->offsetExists($id)) {
            return $this->offsetGet($id);
        } else {
            throw new OutOfBoundsException("Instance with Id $id does not exist!");
        }
    }

    /**
    * \brief Check if object instance exists
    *
    */
    public function hasInstance($id_or_obj)
    {
        if (is_object($id_or_obj)) {
            $id = $id_or_obj->getId();
        } else {
            $id = $id_or_obj;
        }

        return $this->offsetExists($id);
    }
}
