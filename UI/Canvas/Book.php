<?php

/**
* Book
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: Book.php 952 2008-01-20 17:02:49Z mortena $
*
*/
abstract class MQF_UI_Canvas_Book extends MQF_UI_Canvas
{
    protected $canvaslist = array();      ///< Array of MQF_UI_Canvas Ids in this MQF_Book
    protected $currcanvas;                ///< Id of current MQF_Canvas
    protected $moduleoptions = array();   ///< The options passed to the book, needs to be saved, so we can pass them on to the canvases

    /**
    * \brief Constructor
    */
    public function __construct($options = array())
    {
        $this->moduleoptions = $options;

        $options['no-display'] = true;

        parent::__construct($options);
    }

    /**
    * \brief Get the current MQF_Canvas Id
    */
    public function getCurrentCanvasId()
    {
        return $this->currcanvas;
    }

    /**
    * \brief Set the current MQF_Canvas.
    */
    protected function setCurrentCanvas($canvasid, $refresh = true)
    {
        if ($canvasid == $this->currcanvas) {
            return true;
        }

        $canvas = $this->getCanvasInstance($canvasid);

        if ($refresh) {
            $this->refreshModuleDisplay();
        }

        $this->currcanvas = $canvasid;
    }

    /**
    * \brief Get list of MQF_Canvas associated with this MQF_Book
    *
    * \return array
    */
    public function getCanvasIdList()
    {
        return $this->canvaslist;
    }

    /**
    * \brief Register a new MQF_Canvas. This will not instantiate the MQF_Canvas, unless it's default
    *
    * \param string Id
    * \param bool   default
    */
    protected function registerCanvasId($canvasid, $default = false)
    {
        $this->canvaslist[$canvasid] = $canvasid;

        if ($default) {
            $this->setCurrentCanvas($canvasid, false);
        }
    }

    /**
    * \brief Add a new MQF_Canvas
    *
    * \param MQF_Canvas  instance of MQF_Canvas
    * \return MQF_Canvas
    * \throw Exception
    */
    protected function addCanvas($canvas)
    {
        if ($canvas instanceof MQF_UI_Canvas) {
            $this->addModule($canvas);
            $this->canvaslist[$canvas->getId()] = $canvas->getId();

            return $canvas;
        } else {
            $msg = MQF_Log::log("Unable to add Canvas to Book. Wrong type!", MQF_ERROR);
            throw new Exception($msg);
        }
    }

    /**
    * \brief Get XML for current MQF_Canvas, and it's MQF_Module
    *
    * \return string XML document
    */
    public function getXML()
    {
        if ($this->currcanvas == '') {
            return false;
        }

        if (!isset($this->canvaslist[$this->currcanvas])) {
            throw new Exception("Canvas {$this->currcanvas} is not registered!");
        }

        $canvas = $this->getCanvasInstance($this->currcanvas);

        $xml = $canvas->getReturnValue(self::RETVAL_AS_XML);

        if ($canvas->refreshPending() and $canvas->isVisible()) {
            $targetid = $this->getId();

            if (MQF_Registry::instance()->getMQ()->isModuleAuth($this->getId())) {
                $xml .= "\n".'<response id="'.strtolower($targetid).'" type="element">'."<![CDATA[\n";
                $xml .= $this->getHTML();
                $xml .= "\n]]></response>\n";
            }

            $canvas->noRefresh();
        } else {
            if (!$canvas->hasModules()) {
                return $xml;
            }

            $modules = $canvas->getModuleList();

            foreach ($modules as $mod) {
                $xml .= $mod->getXML();
            }
        }

        return $xml;
    }

    /**
    * \brief Get the current MQF_UI_Canvas HTML
    *
    * \return string HTML document
    */
    public function getHTML()
    {
        if ($this->currcanvas == '') {
            return false;
        }

        if (!isset($this->canvaslist[$this->currcanvas])) {
            throw new Exception("Canvas {$this->currcanvas} is not registered!");
        }

        $canvas = $this->getCanvasInstance($this->currcanvas);

        $reg = MQF_Registry::instance();

        $canvas->beforeRefresh();

        $canvas->assignModulesHTML();
        $canvas->setDefaultAssigns();

        $canvas->assignTplVar('this_canvaslist', $this->canvaslist);
        $canvas->assignTplVar('this_currentcanvas', $this->currcanvas);

        $thisid   = $this->getId();
        $canvasid = $canvas->getId();

        $html  = "\n<!-- Book: $thisid showing $canvasid START -->\n";
        $html .= $canvas->disp->fetchHTML($canvas->getTemplateName());
        $html .= "\n<!-- Book: $thisid showing $canvasid STOP -->\n";

        return $html;
    }

    /**
    * \brief Get the current MQF_Canvas instance
    *
    * \param string Id
    * \return MQF_Canvas Instance of MQF_Canvas
    * \throw Exception
    */
    protected function getCanvasInstance($canvasid)
    {
        if (trim($canvasid) == '') {
            throw new Exception("Unable to get instance of blank id!");
        }

        if (!($canvas = $this->getCanvasModuleById($canvasid))) {
            try {
                $canvas = $this->addCanvas(new $canvasid($this->moduleoptions));
            } catch (Exception $e) {
                MQF_Log::log($e->getMessage(), MQF_ERROR);
                throw new Exception("Unable to instatiate class $canvasid. File not found or ".$e->getMessage());
            }
        }

        return $canvas;
    }

    /**
    * \brief Change current canvas
    *
    * \param string Id
    */
    public function changeCanvas($canvasid)
    {
        if ($canvasid != $this->currcanvas) {
            $this->setCurrentCanvas($canvasid);
        }
    }
}
