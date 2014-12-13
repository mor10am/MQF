<?php

require 'Zend/Controller/Request/Http.php';
require 'Zend/Rest/Server.php';

/**
* \class MQF_Rest_Controller
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id:$
*
* Options for controller are "ajax-only" and "debug".
*
* 1) ajax-only: when used, the REST server will only listen to AJAX calls,
*    and return a 404 header if run in any other way.
*
* 2) debug: When "true", it will override the 404 redirecting, and instead
*    return an XML with error message.
*
*/
abstract class MQF_Rest_Controller extends Zend_Rest_Server
{
    protected $_methods    = array();
    protected $_server     = null;
    protected $_restserver = null;
    protected $_classname  = '';

    protected $_reqparams  = array();
    protected $_request    = null;

    protected $_serializeto = 'xml';

    protected $_ajaxonly     = false;
    protected $_debug        = false;
    protected $_cachebackend = 'File';
    protected $_benchmark    = false;
    protected $_timer        = null;

    /**
    *
    */
    public function __construct($options = array())
    {
        $this->_options = $options;

        $this->_request   = new Zend_Controller_Request_Http();
        $this->_reqparams = $this->_request->getParams();

        if (isset($options['ajax-only'])) {
            $this->_ajaxonly = $options['ajax-only'];
        }

        if (isset($options['debug'])) {
            $this->_debug = $options['debug'];
        }

        if (isset($options['cachebackend'])) {
            $this->_cachebackend = $options['cachebackend'];
        }

        if (isset($options['benchmark'])) {
            require_once 'Benchmark/Timer.php';
            $this->_timer     = new Benchmark_Timer();
            $this->_benchmark = $options['benchmark'];
        }

        parent::__construct();
    }

    /**
    *
    */
    public function setClassName($class)
    {
        $this->_classname = $class;
    }

    /**
    *
    */
    protected function _throwError($msg, $forcedebug = false)
    {
        MQF_Log::log($msg, MQF_ERROR);

        if ($this->_debug or $forcedebug) {
            die($this->_serializeReturnvalue($this->fault(new Exception($msg))->saveXML()));
        } else {
            $this->send404();
        }
    }

    /**
    *
    */
    protected function _extractAnnotations($refclass)
    {
        if (!$refclass instanceof ReflectionClass) {
            $this->_throwError("Unable to extract annotations. Input is not a valid class!");
        }

        $classname = $refclass->getName();

        $filename = 'rest.class.'.$classname.'.methods';

        if (file_exists($filename)) {
            $methods = unserialize(file_get_contents($filename));

            if ($methods and is_array($methods) and count($methods) > 0) {
                $this->_methods[$classname] = $methods;
            }
        }

        $methods = $refclass->getMethods();

        foreach ($methods as $m) {
            $docblock = strtolower($m->getDocComment());
            $name = $m->getName();

            if ($m->isPublic() and strstr($docblock, '@mqf.restful') !== false) {
                if (strstr($docblock, '@mqf.rest.post') !== false) {
                    $this->_methods[$classname][$name]['httpmethods'][] = 'post';
                }
                if (strstr($docblock, '@mqf.rest.put') !== false) {
                    $this->_methods[$classname][$name]['httpmethods'][] = 'put';
                }
                if (strstr($docblock, '@mqf.rest.delete') !== false) {
                    $this->_methods[$classname][$name]['httpmethods'][] = 'delete';
                }
                if (strstr($docblock, '@mqf.rest.get') !== false) {
                    $this->_methods[$classname][$name]['httpmethods'][] = 'get';
                }

                if (empty($this->_methods[$classname][$name]['httpmethods']) or
                    !count($this->_methods[$classname][$name]['httpmethods'])) {
                    $this->_methods[$classname][$name]['httpmethods'][] = 'get';
                }

                $this->_methods[$classname][$name]['cache'] = false;

                preg_match("/\@mqf\.rest\.cache\s*?=\s*?(\d+)/", $docblock, $matches);

                if (count($matches) == 2 and is_numeric($matches[1]) and $matches[1] > 0) {
                    $this->_methods[$classname][$name]['cache'] = $matches[1];
                }
            }
        }

        if (!empty($this->_methods[$classname])) {
            file_put_contents($filename, serialize($this->_methods[$classname]));
        }
    }

    /**
    *
    */
    protected function _isRestEnabled($methodname, $classname)
    {
        $httpmethod = strtolower($this->_request->getMethod());

        if (empty($this->_methods[$classname][$methodname]['httpmethods'])) {
            return false;
        }
        if (!isset($this->_methods[$classname][$methodname]) or !is_array($this->_methods[$classname][$methodname]['httpmethods'])) {
            return false;
        }

        return in_array($httpmethod, $this->_methods[$classname][$methodname]['httpmethods']);
    }

    /**
    *
    */
    protected function _isRestMethodCached($methodname, $classname)
    {
        if (!isset($this->_methods[$classname][$methodname]['cache'])) {
            return false;
        }

        if ($this->_methods[$classname][$methodname]['cache'] > 0) {
            return $this->_methods[$classname][$methodname]['cache'];
        }

        return false;
    }

    /**
    *
    */
    protected function _serializeReturnvalue($return)
    {
        if ($this->_serializeto == 'xml') {
            header("Content-Type: text/xml");
        } else {
            include_once 'XML/Unserializer.php';

            $unser = new XML_Unserializer();
            $unser->unserialize($return);

            $data = $unser->getUnserializedData();

            switch ($this->_serializeto) {
            case 'php':
                $return = serialize($data);
                header("Content-Type: text/plain");
                break;
            case 'json':
                $return = MQF_Tools::jsonEncode($data);

                header("Content-Type: text/javascript");
                break;
            default:
              header("Content-Type: text/xml");
            }
        }

        return $return;
    }

    /**
    *
    */
    protected function _setupFromRequestVars()
    {
        if (isset($this->_reqparams['rest'])) {
            unset($this->_reqparams['rest']);
            unset($_REQUEST['rest']);
        }

        if (isset($this->_reqparams['_appkey'])) {
            MQF_Registry::instance()->setValue('APPKEY', $this->_reqparams['_appkey']);
            unset($this->_reqparams['_appkey']);
            unset($_REQUEST['_appkey']);
        }

        $this->_serializeto = 'xml';

        if (isset($this->_reqparams['_serialize'])) {
            $ser = strtolower(trim($this->_reqparams['_serialize']));

            switch ($ser) {
            case 'json':
                $this->_serializeto = 'json';
                break;
            case 'php':
                $this->_serializeto = 'php';
                break;
            case 'xml':
            default:
                $this->_serializeto = 'xml';
                break;
            }

            unset($this->_reqparams['_serialize']);
            unset($_REQUEST['_serialize']);
        }
    }

    /**
    *
    */
    protected function _transformResult($result)
    {
        if ($result instanceof SimpleXMLElement) {
            $response = $result->asXML();
        } elseif ($result instanceof DOMDocument) {
            $response = $result->saveXML();
        } elseif ($result instanceof DOMNode) {
            $response = $result->ownerDocument->saveXML($result);
        } elseif (is_array($result) || is_object($result)) {
            $response = $this->_handleStruct($result);
        } else {
            $response = $this->_handleScalar($result);
        }

        return $response;
    }

    /**
    *
    */
    abstract public function run();

    /**
    *
    */
    protected function send404()
    {
        $response = new Zend_Controller_Response_Http();
        echo $response->setRedirect("/", 404);
        exit;
    }
}
