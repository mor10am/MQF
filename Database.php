<?php

require_once 'adodb/adodb-exceptions.inc.php';
require_once 'adodb/adodb.inc.php';

/**
 * ADODB uses class_exists to find an empty RecordSet.
 * To not trigger the autoloader, we make the class here.
 */
class ADORecordSet_ext_empty extends ADORecordSet_empty
{
}

/**
* Base class for all databases
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: Database.php 1131 2009-06-24 10:31:29Z attila $
*
*/
class MQF_Database
{
    const CLONE_ENC_NONE = 0;
    const CLONE_ENC_UTF8 = 1;
    const CLONE_ENC_ISO88591 = 2;

    protected $db = null;            ///< Instance of ADODB object
    protected $connectcount = 0;     ///< Number of times connected
    protected $connected = false;    ///< Connected?
    protected $id;                   ///< MQF_Database id
    protected $dsn;                  ///< Data Source Name
    protected $tables = array();     ///< Array of tables in database
    protected $columnmeta = array(); ///< Metadata for database
    protected $columnmetaauto = array(); ///< Metadata for database table autocreate
    protected $config = array();         ///< config array
    protected $dbenabled = true;     ///< Specifiy if we are taking connctions

    /**
    * \brief Constructor
    */
    public function __construct($id = false, $config = array())
    {
        if ($id and get_class($this) == 'MQF_Database') {
            $this->id = $id;
        } elseif (!$id) {
            $class = get_class($this);
            list($t, $id) = explode('_', $class);
            $this->id = $id;
        } else {
            $this->id = $id;
        }

        if (is_array($config) and count($config)) {
            $this->config = $config;
        }

        if (empty($this->config['debug'])) {
            $this->config['debug'] = false;
        }

        if (!$this->config['driver'] or !$this->config['database']) {
            throw new Exception("No connection parameters are specified for the database with id '$id'. Is 'driver' and 'database' present?");
        }

        $status = 'not-specified';

        $reg = MQF_Registry::instance();

        if ($reg->hasMQ()) {
            $mq = $reg->getMQ();

            $database = $this->config['database'];
            $dir      = dirname($database);

            if ($dir == '.') {
                $dir = '';
            }

            $file = basename($database);

            if (!$status = $reg->getConfigSetting('mqf', 'production_status')) {
                $status = 'production';
            }

            $status = strtolower(trim($status));

            if ($status != 'production' and $status != 'development') {
                throw new Exception("Legal values for mqf.production_status is 'production' and 'development'");
            }

            if ($status == 'development') {
                $file = 'dev_'.$file;
            }

            $database = '';

            if (strlen(trim($dir)) > 0) {
                $database = $dir.'/';
            }

            $database .= $file;

            $this->config['database'] = $database;
        }

        $this->db = AdoNewConnection($this->config['driver']);

        switch ($this->config['driver']) {
        case 'ibase':
        case 'firebird':
            if (isset($this->config['dateformat'])) {
                $this->db->ibase_datefmt = $this->config['dateformat'];
                $this->db->ibase_timefmt = $this->config['dateformat'];
            }
            break;
        default:
            break;
        }

        if (isset($this->config['metadata']) and is_array($this->config['metadata'])) {
            $metadata = $this->config['metadata'];
            unset($this->config['metadata']);

            foreach ($metadata as $table => $meta) {
                $this->columnmetaauto[strtoupper($table)] = $meta;
            }
        }

        if (isset($this->config['enabled'])) {
            $this->dbenabled = $this->config['enabled'];
        }

        $enablestring = ($this->dbenabled) ? "ON" : "OFF";

        $this->dsn = $this->config['driver'].'://'.$this->config['username'].':xxx@'.$this->config['host'].':'.$this->config['database'];

        MQF_Log::log("Database '{$this->config['database']}' is in mode ".$status." ({$enablestring})");
    }

