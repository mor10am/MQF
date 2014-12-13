<?php

/**
* \class MQF_UI_Module
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: Module.php 1075 2008-07-23 11:03:11Z mortena $
*
* \todo The caching implementation is not very good and a bit questionable. Should re-think it!
*/

abstract class MQF_UI_Module
{
    const RETVAL_AS_OBJECT  = 1;
    const RETVAL_AS_XML     = 2;

    private $init_done = false;         ///< Specifiy if initializing of module is done

    protected $properties = array();    ///< Module properties
    protected $message;                 ///< Next message to return to browser
    protected $errormessage;            ///< Next errormessage to return to browser
    protected $refresh = false;         ///< Refresh display
    protected $template;                ///< Template of this module
    protected $id;                      ///< Id
    protected $dynamic  = false;
    protected $dynaseed = 0;

    protected $targetid;                ///< DOM ID target
    protected $temp_template;           ///< Temporary template name to use

    protected $returnvalobj = null;     ///< MQF_ReturnValue or null

    protected $visible = true;          ///< MQF_Module visible?
    protected $exportlist  = array();
    protected $exportaliases = array();

    protected $disp = null;             ///< MQF_Display

    private $exception = null;          ///< Exception

    protected $usehtmlcache = false;          ///< Use HTML cache
    protected $cachettl     = 60;

    protected $templatecachevars = array();     ///< These vars are part of the template key

    /**
    * \brief Setup module
    *
    */
    public function __construct($options = array())
    {
        if (!defined('_MQF_CONSTANTS_LOADED')) {
            include 'Constants.php5';
        }

        $reg = MQF_Registry::instance();

        $ref = new ReflectionObject($this);

        if (isset($options['dynamic']) and $options['dynamic'] === true) {
            $this->id = $ref->getName();

            $this->setDynamic();
        } else {
            $this->id = $ref->getName();
        }

        $this->export('id');
        $this->export('dynaseed');

        $class_name = $ref->getName();

        if (!$this->template) {
            if (substr($class_name, 0, 7) == 'Module_' or substr($class_name, 0, 7) == 'Canvas_') {
                $template_name = substr($class_name, 7);
                $template_path = str_replace('_', DIRECTORY_SEPARATOR, $class_name);
            } else {
                $template_name = $class_name;
                $template_path = $class_name;
            }

            if (!defined('MQF_APPLICATION_PATH')) {
                define('MQF_APPLICATION_PATH', getcwd().DIRECTORY_SEPARATOR.'..');
            }

            $template_file = MQF_APPLICATION_PATH.DIRECTORY_SEPARATOR.$template_path.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.$template_name.'.tpl';

            $this->setTemplateName($template_file);
        }

        $execmode = $reg->getExecMode();

        MQF_Log::log("Exec mode = $execmode");

        if ($execmode == 'http') {
            $this->setVisible();

            $this->usehtmlcache = false;

            if (isset($options['htmlcache'])) {
                $this->usehtmlcache = $options['htmlcache'];
            }

            if (!isset($options['no-display']) or $options['no-display'] === false) {
                $dispoptions = array();

                $dispoptions['tplplugin'] = false;

                if (isset($options['tplplugin'])) {
                    $dispoptions['tplplugin'] = $options['tplplugin'];
                }

                $dispoptions['language'] = $reg->getUI()->getLanguage();

                $this->disp = new MQF_UI_Display($dispoptions);

                if (!$this->disp instanceof MQF_UI_Display) {
                    throw new Exception("The display of the module {$this->id} was not instantiated");
                }
            }
        }
    }

    /**
    *
    */
    public function initialize()
    {
        if (!$this->init_done) {
            $this->init();
            $this->init_done = true;
        }
    }

    /**
    *
    */
    public function setDynamic()
    {
        if ($this->isDynamic()) {
            return true;
        }

        $this->dynamic = true;

        $this->dynaseed = time().mt_rand(0, 1000000);

        $this->id = $this->id."_".$this->dynaseed;
    }

    /**
    *
    */
    public function isDynamic()
    {
        return $this->dynamic;
    }

    /**
    *
    */
    public function setHTMLCacheTTL($sec)
    {
        $this->cachettl = $sec;
    }

    /**
    *
    */
    abstract protected function init();

    /**
    * \brief Call a modules method
    *
    * \param string Module
    * \param string Method
    * \param array parameters
    * \return mixed
    *
    */
    public function callMethod($module, $method, $arglist = array())
    {
        $class = get_class($this);
        if ($class == $module and strtoupper($method) == 'CALLMETHOD') {
            throw new Exception("Circular calling is forbidden!");
        }
        if (!is_array($arglist)) {
            $arglist = array($arglist);
        }
        $mod = MQF_UI::find($module);

        return call_user_func_array(array($mod, $method), $arglist);
    }

