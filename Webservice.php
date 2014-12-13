<?php

/**
 * \class MQF_Webservice
 *
 * \author Morten Amundsen <mortena@tpn.no>
 * \author Ken-Roger Andersen <kenny@tpn.no>
 * \author Magnus Espeland <magg@tpn.no>
 * \author Gunnar Graver <gunnar.graver@teleperformance.no>
 * \remark Copyright 2006-2007 Teleperformance Norge AS
 * \version $Id: Webservice.php 1127 2009-04-29 14:56:57Z mortena $
 *
 */
class MQF_Webservice
{
    const METHOD_CACHE_TTL = 60;        ///< Default method cache Time to live

    protected $wsclient = null;         ///< SoapClient object
    protected $id;                      ///< Id
    protected $internalws = false;      ///< Is WS internal to TP?
    protected $trace = false;           ///< Use tracing?
    protected $cachemethods = array();  ///< Array of methods to cache (array('method' => methodname, 'ttl' => ttl))
    protected $systemstring;

    private static $_instances = array();

    /**
    * \brief Constructor
    */
    public function __construct($endpoint = null, $options = array())
    {
        if (!is_array($options)) {
            $options = array();
        }

        $options['exceptions'] = true;

        if (isset($options['no-cache']) and !$options['no-cache']) {
            ini_set('soap.wsdl_cache_enabled', 0);
        } else {
            ini_set('soap.wsdl_cache_enabled', 1);
        }

        if (isset($options['trace'])) {
            $this->trace = $options['trace'];
        }

        if (isset($options['cachemethods']) and is_array($options['cachemethods'])) {
            foreach ($options['cachemethods'] as $ma) {
                if (is_array($ma) and isset($ma['method'])) {
                    $m = trim(strtolower($ma['method']));

                    if (isset($ma['ttl']) and $ma['ttl'] > 0) {
                        $ttl = $ma['ttl'];
                    } else {
                        $ttl = MQF_Webservice::METHOD_CACHE_TTL;
                    }

                    $this->cachemethods[$m] = array('method' => $m, 'ttl' => $ttl);
                }
            }
        }

        $class = get_class($this);

        $tmp = explode('_', $class);

        if (count($tmp) >= 2) {
            $this->id = $tmp[count($tmp)-1];
        }

        if (isset($options['mqfinternal'])) {
            $this->internalws = $options['mqfinternal'];
            unset($options['mqfinternal']);
        }

        if (isset($options['mqfwsid'])) {
            if (!$this->id) {
                $this->id = $options['mqfwsid'];
            }
            unset($options['mqfwsid']);
        } elseif (!$this->id) {
            $this->id = $endpoint;
        }

        if (!$this->id) {
            throw new Exception("This webservice has no Id. [{$endpoint}]");
        }

        if (!isset($options['endpoint_is_wsdl']) or !$options['endpoint_is_wsdl']) {
            $wsdl = null;

            if (substr(strtoupper($endpoint), -4) == 'WSDL') {
                $wsdl = $endpoint;
            } else {
                $options['location'] = $endpoint;
                $options['uri']      = dirname($endpoint);
            }
        } else {
            $wsdl = $endpoint;
        }

        $this->systemstring = $endpoint;

        try {
            if ($this->trace) {
                MQF_Log::log("WSDL = ".$wsdl);
                MQF_Log::log("Options = \n".print_r($options, true));
            }

            $this->wsclient = new SoapClient($wsdl, $options);

            if (!$this->wsclient) {
                throw new Exception("Unable to create SOAPClient for endpoint: $endpoint");
            }
        } catch (Exception $e) {
            MQF_Log::log($e->getMessage());
            if ($this->trace) {
                MQF_Log::log(print_r($e, true));
            }
            throw $e;
        }

        MQF_Log::log("Created WebService '{$this->id}' with WSDL/URI '{$endpoint}'");
    }

    /**
    *
    */
    public static function factory($endpoint = null, $options = array())
    {
        if (!self::$_instances[$endpoint]) {
            self::$_instances[$endpoint] = new self($endpoint, $options);
        }

        return self::$_instances[$endpoint];
    }

    /**
    * \brief Get WebService Id
    *
    * \return   string  Id
    */
    public function getId()
    {
        return $this->id;
    }

