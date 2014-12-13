<?php

/**
 * \class MQF_UI_Display
 * Display and language translator. Uses TemplateEngine to render HTML.
 *
 * \author Morten Amundsen <mortena@tpn.no>
 * \author Ken-Roger Andersen <kenny@tpn.no>
 * \author Magnus Espeland <magg@tpn.no>
 * \author Gunnar Graver <gunnar.graver@teleperformance.no>
 * \remark Copyright 2006-2007 Teleperformance Norge AS
 * \version $Id: Display.php 996 2008-02-27 11:00:39Z mortena $
 *
 */
class MQF_UI_Display
{
    private $tplengine  = null;         ///< TemplateEngine object
    private $variables  = array();      ///< Template variables
    private $plugin     = false;        ///< TemplateEngine plugin object

    /**
    * \brief Constructor
    */
    public function __construct($options = array())
    {
        if (isset($options['tplplugin'])) {
            $this->plugin = $options['tplplugin'];
        }
    }

    /**
    * \brief Fetch HTML for template with associatet variables for module
    *
    * \param string template
    * \return string HTML markup
    */
    public function fetchHTML($template)
    {
        $this->tplengine = new MQF_UI_TemplateEngine($this->plugin, array('mode' => MQF_TEMPLATE_ENGINE_MODE_CURLY));

        foreach ($this->variables as $var => $value) {
            $this->tplengine->assign($var, $value);
        }

        if ($template != '') {
            $html = $this->tplengine->fetch($template);
        }

        return $html;
    }

    /**
    * \brief Clear all assigns in template
    */
    public function clean()
    {
        $this->variables = array();
        $this->tplengine->clearAllAssigns();
    }

    /**
    * \brief Get all template variables
    */
    public function getTemplateVars()
    {
        return $this->variables;
    }

    /**
    * \brief Assign variable/value to template
    */
    public function assign($var, $value)
    {
        $this->variables[$var] = $value;
    }

    /**
    * \brief Clear one variable in the template
    */
    public function clearAssign($var)
    {
        unset($this->variables[$var]);
        $this->tplengine->clearAssign($var);
    }
}
