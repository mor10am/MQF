<?php

require_once 'MQF/Authentication/Interface.php';

/**
 * \class MQF_Authentication
 *
 * \author Morten Amundsen <mortena@tpn.no>
 * \author Ken-Roger Andersen <kenny@tpn.no>
 * \author Magnus Espeland <magg@tpn.no>
 * \author Gunnar Graver <gunnar.graver@teleperformance.no>
 * \remark Copyright 2006-2008 Teleperformance Norge AS
 * \version $Id: $
 *
 */

final class MQF_Authentication
{
    private $authlevel = 0;     ///< Authentication level
    private $idlist = null;     ///< List of mqfModule Ids for auth levels
    private $username;          ///< Username

    private $authorized = false;      ///< Is currently logged in?

    private $_timeout;
    private $_appid;
    private $_seed;
    private $_action;
    private $_referer;
    private $_cookiename;

    private $_auth = null;      ///< Authentication

    /**
     * \brief
     */
    public function __construct($auth)
    {
        if (!is_object($auth)) {
            throw new Exception("Decorator is not object!");
        }

        if (!in_array('MQF_Authentication_Interface', class_implements($auth))) {
            throw new Exception("Authentication decorator does not implement interface.");
        }

        $this->_auth  = $auth;
        $this->_appid = MQF_Registry::instance()->getConfigSetting('mqf', 'appkey');

        MQF_Log::log("Authentication is on for appkey {$this->appkey} with class ".get_class($auth));
    }

    /**
    *
    */
    public function authenticate($request, $action = false)
    {
        MQF_Registry::instance()->setMarker('Start Auth');

        $this->_cookiename = '__tpsec_'.$this->appid;

        if (isset($_REQUEST['__tpsecaction']) and strlen($_REQUEST['__tpsecaction'])) {
            $this->_action = strtolower($_REQUEST['__tpsecaction']);
        }

        $ajax = false;

        if (!$this->_action and $action) {
            $this->_action = strtolower(trim($action));

            $ajax = true;
        }

        if ($this->_action == 'logout') {
            $logout = true;
        } else {
            $logout = false;
        }

        if ($this->username and $this->auth and $this->_seed and !$logout and isset($_COOKIE[$this->_cookiename ])) {
            if (time() < $this->_timeout and $this->_timeout) {
                return true;
            }
        }

        $query = array();

        parse_str($_SERVER['QUERY_STRING'], $query);

        // Vi får vår egen URL tilbake påført __tpsecseed
        // Vi setter denne som COOKIE, og vi er deretter
        // autentisert.
        if (isset($query['__tpsecseed'])) {
            $this->_seed = $query['__tpsecseed'];
            setcookie($this->_cookiename, $this->_seed, time()+(60*60*24*365), '/');
        } elseif (isset($_COOKIE[$this->_cookiename])) {
            $this->_seed = $_COOKIE[$this->_cookiename];
        }

        if (!$this->_referer) {
            $protocol = (trim(strtolower($_SERVER['HTTPS'])) == 'on') ? 'https' : 'http';
            $this->_referer = urlencode($protocol.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
        }

        $obj = new stdClass();
        $obj->REFERER    = $this->_referer;
        $obj->REMOTEADDR = $_SERVER['REMOTE_ADDR'];
        $obj->SEED       = $this->_seed;
        $obj->APPID      = $this->_appid;
        $obj->ACTION     = $this->_action;

        $data     = base64_encode(serialize($obj));
        $redirect = false;

        $server = $this->_auth->getServer();

        $ret = @file_get_contents("{$server}?data=".$data."&_serialize=php");

        $response = unserialize($ret);

        if (is_array($response)) {
            $status = $response['authenticate']['status'];

            if (trim($status) == 'success') {
                $response = $response['authenticate'];
                $redirect = false;
            } else {
                $redirect = base64_decode($response['authenticate']['response']['message']);
            }
        } else {
            die("Authentication Server is down!");
        }

        // If there was a logout action, we destroy the cookie and the session
        if ($this->_action == 'logout') {
            if ($this->_cookiename) {
                unset($_COOKIE[$this->cookiename]);
            }

            @session_destroy();

            $this->authorized  = false;
            $this->authlevel   = 0;
            $this->username    = false;
            $this->idlist      = array();
            $this->_seed       = false;
            $this->_cookiename = false;
        }

        $action = $this->_action;

        $this->_action = false;

        // We have data from REST service and it does not tell us to redirect
        // so we return it to the client.
        if ($response and !$redirect and $action != 'logout') {
            if (!$response['USERNAME']) {
                MQF_Log::log("Unable to authorize. Service did not return USERNAME", MQF_ERROR);
                throw new Exception("Authorization failed!");
            }

            $this->_auth->registerResponse($response);

            if (($this->authlevel = $this->_auth->getAuthLevel()) === false) {
                MQF_Log::log("Unable to authorize. Unable to determine AuthLevel!", MQF_ERROR);
                throw new Exception("Authorization failed!");
            }

            MQF_Log::log("Current level is: ".$this->authlevel);

            $idlist = $this->_auth->getIdList();

            if (!$idlist or count($idlist) == 0) {
                MQF_Log::log("Unable to authorize. Unable to determine IdList!", MQF_ERROR);

                $this->authlevel = 0;

                throw new Exception("Authorization failed!");
            }

            $this->idlist   = $idlist;
            $this->username = $response->USERNAME;

            $this->authorized = true;

            MQF_Registry::instance()->setMarker('Stop Auth');

            return true;
        } else {
            // Redirect to the auth server

            if ($ajax) {
                return true;
            }

            header("Location: $redirect");
            exit;
        }
    }

    /**
     * \brief Check if mqfModule id is authorized for auth level in mqfAuthentication
     *
     * \param string id
     * \return bool
     */
    public function isModuleAuth($id)
    {
        if (!is_array($this->idlist) or count($this->idlist) == 0) {
            return false;
        }

        MQF_Log::log("Check '$id' for auth level '{$this->authlevel}': ".implode(',', $this->idlist));

        if (!is_array($this->idlist)) {
            MQF_Log::log("No modules defined for current authlevel '{$this->authlevel}'");

            return false;
        }

        if ($auth = in_array($id, $this->idlist)) {
            MQF_Log::log("$id is auth");
        } else {
            MQF_Log::log("$id is NOT auth");
        }

        return $auth;
    }

    /**
     * \fn public function getAuthLevel()
     * \return int The Authentication level
     *
     * Returns the authoriylevel for this object(username)
     */
    public function getAuthLevel()
    {
        return $this->authlevel;
    }

    /**
    *
    */
    public function getUsername()
    {
        return $this->username;
    }

    /**
    *
    */
    public function isAuth()
    {
        return $this->authorized;
    }

    /**
     * \brief Logout by clearing the auth
     */
    public function logout()
    {
        $this->authenticate($_SERVER['REQUEST_URI'], 'logout');
    }

    /**
     * \brief Fill list of mqfModule Ids for a given auth level
     */
    public function setIdList($level, $list)
    {
        if ($level == '') {
            $level = 0;
        }

        if (!is_array($list)) {
            $list = array($list);
        }

        if (!isset($this->idlist[$level]) or !is_array($this->idlist[$level])) {
            $this->idlist[$level] = array();
        }

        foreach ($list as $id) {
            if (!isset($this->idlist[$level][$id])) {
                $this->idlist[$level][$id] = $id;
            }
        }

        MQF_Log::log("Auth list for level '$level' is: ".implode(',', $this->idlist[$level]));

        return true;
    }

    /**
    *
    */
    public static function notAuthenticated()
    {
        die("Unable to authenticate application!");
    }
}
