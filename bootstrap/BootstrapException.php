<?php
/**
 *
 * Process exceptions thrown before the regular exception handling has started
 *
 */

function mqfBootstrapException($e)
{
    if (isset($_SERVER['HTTP_HOST']) and isset($_SERVER['REQUEST_METHOD'])) {
        $execmode = 'http';
    } elseif (isset($_SERVER['SHELL'])) {
        $execmode = 'console';
    } else {
        $execmode = 'console';
    }

    if ($execmode == 'console') {
        die($e->getMessage()."\n");
    }

    echo formatGuruMeditationException($e);

    try {
        if (class_exists('MQF_Log', false)) { // false means "don't autoload class"
            MQF_Log::log("Bootstrap exception: ".$e->getMessage()."\n".print_r($e, true), MQF_FATAL);
        }
    } catch (Exception $e) {
        // no need to throw this, and it's wrong anyway
    }

    die();
}

/**
 *
 * Make nice Guru Meditation error
 *
 */

function formatGuruMeditationException($e)
{
    return "
        <html>
        <head>
        <title>Guru Meditation</title>


        <style>
        .wrap{
            width: 67%;
            margin: 0 0 1em 14%;
            padding: 2% 3% 2% 5%;
            BACKGROUND: white;
            border-style: solid;
            border-width: 1px;
            top: -1px;
        }
        h2,h3,h4{
            position: relative;
            clear: both;
        }
        h1 {
            position: relative;
            font-size: 14pt;
            clear: both;
        }
        body {
            margin: 0;
            padding: 0;
            background: white;
            //font: 0.8em trebuchet ms;
        }
        em {
            background: #ffc;
        }
        pre {
            font-size: 10pt;
            background: #f0f0f0;
            -moz-border-radius: 10px;
            padding: 1em;
        }
        pre span {
            font-weight: bold;
        }
        .selector, pre b {
            color: red;
        }

        .beh {
            color: blue;
        }
        .event{
            color: green;
        }

        #sortable-list li{
            cursor:move;
            -moz-user-select: none;
            width: 100%;
        }

        .dropout li{
            width: 100px;
            line-height: 100px;
            text-align: center;
            float: left;
            margin: 5px;
            border: 1px solid #ccc;
            list-style: none;
            background: red;
        }

        .dropout li.hover{
            background: yellow;
        }

    #mw_logger
    {
    }

    #mw_log
    {
        border: 8px solid #ff0000;
        padding: 8px;
        visibility:visible;
        background-color: black ;
    }

    #mw_log_border
    {
        border: 8px solid black;
        visibility:visible;
        background-color: black ;
    }

    #mw_log_content
    {
    }


    #mw_log_logo
    {
        font-weight:bold;
        color: #FF0000;
        border-color:Black;
        text-align:center;
    }

    #mw_log *
    {
        background-repeat: no-repeat;
        //font-size: 9pt;
        //font-family:Courier New;
        margin: 0;
        padding: 0;
    }

    #mw_log p, #mw_log h6, #mw_log ul
    {
        text-align: left;
    }

    #mw_log p
    {
        margin-left: 3px;
        padding-left: 20px;
        background-position: top left;
        line-height: 1.4em;
    }

    #mw_log a
    {
        font-weight:bold;
        margin-left: 3px;
        padding-left: 20px;
    }

    #mw_log table
    {
        width:90%;
        text-align:center;
        font-size: 10pt;
    }

        </style>

        <script>
            setInterval(blinkBorder, 1000);

            function blinkBorder()
            {
                var id = document.getElementById('mw_log');

                if (id.style.borderColor == 'black black black black') {
                    id.style.borderColor = 'red red red red';
                } else {
                    id.style.borderColor = 'black black black black';
                }
            }
        </script>

        </head>
        <body>

        <div id='mw_log_border'>
        <div id='mw_log'>
        <table id='mw_log_table'>
            <tr width='100%'>
                <td id='mw_log_logo'>
                    <h1>Guru Meditation #30000001</h1><br/>
                    '".$e->getMessage()."'
                    <br/>
                    In file ".basename($e->getFile()).
                    " at line ".$e->getLine().
                "</td>
            </tr>
        </table>
        </div>
        </div>


        <pre>".print_r($e, true)."</pre>

        </body>
        </html>";
}
