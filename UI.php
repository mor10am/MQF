<?php

/**
 * The UserInterface is defined in this class
 */
final class MQF_UI
{
    const MQF_FIND_MODULE_ALL = 1;
    const MQF_FIND_MODULE_MODULE = 2;
    const MQF_FIND_MODULE_CANVAS = 3;

    private $scripturl;                         ///< Script URL

    private $canvases = array();    ///< Array of MQF_Canvas

    private $current_canvasid;

    private $_lang = 'en';
    private $_i18n = null;

    protected $_dynamicmodules = array();

    /**
     *
     */
    public function __construct()
    {
        $this->scripturl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER["SCRIPT_NAME"].'?'.$_SERVER["QUERY_STRING"];

        $reg = MQF_Registry::instance();

        $lang = trim($reg->getConfigSettingDefault('gui', 'i18n_lang', MQF_MultiQueue::CONFIG_VALUE, false));

        if ($lang) {
            $this->_i18n = new MQF_UI_I18N($lang);
        }
    }

    /**
    *
    */
    public function init()
    {
        $canvasclass = MQF_Registry::instance()->getConfigSetting('gui', 'canvas');

        if (trim($canvasclass) == '') {
            $canvasclass = 'MQF_UI_Canvas';
        }

        $canvas = $this->addCanvas(new $canvasclass());

        $this->current_canvasid = $canvas->getId();
    }

    /**
     *
     *
     */
    public static function find($id, $mode = self::MQF_FIND_MODULE_ALL, $create_to_parent = false)
    {
        return MQF_Registry::instance()->getUI()->getModuleById($id, $mode, $create_to_parent);
    }

    /**
    * \brief Find module by id
    *
    * \param string id
    * \param int mode
    * \param string Create MQF_Module if not found with this Id as parent
    * \return MQF_Module
    */
    public function getModuleById($id, $mode = self::MQF_FIND_MODULE_ALL, $create_to_parent = false)
    {
        if (is_object($id)) {
            throw new Exception("Unable to find id Object ".get_class($id));
        }

        if (count($this->canvases) == 0) {
            $mod = new $id();
            if ($mod instanceof MQF_UI_Module) {
                $mod->init();
            } else {
                throw new Exception("Id $id is not a MQF_UI_Module!");
            }

            return $mod;
        }

        foreach ($this->canvases as $canvas) {
            if (strtolower($canvas->getId()) == strtolower($id)) {
                return $canvas;
            }

            if ($module = $canvas->getCanvasModuleById($id)) {
                if (($module instanceof MQF_UI_Module and $mode == self::MQF_FIND_MODULE_MODULE) or ($module instanceof MQF_UI_Canvas and $mode == self::MQF_FIND_MODULE_CANVAS) or $mode == self::MQF_FIND_MODULE_ALL) {
                    return $module;
                }
            }
        }

        if ($create_to_parent) {
            foreach ($this->canvases as $canvas) {
                if (strtolower($canvas->getId()) == strtolower($create_to_parent)) {
                    break;
                }
                if ($module = $canvas->getCanvasModuleById($id)) {
                    if (($module instanceof MQF_UI_Module and $mode == self::MQF_FIND_MODULE_MODULE) or ($module instanceof MQF_UI_Canvas and $mode == self::MQF_FIND_MODULE_CANVAS) or $mode == self::MQF_FIND_MODULE_ALL) {
                        $canvas = $module;
                        break;
                    } else {
                        throw new Exception("Module/Canvas '$id' was not found in any canvas, and neither was '$create_to_parent'");
                    }
                }
            }

            return $canvas->addModule(new $id());
        }

        throw new Exception("Module/Canvas '$id' was not found in any canvas");
    }

