<?php


/**
 *
 * \class MQF_TemplateEngine
 * \author Magnus Espeland <magg@tpn.no>
 * \author Morten Amundsen <mortena@tpn.no>
 * \version $Id: TemplateEngine.php 1071 2008-07-01 07:33:50Z attila $
 *
 * \brief Wraps and extends other template engines
 *
 *
 *
 *
 */

///  So we can switch engine without touching any other classes/files.
require_once 'Smarty.class.php';

class MQF_UI_TemplateEngine extends Smarty
{
    /**
     *
     * \fn __construct()
     *
     * \brief Construct method
     *
     *
     */
    public function __construct($plugin = false, $options = array())
    {
        parent::__construct();

        if (!defined('MQF_APPLICATION_TEMPLATES')) {
            define('MQF_APPLICATION_TEMPLATES', './templates');
        }

        if (!defined('MQF_APPLICATION_TEMPLATES_C')) {
            define('MQF_APPLICATION_TEMPLATES_C', './');
        }

        $this->template_dir = MQF_APPLICATION_TEMPLATES;
        $this->compile_dir = MQF_APPLICATION_TEMPLATES_C;

        if (isset($options['mode'])) {
            $mode = $options['mode'];
        } else {
            $mode = MQF_TEMPLATE_ENGINE_MODE_CURLY;
        }

        if ($mode == MQF_TEMPLATE_ENGINE_MODE_TT2) {
            $this->left_delimiter  = '[%';
            $this->right_delimiter = '%]';
        }

        if ($plugin) {
            if (is_object($plugin)) {
                $this->_registerPlugin($plugin);
            } else {
                $this->_registerPlugin(new $plugin());
            }
        }

        $this->_registerPlugin($this);
    }

    /**
    * \brief Register plugin objects functions
    */
    private function _registerPlugin($plugin = null)
    {
        if (!is_object($plugin)) {
            MQF_Log::log("Tried to register plugin to templateengine, but didn't pass an object", MQF_WARN);

            return false;
        }

        $ref     = new ReflectionObject($plugin);
        $methods = $ref->getMethods();

        foreach ($methods as $method) {
            $name = $method->getName();

            if (substr($name, 0, 7) == 'tplfunc') {
                $smartyfunction = strtolower(substr($name, 7, strlen($name) - 7));
                $this->register_function($smartyfunction, array(&$plugin, $name));
            } elseif (substr($name, 0, 6) == 'tplmod') {
                $smartyfunction = strtolower(substr($name, 6, strlen($name) - 6));
                $this->register_modifier($smartyfunction, array(&$plugin, $name));
            } elseif (substr($name, 0, 8) == 'tplblock') {
                $smartyfunction = strtolower(substr($name, 8, strlen($name) - 8));
                $this->register_block($smartyfunction, array(&$plugin, $name));
            }
        }
    }

    /**
    * \brief Clear assigned variable
    */
    public function clearAssign($var)
    {
        $this->clear_assign($var);
    }

    /**
    * \brief Clear all assigned variables
    */
    public function clearAllAssigns()
    {
        $this->clear_all_assign();
    }

    /**
    * \brief get assigned variable
    */
    public function getTemplateVars($var)
    {
        return $this->get_template_vars($var);
    }

    /**
    * {input-observe type='text' id='myid' event='change' callback='callback'}
    *
    */
    public function tplfuncInputObserve($params, $smarty)
    {
        if (!isset($params['type'])) {
            throw new Exception("Input type is missing");
        }
        $type = $params['type'];
        unset($params['type']);

        if (!isset($params['id'])) {
            throw new Exception("Id is missing");
        }
        $id = $params['id'];
        unset($params['id']);

        if (!isset($params['event'])) {
            throw new Exception("Event type is missing");
        }
        $event = $params['event'];
        unset($params['event']);

        if (isset($params['callback'])) {
            $callback = $params['callback'];
            unset($params['callback']);
        }

        if (isset($params['action'])) {
            $action = $params['action'];
            unset($params['action']);
        }

        if ($callback and $action) {
            throw new Exception("Unable to use both 'action' and 'callback'");
        }

        $html = "<input type='$type' id='$id' ";
        foreach ($params as $key => $value) {
            $html .= " {$key}='{$value}' ";
        }
        $html .= "/>\n";
        $html .= "<script>\n";
        if ($callback) {
            $html .= "Event.observe('$id', '$event', $callback);\n";
        } elseif ($action) {
            $html .= "Event.observe('$id', '$event', function(e) {mqfCallMethod('$action', [\$F('{$id}')])});\n";
        } else {
            $html .= "<!-- no event for $id -->";
        }
        $html .= "</script>\n";

        return $html;
    }