    /**
    * \brief Destructor disconnects from database always
    */
    public function __destruct()
    {
        try {
            $this->disconnect(true);
        } catch (Exception $e) {
        }
    }

    /**
    * @desc
    */
    public function isEnabled()
    {
        return $this->dbenabled;
    }

    /**
     *
     */
    public function getTables()
    {
        if (count($this->tables) == 0) {
            $this->connect();
            $this->loadTables();
        }

        return $this->tables;
    }

    /**
    * \brief Load tables from database
    */
    protected function loadTables()
    {
        if (count($this->tables) != 0) {
            return true;
        }

        $cache = MQF_Registry::instance()->getCacheById('metadatatables', 86400);

        $id = md5($this->dsn);

        if (!$this->tables = $cache->get($id)) {
            $this->connect();
            $temp = $this->db->MetaTables('TABLES');

            if (count($temp)) {
                foreach ($temp as $t) {
                    $this->tables[strtoupper($t)] = $t;
                }
            }

            $cache->save($id, $this->tables);
        }

        if (is_array($this->tables)) {
            MQF_Log::log("Found tables: ".implode(',', $this->tables));
        } else {
            MQF_Log::log("Database '{$this->dsn}' has no tables");
        }

        MQF_Registry::instance()->setMarker("DB {$this->id} Load Tables");

        return true;
    }

    /**
    * \brief Get metadata for a table
    *
    * \param string table name
    *
    */
    public function getMetadataForTable($table)
    {
        $tabletmp = trim(strtoupper($table));

        if (isset($this->columnmeta[$tabletmp])) {
            return $this->columnmeta[$tabletmp];
        }

        $cache = MQF_Registry::instance()->getCacheById('metadatatable', 86400);

        $id = md5($this->dsn.$tabletmp);

        if (!$this->columnmeta[$tabletmp] = $cache->get($id)) {
            $this->loadTables();

            if (!is_array($this->tables) or !in_array($table, $this->tables)) {
                require_once 'MQF/Database/Exception/NoSuchTable.php';
                throw new MQF_Database_Exception_NoSuchTable("Table '{$table}' does not exist in database");
            }

            $this->connect();
            $this->columnmeta[$tabletmp] = $this->db->MetaColumns($table, true);

            MQF_Log::log("Fields in table $table: ".implode(',', array_keys($this->columnmeta[$tabletmp])));

            $pks = $this->db->MetaPrimaryKeys($table);

            if (is_array($pks) and count($pks)) {
                foreach ($pks as $pk) {
                    foreach ($this->columnmeta[$tabletmp] as $offset => $m) {
                        if ($m->name == $pk) {
                            $this->columnmeta[$tabletmp][$offset]->primarykey = 1;
                        }
                    }
                }
                MQF_Log::log("Primary keys for $table:".implode(',', $pks));
            } else {
                MQF_Log::log("No primary key(s) for $table defined");
            }

            $cache->save($id, $this->columnmeta[$tabletmp]);
        }

        MQF_Registry::instance()->setMarker("DB {$this->id} metadata '{$table}'");

        return $this->columnmeta[$tabletmp];
    }

    /**
    * \brief Connect to database
    *
    */
    public function connect($autocreate = false)
    {
        if (!$this->dbenabled) {
            $this->disconnect(true);
            throw new Exception("Database {$this->id} is disabled.");
        }

        if ($this->connected) {
            $this->connectcount++;

            return $this;
        }

        $debug = false;

        if (isset($this->config['debug'])) {
            $debug = $this->config['debug'];
        }

        $reg = MQF_Registry::instance();

        $this->db->Connect($this->config['host'], $this->config['username'], $this->config['password'], $this->config['database']);

        if (!is_resource($this->db->_connectionID)) {
            throw new Exception("Database '{$this->dsn}' is not connected: ".$this->db->_errorMsg);
        }

        $reg->setMarker("DB Connect {$this->id} [{$this->dsn}]");

        $this->db->debug = $debug;

        MQF_Log::log("Connected to database ".$this->getId()." with DSN '{$this->dsn}'", MQF_DEBUG);

        $this->connected = true;
        $this->connectcount = 1;

        if ($autocreate and count($this->columnmetaauto)) {
            foreach ($this->columnmetaauto as $table => $metadata) {
                try {
                    $this->getMetadataForTable($table);
                } catch (MQF_Database_Exception_NoSuchTable $e) {
                    if (!isset($this->columnmetaauto[$table])) {
                        throw new Exception("Metadata for table $table was not given");
                    }
                    if (!$this->db->Execute($metadata)) {
                        throw new Exception($this->db-ErrorMsg());
                    }
                } catch (Exception $e) {
                    throw $e;
                }
            }

            $reg->setMarker("AutoCreate tables {$this->id}");
        }

        return $this;
    }

