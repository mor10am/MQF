<?php

/**
 * \class MQF_UI_Canvas
 *
 * \author Morten Amundsen <mortena@tpn.no>
 * \author Ken-Roger Andersen <kenny@tpn.no>
 * \author Magnus Espeland <magg@tpn.no>
 * \author Gunnar Graver <gunnar.graver@teleperformance.no>
 * \remark Copyright 2006-2007 Teleperformance Norge AS
 * \version $Id: Canvas.php 1021 2008-03-14 20:36:35Z mortena $
 *
 */

class MQF_UI_Canvas extends MQF_UI_Module
{
    protected $modules  = array();      ///< Array of MQF_Module
    private $replaceid  = false;        ///< Name of DOM Id to replace with content from MQF_Canvas
    protected $template = false;        ///< Default name of canvas template

    protected $hwnd;                    ///< Window ID in MQF_Client

    /**
    * \brief Constructor
    */
    public function __construct($options = array())
    {
        $options['tplplugin'] = $this;

        parent::__construct($options);

        // Find and set HWND
        $this->hwnd = MQF_Registry::instance()->getHWND();
        $this->export('hwnd');
    }

    /**
    *
    */
    protected function init()
    {
    }

    /**
    * \brief Check if this canvas has module children
    *
    * \return boolean Has modules
    */
    public function hasModules()
    {
        if (count($this->modules)) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * \brief Get list of module children
    *
    * \return Array Modules
    */
    public function getModuleList()
    {
        if (!$this->hasModules()) {
            return array();
        }

        return $this->modules;
    }

    /**
    * \brief Add a module to this canvas
    *
    * \param MQF_Module Module
    * \return boolean Return true on success
    * \throw Exception If object added is not instance of MQF_Module
    */
    public function addModule($module, $dynamic = false)
    {
        if ($module instanceof MQF_UI_Module) {
            if ($dynamic) {
                $module->setDynamic();
            }

            $moduleid = strtolower($module->getId());

            if (isset($this->modules[$moduleid])) {
                throw new Exception("Module $moduleid already exists!");
            }

            $this->modules[$moduleid] = $module;

            MQF_Log::log("Added module '$moduleid' with visibilty=".$module->isVisible().' Dynamic='.$module->isDynamic());

            $module->initialize();

            MQF_Log::log("Initialized module '$moduleid'");
        } else {
            throw new Exception("Object is not instance of MQF_Module! Is of type ".gettype($module));
        }

        return $module;
    }

    /**
    * \brief Get HTML for this canvas
    *
    * \return string HTML markup
    */
    public function getHTML()
    {
        $reg = MQF_Registry::instance();

        $id = $this->id;

        if (!$reg->getMQ()->isModuleAuth($this->getId())) {
            return false;
        }

        if ($this->disp instanceof MQF_UI_Display) {
            $this->beforeRefresh();

            $this->assignModulesHTML();

            $html  = "\n<!-- Canvas: {$id} START -->\n";
            $html .= $this->disp->fetchHTML($this->template);
            $html .= "\n<!-- Canvas: {$id} STOP -->\n";

            $this->disp->clean();

            return $html;
        } else {
            MQF_Log::log("The canvas {$id} does not have a valid display. Check if parent constructor was called or the 'no-display' option is 'true'!", MQF_WARN);
        }
    }

    /**
    * \brief Assign vars to template assiciated with this canvas
    *
    * \param MQF_Display
    */
    public function assignModulesHTML()
    {
        $dynamic = "\n<div id='".strtolower($this->getId())."_dynamic'>\n";

        if (count($this->modules)) {
            foreach ($this->modules as $module) {
                if ($module->isDynamic()) {
                    $dynamic .= "\n<div id='".strtolower($module->getId())."'>".$module->getHTML()."</div>\n";
                } else {
                    $id = strtolower($module->getId());

                    $this->assignTplVar($id.'_id', $id);

                    if ($module->isVisible()) {
                        $this->assignTplVar($id.'_html', $module->getHTML());
                    }
                }
            }
        }

        $dynamic .= "\n</div>\n";

        $this->assignTplVar('dynamic_modules', $dynamic);

        $this->setModuleAssigns();
    }

    /**
    * \brief Search for a module with a given Id among this canvas and its children
    *
    * \param string Id
    * \return MQF_Module|MQF_Canvas|MQF_Book|false
    */
    public function getCanvasModuleById($id)
    {
        if (strtolower($id) == strtolower($this->getId())) {
            return $this;
        }

        if (count($this->modules) == 0) {
            return false;
        }

        foreach ($this->modules as $mod) {
            if ($mod instanceof MQF_UI_Canvas or $mod instanceof MQF_UI_Canvas_Book) {
                if (strtolower($id) == strtolower($mod->getId())) {
                    return $mod;
                }
                if ($module = $mod->getCanvasModuleById($id)) {
                    return $module;
                }
            } elseif (strtolower($mod->getId()) == strtolower($id)) {
                return $mod;
            }
        }

        return false;
    }

    /**
    * \brief Get XML for modules.
    *
    * \return string XML data
    */
    public function getXML()
    {
        $xml = $this->getReturnValue(self::RETVAL_AS_XML);

        if (count($this->modules) == 0) {
            return $xml;
        }

        foreach ($this->modules as $mod) {
            if ($mod->isVisible()) {
                $xml .= $mod->getXML();
            }
        }

        return $xml;
    }

    /**
    * \brief Check if canvas is replaceable
    *
    * \return bool
    */
    public function isReplaceable()
    {
        if (strtolower($this->getTargetId()) != strtolower($this->getId())) {
            return true;
        }

        return false;
    }

    /**
    * \brief Get DOM Id for canvas to replace
    *
    * \return mixed Returns Id to put content into, or false
    */
    public function getReplaceId()
    {
        if (!$this->isReplaceable()) {
            return false;
        }

        return $this->targetid;
    }

    /**
    * \brief Set this Canvas visible
    */
    public function showCanvas()
    {
        $this->setVisible();

        return true;
    }

    /**
    * \brief Set this canvas refresh pending. Also set refresh for all module children
    *
    */
    public function refreshModuleDisplay()
    {
        $this->refresh = true;

        if (count($this->modules) == 0) {
            return true;
        }

        foreach ($this->modules as $mod) {
            if ($mod->isVisible()) {
                $mod->refreshModuleDisplay();
            }
        }

        return true;
    }

    /**
     *
     */
    public function insertDynamicModule($moduleid, $element = 'span')
    {
        $module = MQF_UI::find($moduleid, MQF_UI::MQF_FIND_MODULE_MODULE, $this->getId());

        $ui = MQF_Registry::instance()->getUI();

        if ($ui->addDynamicModule($this->getId(), $moduleid, $element)) {
            $module->refreshModuleDisplay();

            $html = "<{$element} id='".strtolower($module->getId())."' class='mqfdynamicmodule' >";
            $html .= $module->getHTML();
            $html .= "</{$element}>";

            return $html;
        }
    }

    /**
     *
     */
    public function tplfuncMQFModulePlaceholder($param, &$smarty)
    {
        $ui = MQF_Registry::instance()->getUI();

        $list = $ui->getDynamicModuleList();

        $id = $this->getId();

        $html = "<div id='mqfmoduleplaceholder'>\n";

        if (isset($list[$id])) {
            foreach ($list[$id] as $obj) {
                $mod = MQF_UI::find($obj->module);
                $html .= "<{$obj->element} id='".strtolower($mod->getId())."' class='mqfdynamicmodule'>\n";
                $html .= $mod->getHTML();
                $html."</{$obj->element}>\n";
            }
        }

        $html .= "</div>\n";

        return $html;
    }
}