    /**
    *
    */
    public function tplfuncGotoCanvas($params, $smarty)
    {
        $c = $params['canvas'];

        $ref = new ReflectionClass($c);

        if (!$canvas = $params['canvas']) {
            throw new Exception("No canvas parameter defined!");
        }

        $reg = MQF_Registry::instance();

        $url = $reg->getValue('scripturl');
        $sid = $reg->getSessionId();

        $p = new stdClass();
        $p->p = $canvas;

        $parameter = MQF_Tools::jsonEncode($p);

        $o = new stdClass();
        $o->o = mt_rand(1, 100000);

        $option = MQF_Tools::jsonEncode($o);

        $url = "{$url}?F=UI.changeCanvas@{$sid}&R=element&O_execid=".urlencode($option)."&P_0=".urlencode($parameter);

        return $url;
    }

/* These functions used in translation process ---------------------------------------------------------------------- */

    /**
    * Creates a translation link with the predefined icon to the given element
    *
    * @param $content string The string found in the template between {t} {/t} elements
    * @param $string  string The translated string
    *
    * @return string The created image link
    */
    private function _createTranslateLink($content, $string)
    {
        $reg = MQF_Registry::instance();
        $canvas = $reg->getConfigSetting('gui', 'canvas');

        $i18n = $reg->getUI()->getI18N();
        $lang = $i18n->getLanguage();
        $icon = $i18n->getIcon();

        $content = addslashes($content); // Because of ' characters
        $string = addslashes($string); // Because of ' characters

        return "<a href=\"javascript:MQF.openTranslateUI('{$canvas}', '{$content}', '{$string}', '{$lang}', true);\"
            class='translate' title='[Translate] {$string}'><img src='$icon' align='absmiddle' border='0'></a>";
    }

    /**
    * Gives back the template name from the given reference
    */
    private function _getTemplateName($reference)
    {
        $parts = pathinfo($reference);

        return $parts['basename'];
    }

    /**
    * For normal translation links (in editmode a translation icon appears)
    */
    public function tplblockT($params, $content, &$smarty, &$repeat)
    {
        $string = MQF_UI_I18N::translate($content);

        $reg = MQF_Registry::instance();
        $i18n = $reg->getUI()->getI18N();

        if (!$i18n) {
            return $content;
        }

        // We save it to use with translation dialog
        if ($content != '') {
            $template = $this->_getTemplateName($this->_plugins['block']['t'][1]);
            $i18n->setPhraseReferences($template, 't', $content, $string);
        }

        switch ($i18n->getMode()) {
            case MQF_UI_I18N::EDIT:
                return $string.' '.$this->_createTranslateLink($content, $string);
                break;

            case MQF_UI_I18N::DISPLAY:
            default:
                return $string;
        }
    }

    /**
    * For dialog translations -> we translate the text and we put a request to the dialog
    */
    public function tplblockTC($params, $content, &$smarty, &$repeat)
    {
        $string = MQF_UI_I18N::translate($content);

        $reg = MQF_Registry::instance();
        $i18n = $reg->getUI()->getI18N();

        if (!$i18n) {
            return $content;
        }

        // We save it to use with translation dialog
        if ($content != '') {
            $template = $this->_getTemplateName($this->_plugins['block']['tc'][1]);
            $i18n->setPhraseReferences($template, 'tc', $content, $string);
        }

        return $string;
    }

