<?php

/**
*
*/
abstract class MQF_ESB_Handler
{
    abstract public function handle(MQF_ESB_Connector_Stomp_Frame $frame);
}
