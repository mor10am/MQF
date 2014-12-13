<?php

interface MQF_Authentication_Interface
{
    public function getAuthLevel();
    public function getIdList();
    public function getServer();
    public function registerResponse($response);
}