    /**
    * This custom function creates a link to a javascript dialog window
    */
    public function tplfuncTDialog($params, &$smarty)
    {
        $reg = MQF_Registry::instance();
        $canvas = $reg->getConfigSetting('gui', 'canvas');
        $i18n = $reg->getUI()->getI18N();

        if (!$i18n) {
            return '';
        }

        // We only write out the dialog script when translation is in edit mode
        if ($i18n->getMode() == MQF_UI_I18N::EDIT) {
            $reg = MQF_Registry::instance();
            $i18n = $reg->getUI()->getI18N();

            $output = "
                <div onMouseOver=\"$('translateDialog').style.display = 'block';
                    MQF.refreshTranslateDialog('{$canvas}', 'translateDialogContent'); return false;\"
                    style='position: absolute; top: 0px; background-color: #000000; color: #FFFFFF; cursor: pointer;
                    font-weight: bold; padding: 3px;'>
                    Translation
                </div>
                <div style='border: 1px solid #000000; padding: 5px; margin: 5px; display: none; overflow: auto; height: 600px; width: 900px;
                    background-color: #ffffff; position: absolute; top: 0px;' id='translateDialog'>

                        <h3>Phrases to translate</h3>

                        <div id='translateDialogContent'>Refreshing...</div>

                    <br />
                    <div style='text-align: center;'>
                        <input type='button' onclick=\"$('translateDialog').hide();\" value=' Hide dialog '>

                        <input type='button' onclick=\"MQF.refreshTranslateDialog('{$canvas}', 'translateDialogContent');\"
                            value=' Refresh dialog '>

                        <input type='button' onclick=\"window.location.reload( false );\" value=' Refresh page '>

                    </div>
                </div>
                ";

            return $output;
        }
    }

    public function tplfuncInsertModule($params, &$smarty)
    {
        if (!isset($params['module'])) {
            throw new Exception("Need 'module' parameter!");
        }

        $element = 'span';

        if (isset($params['element'])) {
            $element = strtolower(trim($params['element']));

            if ($element != 'div' and $element != 'span') {
                throw new Exception("Illegal element for module. Should be span or div.");
            }
        }

        $dynamic = false;

        if (isset($params['dynamic'])) {
            $dynamic = $params['dynamic'];
        }

        $moduleid = $params['module'];

        unset($params['element']);
        unset($params['module']);
        unset($params['dynamic']);

        $canvas = MQF_Registry::instance()->getUI()->getCurrentCanvas();

        try {
            $module = MQF_UI::find($moduleid, MQF_UI::MQF_FIND_MODULE_ALL);
        } catch (Exception $e) {
            $module = $canvas->addModule(new $moduleid($params), $dynamic);
        }

        // If this canvas doesn't contain the module (found in another canvas)
        // We assign the module to this canvas also (just reference, not new instance)
        if (!$canvas->getCanvasModuleById($module->getId())) {
            $canvas->addModule($module, $dynamic);
        }

        $module->refreshModuleDisplay();

        $html = "<{$element} id='".strtolower($module->getId())."'>";
        $html .= $module->getHTML();
        $html .= "</{$element}>";

        $module->noRefresh();

        return $html;
    }

