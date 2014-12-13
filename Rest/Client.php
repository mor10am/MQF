<?php

require_once 'Zend/Http/Client.php';

/**
* \class MQF_Rest_Client
*
* \author Morten Amundsen <mortena@tpn.no>
* \remark Copyright 2006-2008 Teleperformance Norge AS
* \version $Id:$
*
*/
class MQF_Rest_Client
{
    /**
  * @desc
  */
  public function __construct($uri)
  {
      $this->uri = $uri;
  }

  /**
  * @desc
  */
  public function __call($method, $args = array())
  {
      $url = $this->uri;

      if (substr($url, -1) != '/') {
          $url .= '/';
      }
      $url .= $method;

      $url .= '?';

      $fixedargs = array();

      if (count($args)) {
          $i = 1;
          foreach ($args as $value) {
              $fixedargs[] = "arg{$i}=".urlencode($value);
              $i++;
          }
      }

      $fixedargs[] = "_serialize=php";

      $url .= implode('&', $fixedargs);

      $client = new Zend_Http_Client($url);

      $t = microtime(true);

      $response = $client->request();

      if (!$response->isSuccessful()) {
          throw new Exception($response->getStatus()." ".$response->getMessage());
      }

      $data = $response->getBody();
      $data = unserialize($data);

      if (is_object($data)) {
          $data = get_object_vars($data);
      }

      if (empty($data[$method])) {
          throw new Exception("Method $method did not return any data. Probably does not exist in service $this->uri");
      }

      $t = round((microtime(true) - $t) * 1000);

      $data[$method]['__mqf_rest_client_ms__'] = $t;

      return $data[$method];
  }
}