    /**
    * \brief Get the template filename for this module
    *
    * \return string Template
    */
    public function getTemplateName()
    {
        return $this->template;
    }

    /**
    * \brief Set the template filename for this module
    *
    * \param string Template
    */
    public function setTemplateName($tpl)
    {
        $dir = trim(dirname($tpl));

        if (!strlen($dir) or $dir == '.') {
            $ref = new ReflectionObject($this);

            $class_name = $ref->getName();

            if (substr($class_name, 0, 7) == 'Module_' or substr($class_name, 0, 7) == 'Canvas_') {
                $template_name = str_replace('_', DIRECTORY_SEPARATOR, $class_name);
            } else {
                $template_name = $class_name;
            }

            $tpl = MQF_APPLICATION_PATH.DIRECTORY_SEPARATOR.$template_name.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.$tpl;
        }

        MQF_Log::log("Template for Module '{$this->id}' is: ".$tpl);

        $this->template = $tpl;
    }

    /**
    * \brief Get the temporary template filename for this module
    *
    * \return string Template
    */
    public function getTemporaryTemplateName()
    {
        $t = $this->temp_template;
        $this->temp_template = '';

        return $t;
    }

    /**
    * \brief Set the temporary template filename for this module
    *
    * \param string Template
    */
    public function setTemporaryTemplateName($tpl)
    {
        $this->temp_template = $tpl;
    }

    /**
    * \brief Set the temporary template filename for this module
    *
    * \return boolean
    */
    public function hasTemporaryTemplate()
    {
        if ($this->temp_template != '') {
            return true;
        } else {
            return false;
        }
    }

    /**
    * \brief Get module Id
    *
    * \return string Id
    */
    public function getId()
    {
        return $this->id;
    }

    /**
    * \brief Get the HTML markup for this module
    *
    * \return string HTML
    * \throw Exception
    */
    public function getHTML()
    {
        $reg = MQF_Registry::instance();

        //Stop unauthorized module here, if some functions should be used, but html not exposed...
        if ($reg->hasMQ()) {
            if (!$reg->getMQ()->isModuleAuth($this->getId())) {
                return false;
            }
        }

        $html = $this->getModuleHTML();

        return $html;
    }

    /**
    * \brief Get the HTML markup for this module
    *
    * \return string HTML
    * \throw Exception
    */
    public function getModuleHTML()
    {
        $id = $this->getId();

        if ($this->disp instanceof MQF_UI_Display) {
            $this->beforeRefresh();

            $tplcachekey = $this->setModuleAssigns();
            $template    = $this->getTemplateName();

            if ($this->usehtmlcache) {
                $key = sha1($template.$tplcachekey);

                if ($html = MQF_Registry::instance()->getCacheById('__html_cache', $this->cachettl)->get($key)) {
                    MQF_Log::log("Display for {$id} [{$template}] retrieved from cache");

                    return $html;
                }
            }

            $this->setDefaultAssigns();

            $html  = "\n<!-- Module: {$id} START -->\n";
            $html .= $this->disp->fetchHTML($template);
            $html .= "\n<!-- Module: {$id} STOP -->\n";

            $this->disp->clean();

            if ($this->usehtmlcache) {
                MQF_Registry::instance()->getCacheById('__html_cache', $this->cachettl)->save($key, $html);
                MQF_Log::log("Display for {$id} [{$template}] saved to cache [$key]");
            }

            return $html;
        } else {
            MQF_Log::log("The canvas '$id' does not have a valid display. Check if parent constructor was called or the 'no-display' option is 'true'!", MQF_WARN);
        }
    }

    /**
    * \brief Export object property to display
    */
    protected function export($propertyname, $use_in_cachekey = false)
    {
        if (isset($this->exportlist[$propertyname])) {
            throw new Exception("Property '$propertyname' allready exported");
        }

        $this->exportlist[$propertyname] = true;

        if ($use_in_cachekey) {
            $this->templatecachevars[$propertyname] = $propertyname;
        }
    }

    /**
    * \brief Specifiy alternative name for property
    */
    protected function exportalias($org_name, $new_name)
    {
        $this->exportaliases[$org_name] = $new_name;
        MQF_Log::log("Variable '$org_name' will also be exposed as '$new_name'");
    }