    /**
    * \brief Disconnect from database
    *
    * \param bool Force closing of database
    *
    */
    public function disconnect($forceclose = false)
    {
        if ($this->db == null) {
            MQF_Log::log("No ADODB object to disconnect for ".$this->getId(), MQF_WARNING);
            $this->connected = false;
            $this->connectcount = 0;

            return true;
        }

        if (!$this->connected) {
            return true;
        }

        $this->connectcount--;

        if ($this->db->hasFailedTrans()) {
            $this->db->RollbackTrans();
            MQF_Log::log("Failed transaction on {$this->dsn} was rolled back", MQF_WARNING);
        }

        if ($this->connectcount <= 0 or $forceclose) {
            $this->db->Close();
            $this->connected = false;
            $this->connectcount = 0;
        }

        if ($forceclose) {
            MQF_Log::log("FORCE - Disconnected from ".$this->getId(), MQF_DEBUG);
        } else {
            MQF_Log::log("Disconnected from ".$this->getId()." CC=".$this->connectcount, MQF_DEBUG);
        }

        return true;
    }

    /**
    * \brief Check if database is connected
    *
    * \return boolean
    */
    public function isConnected()
    {
        if ($this->db == null) {
            return false;
        }

        return $this->db->IsConnected();
    }

    /**
    *
    */
    public function abortTransaction()
    {
        if ($this->isConnected()) {
            $this->db->CompleteTrans();
        }
    }

    /**
    *
    */
    public function __call($method, $args = array())
    {
        $umethod = strtoupper($method);

        if ((strstr($umethod, 'CONNECT')) !== false) {
            return $this->connect();
        }

        if ($umethod == 'CLOSE') {
            return $this->disconnect();
        }

        if (!method_exists($this->db, $method)) {
            throw new Exception("Method '$method' does not exist in ADODB!");
        }

        if (!$this->isConnected()) {
            throw new Exception("Database {$this->dsn} is not connected! [$method]");
        }

        $ret = call_user_func_array(array(&$this->db, $method), $args);

        MQF_Registry::instance()->setMarker("DB {$this->id}::{$method}");

        return $ret;
    }

    /**
    * \brief Execute query
    *
    * \param string SQL query
    * \param array Parameters
    * \param int Number of seconds to cache data. 0 = No cache
    */
    public function execute($query, $params = false, $cachetime = 0)
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $reg = MQF_Registry::instance();

        $marker = "begin {$this->id}: ".trim(substr($query, 0, 60)).' ['.mt_rand(1, 1000000).']';

        $reg->setMarker($marker, false);

        if ($cachetime == 0) {
            $rs = $this->db->Execute($query, $params);
        } else {
            $rs = $this->db->CacheExecute($cachetime, $query, $params);
        }

        $newmarker = "Time since {$marker}";

        $reg->setMarker($newmarker, false);

