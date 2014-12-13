<?php

require_once 'MQF/Rest/Controller.php';

/**
*
*/
class MQF_Rest_Controller_Fluent extends MQF_Rest_Controller
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

        $parts = explode('/', $this->_request->getPathInfo());

        if (is_array($parts) and count($parts) > 1) {
            if ($parts[0] == '') {
                unset($parts[0]);
            }

            if (count($parts) == 1 and current($parts) == '') {
                $this->_throwError("No Class path given for Rest Service!");
            }

            $this->_setupFromRequestVars();

            $orgparts = $parts;

            if (count($parts) == 1) {
                $_REQUEST['method'] = 'def';
                $method             = 'def';
            } else {
                if (isset($_REQUEST['method'])) {
                    $method = $_REQUEST['method'];
                } else {
                    $method = array_pop($parts);

                    $_REQUEST['method'] = $method;
                }
            }

            $rest_parameters = array();
            $has_class = false;

            $classparts = $parts;

            while (count($classparts)) {
                $class = implode('_', $classparts);

                if (class_exists($class)) {
                    $has_class = true;
                    break;
                }

                $path = implode('/', $classparts).'.php';

                if (file_exists($path)) {
                    $has_class = true;
                    break;
                } else {
                    array_pop($classparts);
                }
            }

            if ($has_class) {
                $extra_arguments = array_diff($orgparts, $classparts);
                reset($extra_arguments);
            } else {
                $this->_throwError("Unable to execute service from request. No class found to represent the URL!");
            }

            if (count($extra_arguments)) {
                $ref = new ReflectionClass($class);

                $this->setClass($class);

                MQF_Log::log("Reflect on class $class");

                $this->_extractAnnotations($ref);

                $m_chain = array();

                $first_arg_done = false;
                $current_method = false;

                while (count($extra_arguments)) {
                    $piece = array_shift($extra_arguments);

                    if ($ref->hasMethod($piece)) {
                        if (!$current_method) {
                            $current_method = $piece;
                        }
                        $m_chain[$current_method]['name'] = $piece;
                        MQF_Log::log("add method $piece");
                    } else {
                        if (!$first_arg_done) {
                            if (!$current_method) {
                                $current_method = 'def';
                            }
                            $m_chain[$current_method]['name'] = 'def';
                            MQF_Log::log("add method def");
                        }

                        $m_chain[$current_method]['params'][] = $piece;
                        MQF_Log::log("add param $piece to method $current_method");
                    }

                    $first_arg_done = true;
                }
            }

            MQF_Registry::instance()->setMarker("REST server for class $class setup done");

            if (count($m_chain)) {
                try {
                    $restobj = new $class();

                    foreach ($m_chain as $m) {
                        if (!$this->_isRestEnabled($m['name'], $class)) {
                            $this->_throwError("Method {$class}::{$m['name']} is not correctly annotated for use by REST service {$this->_request->getMethod()}.");
                        }

                        if ($first_pass_done) {
                            $params = array_merge($m['params'], array($restobj));
                        } else {
                            $params = $m['params'];
                            $first_pass_done = true;
                        }

                        if (!is_object($restobj)) {
                            $this->_throwError("Is not an object!");
                        }

                        MQF_Log::log("Trying method {$m['name']}");

                        if ($cachettl = $this->_isRestMethodCached($m['name'], $class)) {
                            $cachekey = md5("{$class}.{$m['name']}.".serialize($params));

                            $zendcache = Zend_Cache::factory('Core', 'File', array('lifetime' => $cachettl,
                                                                                    'automatic_serialization' => true, ),
                                                                            array('cache_dir' => '/tmp'));

                            if ($restobj = $zendcache->load($cachekey)) {
                                MQF_Log::log("Cache hit for method {$m['name']}");
                                continue;
                            }
                        }

                        $restobj = call_user_func_array(array($restobj, $m['name']), $params);

                        if ($cachettl) {
                            $zendcache->save($restobj, $cachekey, array($class, "{$class}_{$m['name']}"));
                        }
                    }
                } catch (Exception $e) {
                    $this->_throwError($e->getMessage(), true);
                }
            } else {
                $this->_throwError("No methods found to execute in class $class!");
            }

            MQF_Registry::instance()->setMarker("REST call done");

            $this->_method = $current_method;

            print $this->_serializeReturnvalue($this->_transformResult($restobj));
        } else {
            $this->_throwError("No Class path given for Rest Service!");
        }
    }
}