    /**
    *
    */
    public function tplfuncMQFJavascript($params, &$smarty)
    {
        $reg = MQF_Registry::instance();

        if ($reg->getValue('mqf_javascript_built') === true) {
            return true;
        }

        $javascripts = '';
        $jslist      = array();
        $jskey       = '';
        $tmpjs       = '';

        $js_baseurl = trim($reg->getConfigSetting('mqf', 'js_baseurl'));

        if (substr($js_baseurl, strlen($js_baseurl)-1, 1) != '/') {
            $js_baseurl .= '/';
        }

        $javascripts .= "
        <style>
        .waiting {
            background-image:url('{$js_baseurl}/protoload/waiting.gif');
            background-repeat:no-repeat;
            background-position:center center;
            background-color:white;
        }

        .bigWaiting {
            background-image:url('{$js_baseurl}/protoload/bigWaiting.gif');
            background-repeat:no-repeat;
            background-position:center 20%;
            background-color:white;
        }

        .blackWaiting {
            background-image:url('{$js_baseurl}/protoload/blackWaiting.gif');
            background-repeat:no-repeat;
            background-position:center center;
            background-color:black;
        }

        .bigBlackWaiting {
            background-image:url('{$js_baseurl}/protoload/bigBlackWaiting.gif');
            background-repeat:no-repeat;
            background-position:center center;
            background-color:black;
        }
        </style>
        ";

        $js_cache = $reg->getConfigSettingDefault('mqf', 'js_cache', MQF_MultiQueue::CONFIG_VALUE, false);

        $jsa = $reg->getConfigSettingDefault('mqf_javascripts', '', MQF_MultiQueue::CONFIG_GROUP, array());

        $jsa = array_merge(array('JSBASE prototype.js', 'JSBASE protoload/protoload.js', 'JSBASE mqf.js', 'JSBASE full_exception.js'), $jsa);

        foreach ($jsa as $url) {
            // add js_baseurl to url's if they have the keyword and $js_baseurl is a true value
            if (substr($url, 0, 7) == 'JSBASE ' && $js_baseurl) {
                $url = substr($url, 7);

                if (!isset($jslist["$js_baseurl$url"])) {
                    $jslist["$js_baseurl$url"] = "{$js_baseurl}{$url}";

                    $jskey .= "$js_baseurl$url";
                    $tmpjs .= "<script type='text/javascript' src='{$js_baseurl}{$url}'></script>\n";
                }
            } elseif (substr($url, 0, 7) == 'JSBASE ' && !$js_baseurl) {
                throw new Exception('JSBASE keyword set, but no valid js_baseurl in config.ini');
            } else {
                // leave absolute url's (and every url if $js_baseurl isn't set
                if (!isset($jslist[$url])) {
                    $jslist[$url] = $url;

                    $jskey .= $url;
                    $tmpjs .= "<script type='text/javascript' src='$url'></script>\n";
                }
            }
        }

        if ($js_cache) {
            $jskey    = md5($jskey);
            $app_path = $reg->getValue('basedir');

            // Check if concated javascript file exists.
            if (!file_exists("$app_path".DIRECTORY_SEPARATOR."{$jskey}.js")) {
                $buffer = '';
                $path   = $_SERVER["DOCUMENT_ROOT"];

                // Load each javascript, and put all of them in one file.
                foreach ($jslist as $js) {
                    if ($buf = MQF_JSMin::minify(file_get_contents($js))) {
                        $buffer .= $buf;
                        MQF_Log::log("Fetcing JavaScript file $js");
                    } else {
                        MQF_Log::log("Unable to load JavaScript file $js for caching", MQF_WARN);
                    }
                }

                file_put_contents("$app_path".DIRECTORY_SEPARATOR."{$jskey}.js", $buffer);
            }
            // This is the only javascript we're loading
            $javascripts .= "<script type='text/javascript' src='{$jskey}.js'></script>\n";
        } else {
            // This make MQF load all the original scripts one by one
            $javascripts .= $tmpjs;
        }

        $javascripts .= "
                        <script>
                        <!--

                        function mqfCallMethod()
                        {
                            var calloptions = MQF.getCallOptions('".$reg->getValue('scripturl')."', '".$reg->getSessionId()."', arguments);
                            if (!calloptions) return false;
                            MQF.call(calloptions);
                        }

                        function mqfGetCallUrl()
                        {
                            var opts = MQF.getCallOptions('".$reg->getValue('scripturl')."', '".$reg->getSessionId()."', arguments);
                            if (!opts) return false;
                            return opts.url + '?' + opts.data;
                        }

                        -->
                        </script>";

        $reg->setValue('mqf_javascript_built', true);

        return $javascripts;
    }
}
