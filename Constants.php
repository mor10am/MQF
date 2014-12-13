<?php

if (!defined('_MQF_CONSTANTS_LOADED')) {
    define('_MQF_CONSTANTS_LOADED', 1);
}

define('MQF_THROW_EXCEPTION', 1);
define('MQF_DONT_THROW_EXCEPTION', 2);
define('MQF_OVERWRITE', 3);
define('MQF_DONT_OVERWRITE', 4);

define('MQF_LOGLEVEL_DEFAULT', MQF_DEBUG);

define('MQF_TEMPLATE_ENGINE_MODE_CURLY', 0); /// \brief Curly brackets { } (normal smarty)
define('MQF_TEMPLATE_ENGINE_MODE_TT2', 1); /// \brief Square brackets with percent sign [% %] (good for javascript, code templates and css)


define('MQF_CLIENT_TCP_PORT', 8090);         ///< TCP port on client side
define('MQF_CLIENT_TCP_TIMEOUT_CONNECT', 5); ///< Timeout during tcp connect (seconds)
define('MQF_CLIENT_TCP_TIMEOUT_SECS', 10);    ///< Timeout for tcp stream (seconds) (total timeout is sec+ms)
define('MQF_CLIENT_TCP_TIMEOUT_MS', 0);    ///< Timeout for tcp stream (milli-seconds) (total timeout is sec+ms)


define('MQF_DIALER_CTCTPNSYSTEM', 1);
define('MQF_DIALER_ESHARE', 2);

define('SESSION_ID_SOURCE_COOKIE', 1);
define('SESSION_ID_SOURCE_PARAMETER', 2);
define('SESSION_ID_SOURCE_REQUEST', 3);
define('SESSION_ID_SOURCE_REGENERATE', 4);

define('DIALER_PREFIX_MODE_HIDE', 1); ///< How do we handle prefixes?
;
