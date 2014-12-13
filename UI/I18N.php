<?php

require_once 'MQF/Database.php';
require_once 'adodb/drivers/adodb-sqlite.inc.php';

/**
*
*/
class MQF_UI_I18N
{
    const DISPLAY = 1;
    const EDIT    = 2;

    private $_lang = 'en';
    private $_translations = array();
    private $_db = null;
    private $_mode = self::DISPLAY;
    private $_icon = ''; // The icon used in links
    private $_phraseReferences = array();

    /**
    *
    */
    public function __construct($lang = 'en')
    {
        $lang = strtolower(trim($lang));

        if (!$lang) {
            throw new Exception("No language code given!");
        }

        if ($lang == 'browser') {
            $string = $_SERVER["HTTP_ACCEPT_LANGUAGE"];
            $lang = strtolower(substr($string, 0, 2));
        }

        $this->_lang = $lang;

        $this->_connect();

        $this->_mode = MQF_Registry::instance()->getConfigSettingDefault('gui', 'i18n_mode', MQF_MultiQueue::CONFIG_VALUE, self::DISPLAY);
        $this->_icon = MQF_Registry::instance()->getConfigSettingDefault('gui', 'i18n_icon', MQF_MultiQueue::CONFIG_VALUE, 'images/translate.gif');

        $this->_checkOrMakeTable();

        $this->_loadTranslations();

        MQF_Log::log("Creating I18N($lang) mode:{$this->_mode}");
    }

    /**
    *
    */
    public function __toString()
    {
        return "<Object MQF_UI_I18N> Language: {$this->_lang} Mode: {$this->_mode}";
    }

    /**
    *
    */
    private function _connect()
    {
        $this->_db = new MQF_Database('mqfi18n', array('driver' => 'sqlite', 'database' => MQF_APPLICATION_PATH.DIRECTORY_SEPARATOR."mqfi18n.sqlite"));
    }

    /**
    *
    */
    private function _checkOrMakeTable()
    {
        $this->_db->connect();

        try {
            $meta = $this->_db->getMetadataForTable('translations');
            MQF_Log::log(print_r($meta, true));

            return true;
        } catch (MQF_Database_Exception_NoSuchTable $e) {
            $this->_db->execute("create table translations (lang varchar, basestring varchar, translation varchar)");
        }

        return true;
    }

    /**
    *
    */
    private function _loadTranslations()
    {
        $this->_db->connect();

        $rs = $this->_db->execute("select * from translations where lang = ?", array($this->_lang));

        while ($obj = $rs->FetchNextObject(true)) {
            $this->_translations[$obj->BASESTRING] = $obj->TRANSLATION;
        }

        $rs->Close();

        return true;
    }

    /**
    *
    */
    public function getLanguage()
    {
        return $this->_lang;
    }

    /**
    *
    */
    public function getMode()
    {
        return $this->_mode;
    }

    /**
    *
    */
    public function getIcon()
    {
        return $this->_icon;
    }

    /**
    *
    */
    public function setLanguage($lang)
    {
        $lang = strtolower(trim($lang));

        if (!$lang) {
            throw new Exception('Language code is blank');
        }

        MQF_Log::log("Set: $lang - Previous: {$this->_lang}");

        if ($this->_lang != $lang) {
            $this->_lang = $lang;

            $this->_translations = array();

            $this->_checkOrMakeTable();

            $this->_loadTranslations();
        }
    }

    /**
    *
    */
    public static function translate($string)
    {
        if (!$i18n = MQF_Registry::instance()->getUI()->getI18N()) {
            return $string;
        }

        return $i18n->lookup($string);
    }

    /**
    *
    */
    public function lookup($string)
    {
        if (isset($this->_translations[$string])) {
            return $this->_translations[$string];
        } else {
            return $string;
        }
    }

    /**
    *
    */
    public function addTranslation($orgstring, $newstring)
    {
        $this->_connect();

        if (isset($this->_translations[$orgstring])) {
            $query = "update translations set translation = ? where basestring = ? and lang = ?";
            $params = array($newstring, $orgstring, $this->_lang);
        } else {
            $query = "insert into translations (basestring, translation, lang) values (?,?,?)";
            $params = array($orgstring, $newstring, $this->_lang);
        }

        $this->_translations[$orgstring] = $newstring;
        $this->_phraseRefresh($orgstring, $newstring); // Refresh on Phrase table

        $this->_db->connect();

        $this->_db->execute($query, $params);
    }

    /**
    * Refreshing all occurence of phraseReferences (translation dialog refresh);
    */
    private function _phraseRefresh($content, $string)
    {
        foreach ($this->_phraseReferences as $key => $data) {
            if ($data['content'] == $content) {
                $data['string'] = $string;
                $this->_phraseReferences[$key] = $data;
            }
        }
    }

    /**
    * Saves Phrase reference
    */
    public function setPhraseReferences($templateName, $blockType, $content, $string)
    {
        $key = $templateName.'|'.$content;

        $this->_phraseReferences[$key]['template'] = $templateName;
        $this->_phraseReferences[$key]['content'] = $content;
        $this->_phraseReferences[$key]['string'] = $string;
        $this->_phraseReferences[$key][$blockType] = 'X'; // occurence
    }

    /**
    * Gives back the HTML content of the TDialog
    */
    public function getTranslationDialogContent()
    {
        $reg = MQF_Registry::instance();
        $canvas = $reg->getConfigSetting('gui', 'canvas');

        ksort($this->_phraseReferences); // sort by templateName, Content

        // We format the captions to links that needs to be translated with the dialog
        $links = "<table style='border-collapse: collapse;' border='1' bordercolor='#cccccc' cellpadding='5'>
            <tr style='font-weight: bold;'>
                <td>Template file</td>
                <td>Original text</td>
                <td align='center'>block {t}</td>
                <td align='center'>block {tc}</td>
                <td>Translation</td>
            </tr>";

        $format = "
            <tr>
                <td>%s</td>
                <td>%s</td>
                <td align='center'>%s&nbsp;</td>
                <td align='center'>%s&nbsp;</td>
                <td><a href='#' onclick=\"MQF.openTranslateUI('{$canvas}', '%s', '%s', '{$this->_lang}', false);
                MQF.refreshTranslateDialog('{$canvas}', 'translateDialogContent'); return false;\">%s</a></td>
            </tr>";

        foreach ($this->_phraseReferences as $data) {
            $links .= sprintf($format,
                $data['template'], $data['content'], $data['t'], $data['tc'],
                addslashes($data['content']), addslashes($data['string']), $data['string']);
        }

        $links .= '</table>';

        return $links;
    }
}
