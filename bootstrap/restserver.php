<?php

/**
* MultiQueue Rest Server bootstrapper
*
* 1. Make a symlink to this file in your Rest-server folder.
* 2. Make a new folder 'Rest'.
* 3. Your Rest class should be on this form 'class MQF_Rest_Name1_Name2_Name3', where Name(n) will be folders.
*
* Apache Rule:
*        RewriteEngine on
*        RewriteRule !\.(js|ico|gif|jpg|png|css)$ index.php
*/

ini_set('include_path', ini_get('include_path').':'.dirname($_SERVER['SCRIPT_FILENAME']));

require_once 'Zend/Loader.php';
require_once 'Zend/Version.php';

require_once 'Benchmark/Timer.php';

Zend_Loader::registerAutoload();

require 'MQF/Log.php';

require 'Zend/Controller/Request/Http.php';
require 'Zend/Rest/Server.php';

$request = new Zend_Controller_Request_Http();
$request->setBaseUrl();

$params = $request->getParams();

$parts = explode('/', $request->getPathInfo());

$rest = new Zend_Rest_Server();

if (is_array($parts) and count($parts) > 1) {
    if ($parts[0] == '') {
        unset($parts[0]);
    }

    if (isset($_REQUEST['rest'])) {
        unset($_REQUEST['rest']);
        unset($params['rest']);
    }

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

    if ($parts[count($parts)-1] == '') {
        throw new Exception("Unknown class!");
    }

    $class = 'MQF_Rest_'.implode('_', $parts);

    $ref = new ReflectionClass($class);

    $mp = null;

    if (!$ref->hasMethod($method)) {
        if (!$ref->hasMethod('def')) {
            header("Content-Type: text/xml");
            die($rest->fault(new Exception("Class '$class' has neither method '$method' or 'def'"))->saveXML());
        }

        $first_parameter    = $method;
        $method             = 'def';
        $_REQUEST['method'] = 'def';

        $mp = $ref->getMethod($method)->getParameters();

        if (isset($mp[0])) {
            $mpname = $mp[0]->getName();

            $_REQUEST[$mpname] = $first_parameter;
        }
    }

    if (!$mp) {
        $mp = $ref->getMethod($method)->getParameters();
    }

    $req = array();

    foreach ($_REQUEST as $key => $value) {
        $req[strtolower($key)] = $value;
    }

    foreach ($mp as $p) {
        $name = strtolower($p->getName());

        if (!isset($req[$name])) {
            if ($p->isDefaultValueAvailable()) {
                $_REQUEST[$name] = $p->getDefaultValue();
            }
        }
    }
} else {
    header("Content-Type: text/xml");
    die($rest->fault(new Exception("Illegal Rest path: ".$request->getPathInfo()))->saveXML());
}

if (!$method) {
    header("Content-Type: text/xml");
    die($rest->fault(new Exception("No method given."))->saveXML());
}

$rest->setClass($class);

try {
    header("Content-Type: text/xml");

    $response = $rest->returnResponse(true)->handle();

    print $response;
} catch (Exception $e) {
    ob_end_clean();

    header('text/xml');
    die($rest->fault(new Exception($e->getMessage()))->saveXML());
}

MQF_Log::log("Calling {$class}::{$method}(".implode(',', $params).')');