    /**
    * \brief Get XML document for this MQF_Module
    *
    * \return string XML data
    */
    public function getXML()
    {
        $xml = $this->getReturnValue(self::RETVAL_AS_XML);

        $targetid = strtolower($this->getTargetId());

        if ($this->refreshPending() and $this->isVisible()) {
            if (MQF_Registry::instance()->getMQ()->isModuleAuth($this->getId())) {
                $xml .= "\n".'<response id="'.$targetid.'" js="'.$this->getId().'" type="element">'."<![CDATA[\n";
                $xml .= $this->getHTML();
                $xml .= "\n]]></response>\n";
            }

            $this->noRefresh();
        } elseif ($this->refreshPending() and !$this->isVisible()) {
            $xml .= "\n".'<response id="'.$targetid.'" js="'.$this->getId().'" type="element">'."<![CDATA[\n";
            $xml .= "\n]]></response>\n";
        }

        return $xml;
    }

    /**
     *
     * \fn setModuleAssigns()
     * \return void
     *
     * \brief export all object properties to the template engine
     *
     */
    public function setModuleAssigns()
    {
        $key = false;

        foreach ($this->exportlist as $propertyname => $nothing) {
            $this->assignTplVar($propertyname, $this->$propertyname);

            if (isset($this->templatecachevars[$propertyname])) {
                $key .= serialize($this->$propertyname);
            }
        }

        if ($key) {
            $key = sha1($key);
        }

        return $key;
    }

    /**
    * This method can be overridden in your own module to do some calculations before refresh
    */
    protected function beforeRefresh()
    {
    }

    /**
    * \brief Get the default key/value assigns for use in the template
    *
    * \return Array Default assigns
    */
    public function getDefaultAssigns()
    {
        $tmp = array();

        $reg = MQF_Registry::instance();

        if ($da = $reg->getValue('default_assign_'.$this->getId())) {
            return $da;
        }

        $reg->setValue('default_assign_'.$this->getId(), $tmp);

        return $tmp;
    }

    /**
    * \brief Set the default assigns for this module template
    */
    public function setDefaultAssigns()
    {
        $assigns = $this->getDefaultAssigns();

        foreach ($assigns as $key => $value) {
            $this->assignTplVar($key, $value);
        }

        $this->assignTplVar('this_message', $this->message);
        $this->assignTplVar('this_errormessage', $this->errormessage);
    }

    /**
    * \brief Set an error message in this module
    *
    * \param string Message
    */
    public function setErrorMessage($msg)
    {
        $this->errormessage = $msg;
    }

    /**
    * \brief Set a message in this module
    *
    * \param string Message
    */
    public function setMessage($msg)
    {
        $this->message = $msg;
    }

    /**
    * \brief Get which DOM Id is target for this module output
    *
    * \return string DOM Id
    */
    public function getTargetId()
    {
        if ($this->targetid == '') {
            return strtolower($this->getId());
        } else {
            return $this->targetid;
        }
    }

    /**
    * \brief Set which DOM id will be the target for this module output
    *
    * \param string DOM Id
    */
    public function setTargetId($target)
    {
        $this->targetid = $target;
    }

    /**
    * \brief Set a dynamic property for this module
    *
    * \param string Key
    * \param mixed Value
    */
    public function setProperty($key, $value)
    {
        $this->properties[$key] = $value;
        MQF_Log::log("Set property '$key' = '$value'", MQF_DEBUG);
    }

    /**
    * \brief Get a dynamic property from module
    *
    * \param string Key
    * \return mixed Property value
    */
    public function getProperty($key)
    {
        if (isset($this->properties[$key])) {
            return $this->properties[$key];
        }

        MQF_Log::log("Property '$key' not found", MQF_WARNING);

        return false;
    }

    /**
    * \brief Refresh this module display, and possibly remote modules
    */
    public function refreshModuleDisplay()
    {
        $this->refresh = true;
    }

    /**
    * \brief Check if this module has a refresh pending
    *
    * \return boolean Refresh pending
    */
    public function refreshPending()
    {
        return $this->refresh;
    }

    /**
    * \brief Set this module to not refresh
    */
    public function noRefresh()
    {
        $this->refresh = false;
    }

    /**
    *  Execute method in this module
    *
    * \param string Method
    * \param array Parameters
    *
    * \return mixed
    * \throw Exception
    *
    */
    public function executeMethod($method, $params)
    {
        if (method_exists($this, $method)) {
            $this->errormessage = '';
            $this->message      = '';

            $ret = call_user_func_array(array($this, $method), $params);

            $p = array();

            foreach ($params as $v) {
                $p[] = MQF_Tools::fixValue($v);
            }

            MQF_Log::log(get_class($this)."::$method(".implode(', ', $p).")", MQF_INFO);

            return $ret;
        } else {
            throw new Exception("Method '$method' does not exist in class ".get_class($this));
        }
    }