    /**
    * \brief Display HTML code from canvases on loading or reloading MultiQueue
    *
    *
    * \param    mixed        Result of executed method or null
    * \param    boolean        Set to true if this is the first time we display the application (or on reload)
    * \return                This function displays and dies
    * \throw                Exception
    */
    public function display($result = null, $inital = false, $execid = 0)
    {
        $controller = MQF_Controller_Front::instance();

        if ($result and $controller->isAjax()) {
            header('Content-Type: text/xml');

            print $result;

            return true;
        }

        if (count($this->canvases) == 0) {
            $msg = MQF_Log::log("MultiQueue has no Canvas to draw on!", MQF_ERROR);
            throw new Exception($msg);
        }

        $html  = "<!-- MultiQ Framework v{$this->app_version} {$this->app_date} (c) Teleperformance Norge AS 2006-2008 -->\n";
        $html .= "<!-- created by Morten Amundsen, Ken-Roger Andersen, Magnus Espeland, Gunnar Graver, Eivind Falkenstein -->\n";
        $html .= "<!-- All rights reserved -->\n\n";

        $refresh_root = false;

        $canvas = $this->canvases[$this->current_canvasid];

        if (!$canvas instanceof MQF_UI_Canvas) {
            throw new Exception("Current canvas '{$this->current_canvasid}' does not point to valid canvas object");
        }

        if ($canvas->isVisible()) {
            $html .= $canvas->getHTML();
        } else {
            MQF_Log::log("Canvas ".$canvas->getId()." is hidden", MQF_DEBUG);
        }

        if ($inital or !$controller->isAjax()) {
            print $html;

            return true;
        } else {
            $reg = MQF_Registry::instance();

            header('Content-Type: text/xml');
            print MQF_Executor::getCallbackXML(new MQF_ReturnValue($reg->getValue('scripturl'), 'mqfRedirectBrowser'));

            return true;
        }
    }

    /**
    *
    */
    public static function addNewCanvas($canvas)
    {
        return MQF_Registry::instance()->getUI()->addCanvas($canvas);
    }

    /**
    * \brief Add a new MQF_Canvas to MultiQueue
    */
    public function addCanvas($canvas)
    {
        if (!$canvas instanceof MQF_UI_Canvas) {
            $canvas = new $canvas();
        }

        $canvasid = $canvas->getId();

        if (isset($this->canvases[$canvasid])) {
            throw new Exception("Canvas $canvasid already exists!");
        }

        $reg = MQF_Registry::instance();
        $mq  = $reg->getMQ();

        $this->canvases[$canvasid] = $canvas;

        $canvas->initialize();

        return $canvas;
    }

    /**
    *
    */
    public function setCurrentCanvas($canvas)
    {
        try {
            $cobj = MQF_UI::find($canvas);
        } catch (Exception $e) {
            $cobj = MQF_UI::addNewCanvas($canvas);
        }

        $this->current_canvasid = $cobj->getId();
        $cobj->refreshModuleDisplay();

        if (!isset($this->canvases[$this->current_canvasid]) or !$this->canvases[$this->current_canvasid] instanceof MQF_UI_Canvas) {
            $this->canvases[$this->current_canvasid] = $cobj;
        }

        return $cobj;
    }

    /**
    *
    */
    public function getCurrentCanvas()
    {
        return $this->canvases[$this->current_canvasid];
    }

    /**
    * \brief Get XML from one or all canvases
    */
    public function getCanvasXML($options = array(), $execid = 0)
    {
        $xml = '<?xml version="1.0" encoding="utf-8" ?>';

        $xml .= "\n<ROOTNODE>\n";
        $xml .= "<ajax-response execid='{$execid}'>\n";

        $canvas = $this->canvases[$this->current_canvasid];

        if (isset($options['retvalxml'])) {
            $xml .= $options['retvalxml'];
        }

        if ($canvas->isVisible() or $canvas->hasReturnValue() or $canvas->hasException()) {
            $xml .= $canvas->getXML();
        }

        $xml .= "</ajax-response>\n";
        $xml .= "</ROOTNODE>";

        return $xml;
    }

    /**
    * \brief Set language
    */
    public function setLanguage($lang)
    {
        $this->_i18n->setLanguage($lang);
    }

    /**
    * \brief Get language
    */
    public function getLanguage()
    {
        if ($this->_i18n) {
            return $this->_i18n->getLanguage();
        }
    }

    /**
    *
    */
    public function getI18N()
    {
        return $this->_i18n;
    }

    /**
     *
     */
    public function getDynamicModuleList()
    {
        return $this->_dynamicmodules;
    }

    /**
     *
     */
    public function addDynamicModule($canvas, $module, $element = 'span')
    {
        if (isset($this->_dynamicmodules[$canvas])) {
            foreach ($this->_dynamicmodules[$canvas] as $obj) {
                if (strtolower($module) == strtolower($obj->module)) {
                    return false;
                }
            }
        } else {
            $this->_dynamicmodules[$canvas] = array();
        }

        $obj = new stdclass();
        $obj->module = $module;
        $obj->element = $element;

        $this->_dynamicmodules[$canvas][] = $obj;

        return true;
    }
}