        if ($this->config['debug'] and $reg->timer) {
            $sec = $reg->timer->timeElapsed($marker, $newmarker);

            $perf = array();
            $perf['class']    = __CLASS__;
            $perf['system']   = $this->dsn;
            $perf['id']       = $this->id;
            $perf['method']   = $query;
            $perf['args']     = MQF_Tools::fixValue($params);
            $perf['cache']    = ($cachetime == 0) ? false : $cachetime;
            $perf['time']     = date('Y-m-d H:i:s');
            $perf['duration'] = round($sec, 4);

            MQF_Log::log('PERF: '.json_encode($perf));
        }

        return $rs;
    }

    /**
    * \brief Execute query and return record set as an array
    *
    * \param string SQL query
    * \param array Parameters
    * \param array Options
    * \return array
    */
    public function queryReturnArray($query, $params = false, $options = array())
    {
        global $ADODB_FETCH_MODE;

        if (!is_array($options)) {
            throw new Exception('Options not given as array! ['.gettype($options).']');
        }

        $mode = $ADODB_FETCH_MODE;

        if (isset($options['fetchmode'])) {
            $ADODB_FETCH_MODE = $options['fetchmode'];
        } else {
            $ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
        }

        $cachetime = 0;

        if (isset($options['cachetime'])) {
            $cachetime = $options['cachetime'];
        }

        $count = -1;

        if (isset($options['count'])) {
            $count = $options['count'];
        }

        $encoding = self::CLONE_ENC_NONE;

        if (isset($options['encoding'])) {
            $encoding = $options['encoding'];
            if ($encoding != self::CLONE_ENC_UTF8 and $encoding != self::CLONE_ENC_ISO88591) {
                $encoding = self::CLONE_ENC_NONE;
            }
        }

        try {
            $rs = $this->execute($query, $params, $cachetime);

            if ($count <= 0) {
                $count = -1;
            }

            $recs = $rs->getRows($count);

            if ($encoding) {
                $len = count($recs);

                switch ($encoding) {
                case self::CLONE_ENC_UTF8:
                    for ($i = 0; $i < $len; $i++) {
                        $recs[$i] = MQF_Tools::utf8($recs[$i]);
                    }

                    break;
                case self::CLONE_ENC_ISO88591:
                    for ($i = 0; $i < $len; $i++) {
                        $recs[$i] = MQF_Tools::iso88591($recs[$i]);
                    }

                    break;
                }
            }

            $ADODB_FETCH_MODE = $mode;

            $rs->Close();

            return $recs;
        } catch (Exception $e) {
            $ADODB_FETCH_MODE = $mode;
            throw $e;
        }
    }

    /**
    * \brief Get Id of last inserted row. Not all databases implement this
    *
    * \return int Id
    * \throw Exception
    */
    public function getLastInsertId()
    {
        if (!$id = $this->db->Insert_Id()) {
            throw new Exception("Method 'Insert_Id' is not supported!");
        }

        return $id;
    }

    /**
    * \brief Get database id
    *
    * \return   string  Id
    */
    public function getId()
    {
        return $this->id;
    }

    /**
    * \brief Convert ADOFetchObj to object of type stdClass
    *
    * \param ADOFetchObj Row object
    * \param int Type of encoding
    * \return stdClass
    */
    public static function adoToStd(ADOFetchObj $obj, $encoding = MQF_Database::CLONE_ENC_NONE)
    {
        $new = new StdClass();

        return self::adoFill($obj, $new, $encoding);
    }

    /**
    *
    */
    public static function adoFill(ADOFetchObj $obj, $new, $encoding = MQF_Database::CLONE_ENC_NONE)
    {
        if (!is_object($new)) {
            throw new Exception("An object to fill with properties was not given");
        }

        $tmp = get_object_vars($obj);
        foreach ($tmp as $field => $value) {
            if ($encoding == MQF_Database::CLONE_ENC_UTF8) {
                $value = MQF_Tools::UTF8($value);
            } elseif ($encoding == MQF_Database::CLONE_ENC_ISO88591) {
                $value = MQF_Tools::ISO88591($value);
            }
            $new->$field = $value;
        }

        return $new;
    }
}
