<?php

/**
* MQF Bootstrapper
*
* Make sure php.ini has "include_path" to the following applications
* Smarty, ADODB, Zend Framework
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \d $Id: bootstrap.php 992 2008-02-26 14:53:22Z mortena $
*
*/

/*

Config filename = config.ini

EXAMPLE:

[mqf]
status = production|development
mqenabled = true|false
authclass = subclass of MQF_Authentication (this framework is not done)

appkey = <any key>
company = <company name>
project = <project id>


cache_enable = false|true
cache_timeout = in seconds


js_baseurl = <base url path of MQF javascripts>
js_cache = true|false

[mqdatabase]
driver =
host =
db =
username =
password =
debug = true|false

[session]
session_timeout = <in seconds>
session_id_source = (1=cookie, 2=parameter, 3=request, 4=regenerate always)

[gui]
jsexeptionmethod = mqf_full_exception
canvas = <sub class of MQF_Canvas>

[dialer]
prefix_mode = 1
calldata_debug = true|false

[logging]
loglevel = (-1 = all)
log_profiling = true|false

[dialer]
prefix_mode = (1 or 0)

[webservices]

[mqx_javascripts]


*/

// ######################
// ###                ###
// ###   SET PATHS    ###
// ###                ###
// ######################

if (isset($_SERVER['PWD'])) {
    chdir($_SERVER['PWD']);
}

define('MQF_APPLICATION_PATH', dirname($_SERVER["SCRIPT_FILENAME"]));
define('MQF_APPLICATION_TEMPLATES', dirname($_SERVER["SCRIPT_FILENAME"]).'/templates');
define('MQF_APPLICATION_TEMPLATES_C', dirname($_SERVER["SCRIPT_FILENAME"]).'/templates_c');

require_once 'Zend/Loader.php';
require_once 'Zend/Version.php';

require_once 'Benchmark/Timer.php';
require_once 'OS/Guess.php';

$os = new OS_Guess();

if ($os->getSysname() == 'windows') {
    define('MQF_INCPATH_SEP', ';');
} else {
    define('MQF_INCPATH_SEP', ':');
}

ini_set('include_path', MQF_APPLICATION_PATH.MQF_INCPATH_SEP.ini_get('include_path'));

Zend_Loader::registerAutoload();

// Import error handler
require_once 'MQF/bootstrap/ErrorHandler.php';

// set error handler to ours
set_error_handler('mqfErrorHandler');

// ######################
// ###                ###
// ### IMPORT CLASSES ###
// ###                ###
// ######################
// import the classes we are going to use, no matter what
// everything we import here helps APC
// We should try to make all of the bootstrapping stuff imported


// Import exception handler
require_once 'MQF/bootstrap/BootstrapException.php';

require_once 'MQF/Constants.php';
require_once 'MQF/Registry.php';
require_once 'MQF/Log.php';
require_once 'MQF/Controller/Front.php';
require_once 'MQF/Executor.php';
require_once 'MQF/UI.php';
require_once 'MQF/MultiQueue.php';
require_once 'MQF/UI/Module.php';
require_once 'MQF/UI/Canvas.php';

if (Zend_Version::compareVersion('1.0.0') == 1) {
    die("Zend Framework 1.0+ is required for MQF.\n");
}

if (file_exists(MQF_APPLICATION_PATH.'/includes.php')) {
    @require_once MQF_APPLICATION_PATH.'/includes.php';
}

MQF_Registry::instance()->setValue('os', $os);

// ######################
// ###                ###
// ###    EXECUTE     ###
// ###                ###
// ######################


try {
    $controller = MQF_Controller_Front::instance();

    $controller->run();

    exit;
} catch (Exception $e) {
    $reg = MQF_Registry::instance();

    if ($reg->getValue('execmode') == 'console') {
        print $e->getMessage();
        print "\n\n";
        print_r($e);
    } else {
        if (isset($GLOBAL_REQUEST_PARAMS['F']) and class_exists('MQF_Executor', false) and $controller->isAjax()) {
            header('Content-Type: text/xml');
            print MQF_Executor::getExceptionXML(MQF_Tools::convertExceptionToStdClass($e), 0, 'mqfFrontControllerFailure');
        } else {
            mqfBootstrapException($e);
        }
    }
}