    /**
    * \brief Call method in webservice
    */
    public function __call($method, $arguments)
    {
        $reg = MQF_Registry::instance();

        if ($this->internalws) {
            $appkey = $reg->getConfigSettingDefault('mqf', 'appkey', MQF_MultiQueue::CONFIG_VALUE, 'MQF');

            $arguments = array_merge(array($appkey), $arguments);
        }

        try {
            $markerstring = 'begin '.$this->id.'::'.$method.' ['.mt_rand(1, 10000000).']';

            $reg->setMarker($markerstring, false);

            $use_cache = false;
            $m         = trim(strtolower($method));

            if (isset($this->cachemethods[$m])) {
                $cache = new MQF_Cache($this->cachemethods[$m]['ttl']);

                $use_cache = true;
                $cacheid   = $cache->genKey($method, $arguments);

                if ($ret = $cache->get($cacheid)) {
                    $duration = $reg->logTimeSinceMarker($markerstring, "Method '$method' in service '{$this->id}' Cache HIT");

                    if ($duration !== false) {
                        $perf = array();
                        $perf['class']    = __CLASS__;
                        $perf['system']   = $this->systemstring;
                        $perf['id']       = $this->id;
                        $perf['method']   = $method;
                        $perf['args']     = MQF_Tools::fixValue($arguments);
                        $perf['cache']    = true;
                        $perf['time']     = date('Y-m-d H:i:s');
                        $perf['duration'] = $duration;
                        MQF_Log::log('PERF: '.json_encode($perf));
                    }

                    return  $ret;
                } else {
                    MQF_Log::log("Method '$method' in service '{$this->id}' Cache MISS");
                }
            }

            MQF_Log::log("Trying {$this->id}::{$method} with args ".MQF_Tools::fixValue($arguments));

            $ret = call_user_func_array(array($this->wsclient, $method), MQF_Tools::utf8($arguments));

            $newmarker = "Time since {$markerstring}";

            $reg->setMarker($newmarker, false);

            if ($use_cache and $cacheid) {
                $cache->save($cacheid, $ret);
                MQF_Log::log("Method '$method' in service '{$this->id}' Cache SAVE for ".$this->cachemethods[$m]['ttl']." seconds");
            }
        } catch (Exception $e) {
            MQF_Log::log($e->getMessage(), MQF_WARN);

            if ($this->trace) {
                MQF_Log::log("\nREQUEST HEADERS:\n".$this->wsclient->__getLastRequestHeaders()."\n--------------------------------", MQF_DEBUG);
                MQF_Log::log("\nRESPONSE HEADERS:\n".$this->wsclient->__getLastResponseHeaders()."\n--------------------------------", MQF_DEBUG);
                MQF_Log::log("\nRESPONSE:\n".$this->wsclient->__getLastResponse()."\n--------------------------------", MQF_DEBUG);
            }

            if ($e->getMessage() != 'looks like we got no XML document') {
                throw new Exception("Error calling {$method}: ".$e->getMessage());
            } else {
                throw new Exception("looks like we got no XML document, respone follows:\n==== BEGIN RESPONSE ====\n".$this->wsclient->__getLastResponse()."\n==== END RESPONSE ====\n");
            }
        }

        $duration = $reg->logTimeSinceMarker($markerstring, "{$this->id}::{$method}(".MQF_Tools::fixValue($arguments).")");

        if ($duration !== false) {
            $perf = array();
            $perf['class']    = __CLASS__;
            $perf['system']   = $this->systemstring;
            $perf['id']       = $this->id;
            $perf['method']   = $method;
            $perf['args']     = MQF_Tools::fixValue($arguments);
            $perf['cache']    = false;
            $perf['time']     = date('Y-m-d H:i:s');
            $perf['duration'] = $duration;

            MQF_Log::log('PERF: '.json_encode($perf));
        }

        if ($this->trace) {
            MQF_Log::log("\nREQUEST HEADERS:\n".$this->wsclient->__getLastRequestHeaders()."\n--------------------------------", MQF_DEBUG);
            MQF_Log::log("\nRESPONSE HEADERS:\n".$this->wsclient->__getLastResponseHeaders()."\n--------------------------------", MQF_DEBUG);
            MQF_Log::log("\nRESPONSE:\n".$this->wsclient->__getLastResponse()."\n--------------------------------", MQF_DEBUG);
        }

        return $ret;
    }
}
