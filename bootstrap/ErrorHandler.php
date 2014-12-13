<?php

function mqfErrorHandler($errno, $errstr, $errfile, $errline, $errcontext)
{
    global $GLOBAL_REQUEST_PARAMS;

    $er = ini_get('error_reporting');

    // Error reporting was suppressed or there was an E_NOTICE.
    if ($er == 0 or $errno == 8 or !($errno & $er)) {
        return true;
    }

    if (isset($GLOBAL_REQUEST_PARAMS['F']) && class_exists('MQF_Executor', false)) {
        $string = "$errstr <br/>\n $errfile at line $errline";
        throw new Exception($string);
    } elseif (function_exists('mqfBootstrapException')) {
        $string = "[$errno/$er] $errstr <br/>\n $errfile at line $errline";

        mqfBootstrapException(new Exception($string));
    } else {
        $string = "[$errno/$er] $errstr in $errfile at line $errline";

        die($string);
    }

    exit();
}