    /**
    * \brief Check if module is visible
    *
    * \return boolean
    */
    public function isVisible()
    {
        return $this->visible;
    }

    /**
    * \brief Set module hidden
    */
    public function setHidden()
    {
        $this->visible = false;
    }

    /**
    * \brief Set module visbible
    */
    public function setVisible()
    {
        $this->visible = true;
    }

    /**
    * \brief Assign variable to template
    *
    */
    public function assignTplVar($var, $value)
    {
        if ($this->disp instanceof MQF_UI_Display) {
            $this->disp->assign($var, $value);

            if (isset($this->exportaliases[$var])) {
                $this->assignTplVar($this->exportaliases[$var], $value);
                MQF_Log::log("Assigning '$var' as '".$this->exportaliases[$var]."' as well");
            }
        }
    }

    /**
    * \brief Get all assigned variables
    *
    */
    public function getTplVars()
    {
        if ($this->disp instanceof MQF_UI_Display) {
            return $this->disp->getTemplateVars();
        }
    }

    /**
    * \brief Set this modules exception to be thrown to browser
    *
    * \param Exception
    */
    public function setException($e)
    {
        $this->exception = MQF_Tools::convertExceptionToStdClass($e);
    }

    /**
    * \brief Get the exception if any
    *
    * \return stdClass or false
    */
    public function getException()
    {
        if ($this->exception) {
            $e = $this->exception;
            unset($this->exception);

            return $e;
        } else {
            return false;
        }
    }

    /**
    * \brief Check if this module currently has an exception
    *
    * \return bool
    */
    public function hasException()
    {
        if ($this->exception) {
            return true;
        }

        return false;
    }

    /**
    * \brief Get MQF_ReturnValue if any, and then clear it
    *
    * \return MQF_ReturnValue or false
    */
    public function getReturnValue($retmode = self::RETVAL_AS_OBJECT)
    {
        if ($retmode == self::RETVAL_AS_OBJECT) {
            if ($this->returnvalobj) {
                $t = $this->returnvalobj;
                unset($this->returnvalobj);

                return $t;
            } else {
                return false;
            }
        } elseif ($retmode == self::RETVAL_AS_XML) {
            $xml = '';

            if ($this->hasException()) {
                $xml .= MQF_Executor::getExceptionXML($this->getException());
            } elseif ($this->returnvalobj) {
                $xml .= "\n".'<response id="mqfCallbackBroker" type="object">'."<![CDATA[\n";
                $xml .= MQF_Tools::JsonEncode($this->returnvalobj);
                $xml .= "\n]]></response>\n";

                unset($this->returnvalobj);
            }

            return $xml;
        } else {
            throw new Exception("Unknown mode '$retmode' for getting returnvalue of module");
        }
    }

    /**
    * \brief Set returnvalue and what callback that should receive it in the browser
    *
    * \param mixed value
    * \param string callback
    */
    public function setReturnValue($value, $callback = '')
    {
        $this->returnvalobj = new MQF_UI_ReturnValue($value, $callback);
        MQF_Log::log("Set returnvalue for module '{$this->id}' to callback '{$callback}': ".print_r($value, true));

        return $this->returnvalobj;
    }

    /**
    * \brief Check if this module currently has an MQF_ReturnValue
    *
    * \return bool
    */
    public function hasReturnValue()
    {
        if ($this->returnvalobj == null) {
            return false;
        }

        return true;
    }

    /**
     *
     */
    public function clearReturnValue()
    {
        unset($this->returnvalobj);
    }

    /**
    *
    */
    public function addTranslation($orgstring, $newstring, $language, $refresh)
    {
        $i18n = MQF_Registry::instance()->getUI()->getI18N();
        $i18n->setLanguage($language);
        $i18n->addTranslation($orgstring, $newstring);

        if ($refresh) {
            $this->refreshModuleDisplay();
        }
    }

    /**
    * For refreshing the translationDialog div
    */
    public function getTranslationDialogContent($domID)
    {
        $i18n = MQF_Registry::instance()->getUI()->getI18N();

        $result['domID'] = $domID;
        $result['HTML'] = $i18n->getTranslationDialogContent();

        return $result;
    }

    /**
    *
    */
    public function changeLanguage($lang)
    {
        $lang = strtolower(trim($lang));

        $i18n = MQF_Registry::instance()->getUI()->getI18N();

        if ($lang == $i18n->getLanguage()) {
            return true;
        }

        $i18n->setLanguage($lang);

        $this->refreshModuleDisplay();
    }
}
