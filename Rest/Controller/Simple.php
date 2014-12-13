<?php

require_once 'MQF/Rest/Controller.php';

/**
*
* Setup example for "index.php"
*
* <code>
*
* require_once 'Zend/Loader.php';
* Zend_Loader::registerAutoload();
*
* require_once 'MQF/Log.php';
* require_once 'MQF/Rest/Controller/Simple.php';
*
* MQF_Log::registerLogger('rest.log');
*
*
* $options = array(
*   'cachebackend'  => 'Apc',
*   'benchmark'     => true
* );
*
* $server = new MQF_Rest_Controller_Simple($options);
* $server->run();
*
* </code>
*
* ".htaccess" should look like this:
*
* <code>
*
* RewriteEngine on
* RewriteBase /services
* RewriteRule !\.(js|ico|txt|gif|jpg|png|css)$ index.php
*
* </code>
*
*/
class MQF_Rest_Controller_Simple extends MQF_Rest_Controller
{
    /**
    *
    */
    public function __construct($options = array())
    {
        parent::__construct($options);
    }

    /**
    *
    */
    public function run()
    {
        if ($this->_ajaxonly and !$this->_request->isXmlHttpRequest()) {
            $this->_throwError("Can only be run via XmlHttpRequest");
        }

        if ($this->_benchmark) {
            $this->_timer->start();
        }

        $method = false;

        $parts = explode('/', $this->_request->getPathInfo());

        if (is_array($parts) and count($parts) > 1) {
            if ($parts[0] == '') {
                unset($parts[0]);
            }

            if (count($parts) == 1 and current($parts) == '') {
                $this->_throwError("No Class path given for Rest Service!");
            }

            if (!$parts[count($parts)-1]) {
                $this->_throwError("No Class path given for Rest Service!");
            }

            $this->_setupFromRequestVars();

            $rest_parameters = array();
            $has_class = false;

            $classparts = $parts;

            while (count($classparts)) {
                $class = implode('_', $classparts);

                if (@class_exists($class)) {
                    $has_class = true;
                    break;
                }

                $path = implode('/', $classparts).'.php';

                if (file_exists($path)) {
                    $has_class = true;
                    $class = implode('_', $classparts);
                    break;
                } else {
                    array_pop($classparts);
                }
            }

            if ($has_class) {
                $first_extra_arg = false;

                $diff = array_diff($parts, $classparts);
                reset($diff);
                if (count($diff)) {
                    $first_extra_arg = current($diff);
                }
            } else {
                $this->_throwError("Unable to execute service from request! No class found to represent the URL!");
            }

            if (!$first_extra_arg or $first_extra_arg == '') {
                $method = 'def';
            }

            if (isset($this->_reqparams['method'])) {
                $method = $this->_reqparams['method'];
            }

            $ref = new ReflectionClass($class);

            $this->_extractAnnotations($ref);

            $mp = null;

            $first_arg_is_method = false;

            if ($first_extra_arg and $ref->hasMethod($first_extra_arg)) {
                $first_arg_is_method = true;
            }

            $method_arg_is_method = false;

            if ($method and $ref->hasMethod($method)) {
                $method_arg_is_method = true;
            }

            $method_def_exists = false;

            if ($ref->hasMethod('def')) {
                $method_def_exists = true;
            }

            if (!$method_def_exists and !$method_arg_is_method and !$first_arg_is_method) {
                $this->_throwError("No method found to execute in class $class [$method]!");
            }

            if ($method_arg_is_method) {
                $use_all_extra_args = true;
            } else {
                $use_all_extra_args = false;
            }

            $first_parameter = false;

            if ($first_arg_is_method) {
                $method = array_shift($diff);
                $first_extra_arg = current($diff);
                $first_parameter = $first_extra_arg;
            }

            if (!$ref->hasMethod($method)) {
                if (!$ref->hasMethod('def')) {
                    $this->_throwError("Class '$class' has neither method '$method' or 'def'");
                }

                $first_parameter = $first_extra_arg;
                $method          = 'def';
            }

            if (!$method) {
                $this->_throwError("No method given.");
            }

            if (!$this->_isRestEnabled($method, $class)) {
                $this->_throwError("Method {$class}::{$method} is not correctly annotated for use by REST service.");
            }

            if ($first_parameter) {
                $mp = $ref->getMethod($method)->getParameters();

                if (isset($mp[0])) {
                    $mpname = $mp[0]->getName();

                    $_REQUEST[$mpname] = $first_parameter;
                }
            }

            $_REQUEST['method'] = $method;

            if (!$mp) {
                $mp = $ref->getMethod($method)->getParameters();
            }

            $req     = array();
            $unnamed = array();

            foreach ($_REQUEST as $key => $value) {
                if (substr(strtolower($key), 0, 3) == 'arg') {
                    $offset = substr($key, 3, strlen($key)-1);
                    if (is_numeric($offset)) {
                        $unnamed[$offset] = $value;
                    }
                } else {
                    $req[strtolower($key)] = $value;
                }
            }

            $cacheparams = array();

            $offset = 0;

            foreach ($mp as $p) {
                $name = strtolower($p->getName());

                if (!isset($req[$name])) {
                    if (isset($unnamed[$offset])) {
                        $_REQUEST[$name] = $unnamed[$offset];
                    } else {
                        if ($p->isDefaultValueAvailable()) {
                            $_REQUEST[$name] = $p->getDefaultValue();
                        }
                    }

                    unset($_REQUEST['arg'.$offset]);
                    $offset++;
                }
            }

            $cachekey = false;

            if ($this->_benchmark) {
                $this->_timer->setMarker('Parsed method');
            }

            if ($cachettl = $this->_isRestMethodCached($method, $class)) {
                $cachekey = md5("{$class}.{$method}.".serialize($cacheparams));

                $zendcache = Zend_Cache::factory('Core', $this->_cachebackend,
                                                array('lifetime'                => $cachettl,
                                                      'automatic_serialization' => true, )
                                                );

                if ($cachedata = $zendcache->load($cachekey)) {
                    if ($this->_benchmark) {
                        $this->_timer->stop();
                        $time = $this->_timer->timeElapsed('Parsed method');

                        MQF_Log::log("MQFDUR C: {time: \"".date('Y-m-d H:i:s')."\", method: \"".$class.'::'.$method."\", duration: \"".$time."\"}");
                    }

                    print $this->_serializeReturnvalue($cachedata);

                    return;
                }
            }

            $this->setClass($class);

            try {
                $response = $this->returnResponse(true)->handle();

                if ($this->_benchmark) {
                    $this->_timer->setMarker('Executed method'.$class.'::'.$method);

                    $time = $this->_timer->timeElapsed('Parsed method', 'Executed method'.$class.'::'.$method);

                    MQF_Log::log("MQFDUR R: {time: \"".date('Y-m-d H:i:s')."\", method: \"".$class.'::'.$method."\", duration: \"".$time."\", remote_address: \"".$_SERVER['REMOTE_ADDR']."\", server: \"".$_SERVER['SERVER_NAME']."\", url: \"".$this->_request->getRequestUri()."\"}");

                    $this->_timer->stop();
                }

                if ($cachettl) {
                    $zendcache->save($response, $cachekey, array($class, "{$class}_{$method}"));
                }

                print $this->_serializeReturnvalue($response);
            } catch (Exception $e) {
                $this->_throwError($e->getMessage(), true);
            }
        } else {
            $this->_throwError("No Class path given for Rest Service!");
        }
    }
}
