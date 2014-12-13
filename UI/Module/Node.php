<?php

class MQF_Module_Node extends MQF_Module
{
    private $_node;

    protected $nodedata = array();

    /**
    *
    */
    public function __construct($node, $options = array())
    {
        if (!$node instanceof MQF_Node) {
            throw new Exception("This module must be attached to a Node!");
        }

        $this->_node = $node;

        $options['tplplugin'] = $this;

        parent::__construct($options);
    }

    /**
    *
    */
    public function init()
    {
        $template_file = MQF_APPLICATION_PATH.'/templates/'.$this->_node->TYPE.'.tpl';

        $this->setTemplateName($template_file);

        $this->export('nodedata');
    }

    /**
    *
    */
    public function beforeRefresh()
    {
        $this->nodedata = print_r($this->_node->getData(), true);
    }
}
