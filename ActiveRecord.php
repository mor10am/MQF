<?php

require_once 'MQF/Database.php';

/**
* \class MQF_ActiveRecord
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: ActiveRecord.php 1084 2008-10-02 17:16:11Z mortena $
*
*/
class MQF_ActiveRecord
{
    protected $metadata = array();      ///< Table metadata
    protected $table;                   ///< Name of table
    protected $primarykeys = array();   ///< Array of primarykeys
    protected $dbid;                    ///< MQF_Database Id

    protected $pkautoinc = false;
    protected $recdel    = false;

    protected $dirty   = false;          ///< Shows if any field in the table has been changed
    protected $queries = array();        ///< Cache of already created queries
    protected $record  = null;           ///< Fields and values of this ActiveRecord
    protected $lastrec = null;           ///< Keeps track of changes

    protected $options;                 ///< Options given
    protected $viewonly = false;        ///< Tag if this ActiveRecord can be modified or not

    private $_signals = array();

    const VALUES_AS_ARRAY = 0;
    const VALUES_AS_OBJECT = 1;
    const VALUES_AS_JSON = 2;
    const VALUES_AS_XML = 3;

    /**
    * \brief Create a new ActiveRecord
    *
    * \param    string      $dbid       Id of MQF_Database
    * \param    string      $table      Databasetable that this class is using
    * \param    string      $options
    */
    public function __construct($dbid, $table = '', $options = array())
    {
        $this->dbid    = $dbid;
        $this->record  = new stdClass();
        $this->lastrec = new stdClass();
        $this->options = $options;

        if ($table) {
            $this->table = $table;
        } else {
            $this->table = strtolower(get_class($this));
        }

        if (isset($options['metadata']) and count($options['metadata'])) {
            $this->metadata = $options['metadata'];

            foreach ($metadata as $m) {
                if (isset($m['primarykey']) and $m['primarykey'] == 1) {
                    $this->primarykeys[strtoupper($m['field'])] = trim(strtoupper($m['field']));
                }
            }
        } else {
            $this->_buildMetaData();
        }

        if (count($this->primarykeys) == 0 and isset($options['primarykeys']) and count($options['primarykeys']) > 0) {
            foreach ($options['primarykeys'] as $pk) {
                $this->primarykeys[strtoupper($pk)] = trim(strtoupper($pk));
            }
        }

        if (count($this->primarykeys) == 0) {
            throw new Exception("Table {$this->table} has no primarykeys!");
        }

        // Initialize record with default values
        foreach ($this->metadata as $m) {
            $f = trim(strtoupper($m['field']));
            $this->record->$f  = $m['default'];
            $this->lastrec->$f = $m['default'];
        }

        if (isset($options['pk-is-autoinc'])) {
            $this->pkautoinc = $options['pk-is-autoinc'];

            if ($this->pkautoinc and count($this->primarykeys) != 1) {
                throw new Exception("When primarykey is auto-increment, there must be only one (1) PK");
            }
        }

        // Run event
        $this->onCreate();

        $this->dirty = true;
    }

    /**
    * \brief Destructor will always try to save data if dirty
    *
    */
    public function __destruct()
    {
        try {
            $this->save();
        } catch (Exception $e) {
        }
    }

    /**
    *
    */
    public function attachSignal($field, $type, $callback)
    {
        $type = strtoupper(trim($type));
        $field = strtoupper(trim($field));

        if (!$this->hasFields($field)) {
            throw new Exception("Unable to add signal to unknown field $field");
        }

        if (!in_array($type, array('CHANGE', 'CLEAR', 'GETFILTER'))) {
            throw new Exception("Unknown signal type $type");
        }

        if (!is_array($callback) or count($callback) != 2) {
            throw new Exception("Callback should be an array containing object and method");
        }

        if (!is_array($this->_signals[$type][$field])) {
            $this->_signals[$type][$field] = array();
        }

        if ($type == 'GETFILTER') {
            if (count($this->_signals[$type][$field]) == 1) {
                throw new Exception("Can only add one (1) GETFILTER per field!");
            }
        }

        $this->_signals[$type][$field][] = $callback;
    }

    /**
    *
    */
    protected function signal($field, $value, $type)
    {
        if ($type == 'GETFILTER') {
            $retval = $value;
        } else {
            $retval = true;
        }

        if (!isset($this->_signals[$type][$field])) {
            return $retval;
        }
        if (count($this->_signals[$type][$field]) == 0) {
            return $retval;
        }

        for ($i = 0; $i < count($this->_signals[$type][$field]); $i++) {
            $retval = call_user_func_array($this->_signals[$type][$field][$i], array($value));
        }

        if ($type == 'GETFILTER') {
            return $retval;
        } else {
            return true;
        }
    }

    /**
    * \brief Get the registered MQF_Database ID for this Active Record
    *
    * \return   string  MQF_Database Id
    */
    public function getDBID()
    {
        return $this->dbid;
    }

    /**
    * \brief Get options array
    *
    * \return   array   Options
    *
    */
    public function getOptions()
    {
        return $this->options;
    }

    /**
    * \brief Build metadata for this table
    *
    */
    private function _buildMetaData()
    {
        $metadata = MQF_Registry::instance()->getDatabaseById($this->dbid)->getMetaDataForTable($this->table);

        $this->primarykeys = array();
        $this->metadata    = array();

        foreach ($metadata as $m) {
            $m->name = trim(strtoupper($m->name));

            $this->metadata[$m->name]['field']    = $m->name;
            $this->metadata[$m->name]['datatype'] = $m->type;
            $this->metadata[$m->name]['size']     = $m->max_length;

            if (isset($m->has_default) and $m->has_default == 1) {
                $this->metadata[$m->name]['default'] = $m->default_value;
            } else {
                $this->metadata[$m->name]['default'] = null;
            }

            if (isset($m->primarykey) and $m->primarykey == 1) {
                $this->primarykeys[$m->name] = $m->name;
            }
        }
    }

    /**
    * \brief Check if fields are in table
    *
    * \param    array   Array of fieldnames
    * \return   bool
    *
    */
    public function hasFields($fields)
    {
        if (!is_array($fields)) {
            $fields = array($fields);
        }

        $r = get_object_vars($this->record);
        $r = array_keys($r);

        foreach ($fields as $f) {
            $f = strtoupper($f);
            if (!in_array($f, $r)) {
                return false;
            }
        }

        return true;
    }

    /**
    * \brief Get current state of record
    *
    * \return   stdClass    Active Record data
    */
    public function extract()
    {
        $o = clone $this->record;

        return $o;
    }

    /**
    * \brief Clear the state of this Active Record
    */
    private function _reset()
    {
        $this->save();

        foreach ($this->record as $field => $value) {
            $this->record->$field  = null;
            $this->lastrec->$field = null;
        }
    }

    /**
    * \brief Apply data to this ActiveRecord
    *
    */
    private function _apply($fo)
    {
        if (is_object($fo)) {
            $fo = get_object_vars($fo);
        }

        if (!is_array($fo)) {
            throw new Exception("Input is not array!");
        }

        foreach ($fo as $f => $value) {
            if (is_numeric($f)) {
                continue;
            }

            $this->$f = $value;
            $this->lastrec->$f = $value;
        }
    }

    /**
    *
    */
    private function _setNotDirty()
    {
        $this->dirty = false;

        foreach ($this->lastrec as $f => $v) {
            $this->lastrec->$f = $this->record->$f;
        }
    }

    /**
    * \brief Find multiple records
    *
    * \param    string  $sqlcriteria    WHERE clause of SQL SELECT
    * \param    int     $maxcount       Limit on how many records to return (0 = all)
    * \return   array   Array of Active Records
    */
    public function find($sqlcriteria = '', $maxcount = 0)
    {
        $query  = $this->getSQL('find', $sqlcriteria);
        $result = array();

        try {
            $db = MQF_Registry::instance()->getDatabaseById($this->dbid);

            $records = $db->queryReturnArray($query, array(), $maxcount);

            $count = count($records);

            if (!$count) {
                return $result;
            }

            $class = get_class($this);

            foreach ($records as $r) {
                if ($class == 'MQF_ActiveRecord') {
                    $c = new $class($this->dbid, $this->table);
                } else {
                    $c = new $class();
                }
                $c->_apply($r);
                $result[] = $c;
            }
        } catch (Exception $e) {
            MQF_Log::log($e, MQF_ERROR);

            $result = array();
        }

        return $result;
    }

    /**
    * \brief Generate and cache an SQL of given type
    *
    * \param    string  $type   type of SQL to create (find, select, insert, update, delete)
    * \param    string  $extra  Typically WHERE clause for FIND
    * \return   string  SQL query
    */
    public function getSQL($type, $extra = null)
    {
        $qid = strtolower(get_class($this).'_'.strtolower($type));

        if (isset($this->queries[$qid]) and ($type != 'find' and $type != 'update')) {
            return $this->queries[$qid];
        }

        switch ($type) {
        case 'select':
            $query = "SELECT ";
            $t     = array();

            if (count($this->metadata) == 0) {
                throw new Exception("There are no metadata for {$this->table}");
            }

            $query .= '*';

            $query .= " FROM {$this->table} ";

            $query .= " WHERE ";

            $t2 = array();

            foreach ($this->primarykeys as $pk) {
                $t2[] = "$pk = ?";
            }

            $query .= implode(' AND ', $t2);
            break;
        case 'update':
            $query = "UPDATE {$this->table} SET ";

            $t = array();

            foreach ($this->metadata as $m) {
                $f = strtoupper($m['field']);

                if (isset($this->primarykeys[$f])) {
                    continue;
                }

                if ($this->lastrec->$f != $this->record->$f) {
                    $t[] = $f." = ?";
                }
            }

            $query .= implode(',', $t);

            $query .= " WHERE ";

            $t2 = array();

            foreach ($this->primarykeys as $pk) {
                $t2[] = "$pk = ?";
            }

            $query .= implode(' AND ', $t2);
            break;
        case 'insert':
            $query = "INSERT INTO {$this->table} ";

            $fields = array();
            $places = array();
            $params = array();

            foreach ($this->metadata as $m) {
                $f        = strtoupper($m['field']);
                $fields[] = $f;
                $places[] = '?';
            }

            $query .= '('.implode(',', $fields).') ';
            $query .= ' VALUES ';
            $query .= '('.implode(',', $places).') ';
            break;
        case 'delete':
            $query = "DELETE FROM {$this->table} WHERE ";

            $t = array();

            foreach ($this->primarykeys as $pk) {
                $t[] = "$pk = ?";
            }

            $query .= implode(' AND ', $t);
            break;
        case 'find':
            $query = "SELECT ";

            $query .= '*';

            $query .= " FROM {$this->table} ";

            if ($extra != '') {
                $query .= ' WHERE '.$extra;
            }
            break;
        default:
            throw new Exception("Unknown type $type");
        }

        if ($type != 'find' and $type != 'update') {
            $this->queries[$qid] = $query;
        }

        return $query;
    }

    /**
    * \brief Load new record
    *
    * \param    array   $id     Array of parameters to use in SELECT
    * \return   MQF_ActiveRecord
    */
    public function load($id)
    {
        $query = $this->getSQL('select');

        if (is_array($id)) {
            $params = $id;
        } else {
            $params = array($id);
        }

        if ($obj = $this->_querySingle($query, $params)) {
            $this->_apply($obj);

            $this->_setNotDirty();

            MQF_Log::log("Loaded ".implode(',', $this->primarykeys)." (".implode(',', $params).") from {$this->table}", MQF_INFO);

            $this->onLoad();

            return $this;
        } else {
            return false;
        }
    }

    /**
    * \brief Save record if 'dirty'.
    *
    * Will automatically figure out if to use INSERT or UPDATE
    *
    */
    public function save($extforceinsert = false)
    {
        if (!$this->dirty) {
            $this->onAfterSave(false);

            return false;
        }

        $new = true;

        foreach ($this->primarykeys as $pk) {
            if ($this->$pk and $this->$pk !== 0) {
                $new = false;
                break;
            }

            if ($this->$pk === null and !$this->pkautoinc) {
                MQF_Log::log(get_class($this)." primary key $pk is NULL for table '{$this->table}' and this record is not saved", MQF_WARN);
                $this->dirty = false;

                return false;
            }
        }

        $this->onBeforeSave(($new or $extforceinsert) ? true : false);

        if ($new or $extforceinsert) {
            $this->_insert();
        } else {
            $this->_update();
        }

        $this->onAfterSave(($new or $extforceinsert) ? true : false);

        return true;
    }

    /**
    * \brief Update Active Record
    */
    private function _update()
    {
        $query = $this->getSQL('update');

        $params = array();

        foreach ($this->metadata as $m) {
            $f = strtoupper($m['field']);
            if (isset($this->primarykeys[$f])) {
                continue;
            }

            if ($this->lastrec->$f != $this->record->$f) {
                $params[] = $this->$f;
            }
        }

        if (count($params) == 0) {
            $this->_setNotDirty();
            MQF_Log::log(get_class($this)." was set to dirty, but none of the fields for table '{$this->table}' where changed", MQF_WARNING);

            return $this;
        }

        foreach ($this->primarykeys as $pk) {
            $params[] = $this->record->$pk;
        }

        $this->_execSingle($query, $params);

        $this->_setNotDirty();

        MQF_Log::log("Updated {$this->table} with ".implode(',', $this->primarykeys)." (".implode(',', $params).")", MQF_INFO);

        return $this;
    }

    /**
    * \brief Insert Active Record
    */
    private function _insert()
    {
        foreach ($this->primarykeys as $pk) {
            if (!$this->$pk and $this->$pk !== 0) {
                $tmp = array();
                reset($this->primarykeys);
                foreach ($this->primarykeys as $pk) {
                    $tmp[$pk] = $this->$pk;
                }

                $newpk = $this->getPrimaryKeyValues($tmp);

                if ($newpk === true) {
                    break;
                }
                if (!is_array($newpk) or count($newpk) == 0) {
                    throw new Exception("No primarykey was generated!");
                }
                foreach ($newpk as $npk => $npkvalue) {
                    $this->$npk = $npkvalue;
                }
                break;
            }
        }

        $query  = $this->getSQL('insert');
        $params = array();

        foreach ($this->metadata as $m) {
            $f        = strtoupper($m['field']);
            $params[] = $this->$f;
        }

        $this->_execSingle($query, $params);

        MQF_Log::log("Inserted {$this->table} with ".implode(',', $this->primarykeys)." (".implode(',', $params).")", MQF_INFO);

        $this->onInsert();

        $o = $this->_refresh();

        $this->_setNotDirty();
    }

    /**
    * \brief Delete Active Record
    *
    * Object will not be unset, but reset.
    */
    public function delete()
    {
        if ($this->recdel) {
            return true;
        }

        if ($this->pkautoinc) {
            reset($this->primarykeys);
            $pk = current($this->primarykeys);

            if ($this->$pk === null) {
                $this->_setNotDirty();

                MQF_Log::log("Deleted, but no SQL for ".get_class($this)." because Primary Key is NULL", MQF_INFO);

                $this->recdel = true;

                $this->onDelete();

                $this->_reset();

                return true;
            }
        }

        $query  = $this->getSQL('delete');
        $params = array();

        foreach ($this->primarykeys as $pk) {
            $params[] = $this->$pk;
        }

        $this->_execSingle($query, $params);

        $this->_setNotDirty();

        MQF_Log::log("Deleted {$this->table} with ".implode(',', $this->primarykeys)." (".implode(',', $params).")", MQF_INFO);

        $this->recdel = true;

        $this->onDelete();

        $this->_reset();

        return true;
    }

    /**
    * \brief Refresh already loaded Record
    *
    * Can be useful if triggers are manipulating on UPDATE or INSERT
    * and we want those values updated.
    *
    * \return   MQF_ActiveRecord
    */
    private function _refresh()
    {
        $params = array();

        foreach ($this->primarykeys as $pk) {
            $params[] = $this->$pk;
        }

        return $this->load($params);
    }

    /**
    * \brief Execute SQL, returning no recordset
    */
    private function _execSingle($query, $params = array())
    {
        try {
            $db = MQF_Registry::instance()->getDatabaseById($this->dbid);

            $db->Execute($query, $params);

            return true;
        } catch (Exception $e) {
            $this->_apply($this->lastrec);
            $this->_setNotDirty();

            throw $e;
        }
    }

    /**
    * \brief Executing query for a single recordset, and updating the ActiveRecord
    *
    * \throw Exception
    */
    private function _querySingle($query, $params = array())
    {
        try {
            $db = MQF_Registry::instance()->getDatabaseById($this->dbid);

            $rs = $db->Execute($query, $params);

            $obj = $rs->FetchNextObject(true);

            $rs->Close();

            if (!$obj) {
                MQF_Log::log("Record does not exist: $query (".print_r($params, true).')', MQF_WARN);

                return false;
            }

            return MQF_Database::adoToStd($obj, MQF_Database::CLONE_ENC_UTF8);
        } catch (Exception $e) {
            $this->_apply($this->lastrec);
            $this->_setNotDirty();
            throw $e;
        }
    }

    /**
    * \brief Get a value from the Active Record
    *
    * \param    string  $field
    * \return   mixed   Value of field
    * \throw   Exception
    */
    public function __get($field)
    {
        $field = strtoupper(trim($field));

        if ($field == '') {
            throw new Exception("Field is empty!");
        }

        if (isset($this->record->$field) or $this->record->$field == NULL) {
            return $this->signal($field, $this->record->$field, 'GETFILTER');
        } else {
            throw new Exception("Unknown field $field for {$this->table}");
        }
    }

    /**
    * \brief Get a value from the Active Record
    *
    * \param    string  $field
    * \param    mixed   $value
    * \return   bool    Return true on success
    * \throw   Exception
    */
    public function __set($field, $value)
    {
        if ($this->isViewOnly()) {
            throw new Exception(get_class($this).' is view only! Unable to set '.$field.' to '.$value);
        }

        if (is_object($value)) {
            throw new Exception("Unable to save object of type ".get_class($value)." in field {$field}.");
        }

        $field = strtoupper(trim($field));
        if ($field == '') {
            throw new Exception("Field is empty!");
        }

        $ref = get_object_vars($this->record);

        if (isset($ref[$field]) or $ref[$field] === null) {
            if ($value != $this->record->$field) {
                $dt  = $this->metadata[$field]['datatype'];
                $len = $this->metadata[$field]['size'];

                // If len -1 then we specify the ranges by hand
                if ($len == -1) {
                    switch (strtolower($dt)) {
                        case 'tinyblob':
                        case 'tinytext':
                          $len = 256;
                        break;

                        case 'blob':
                        case 'text':
                           $len = 65536;
                        break;

                        case 'mediumblob':
                        case 'mediumtext':
                           $len = 16777216;
                        break;

                        // As if we don't know we give the maximum: 2^32
                        default:
                          $len = 4294967296;
                        break;
                    }
                }

                switch (strtolower($dt)) {
                case 'datetime':
                case 'date':
                case 'timestamp':
                    if (!strtotime($value)) {
                        MQF_Log::log("$value is not a legal date. Set to NULL", MQF_WARNING);
                        $value = null;
                    }
                    break;
                case 'int':
                case 'integer':
                case 'number':
                case 'long':
                case 'short':
                case 'smallint':
                    if (!is_numeric($value)) {
                        throw new Exception("Value of $field is not an number!");
                    }
                    settype($value, 'int');
                    if (strlen($value) > $len) {
                        throw new Exception("Value of $field exceeded length of $len chars!");
                    }
                    break;
                case 'float':
                case 'decimal':
                    if (!is_numeric($value)) {
                        throw new Exception("Value of $field is not an number!");
                    }
                    if (strlen($value) > $len) {
                        throw new Exception("Value of $field exceeded length of $len chars!");
                    }
                    break;
                case 'str':
                case 'string':
                case 'varchar':
                case 'char':
                default:
                    if ($len and strlen($value) > $len) {
                        throw new Exception("Value of $field exceeded length of $len chars!");
                    }
                    break;
                }

                $t = debug_backtrace();

                $class = get_class($this);

                if (count($t) < 1 or $t[1]['class'] != $class or $t[1]['function'] != 'onChange') {
                    $this->onChange($field, $value);

                    $this->lastrec->$field = $this->record->$field;
                }

                if (!$value) {
                    $this->signal($field, $value, 'CLEAR');
                }

                $this->signal($field, $value, 'CHANGE');

                $this->record->$field = $value;

                $this->dirty = true;
            }

            return true;
        } else {
            throw new Exception("Unknown field $field for {$this->table}");
        }
    }

    /**
    * \brief onChange event. Should have full implementation in child class
    *
    * \param    string  $field
    * \param    mixed   $value
    */
    protected function onChange($field, $value)
    {
    }

    /**
    *
    */
    protected function onDelete()
    {
    }

    /**
    * \brief onCreate event. Should have full implementation in child class
    */
    protected function onCreate()
    {
    }

    /**
    * \brief onLoad event. Should have full implementation in child class
    */
    protected function onLoad()
    {
    }

    /**
    * \brief onInsert event. Should have full implementation in child class if this one is not good enough.
    */
    protected function onInsert()
    {
        if (count($this->primarykeys) == 1) {
            reset($this->primarykeys);
            $pk = current($this->primarykeys);

            if ($this->$pk) {
                return true;
            }

            $id = MQF_Registry::instance()->getDatabaseById($this->dbid)->getLastInsertId();

            if (!$id) {
                return true;
            }

            $this->$pk = $id;

            return true;
        }
    }

    /**
     * \brief Will be called before either an update or insert is done.
     *
     * \param bool $newrecord  Specifies if this is a new record, or an older one being updated
     */
    protected function onBeforeSave($newrecord)
    {
        return true;
    }

    /**
     *
     */
    protected function onAfterSave($newrecord)
    {
        return true;
    }

    /**
    * \brief Get array of primary keys
    *
    * \return   array   primarykeys
    */
    public function getPrimaryKeyDef()
    {
        return $this->primarykeys;
    }

    /**
    * \brief Get values for given fields
    *
    * If fields array is empty, all values are returned
    *
    * \param    array   $fields
    * \param    int     Type of return type for values
    * \return   mixed   Active record values  (array or object)
    */
    public function getValues($type = MQF_ActiveRecord::VALUES_AS_OBJECT, $fields = false)
    {
        if (!$fields or count($fields) == 0) {
            $fields = array_keys(get_object_vars($this->record));
        }

        switch ($type) {
        case MQF_ActiveRecord::VALUES_AS_ARRAY:
            $ret = array();

            foreach ($fields as $f) {
                $ret[$f] = $this->$f;
            }

            return $ret;

        case MQF_ActiveRecord::VALUES_AS_OBJECT:
            $ret = new StdClass();

            foreach ($fields as $f) {
                $ret->$f = $this->$f;
            }

            return $ret;

        case MQF_ActiveRecord::VALUES_AS_JSON:
            $ret = new StdClass();

            foreach ($fields as $f) {
                $ret->$f = $this->$f;
            }

            return MQF_Tools::jsonEncode($ret);

        case MQF_ActiveRecord::VALUES_AS_XML:

            $xml = new XMLWriter();
            $xml->openMemory();

            $xml->startDocument('1.0', 'utf-8');

            $xml->startElement('mqfactiverecord');
            $xml->writeAttribute('table', strtolower($this->table));

            foreach ($fields as $f) {
                $xml->writeElement($f, MQF_Tools::utf8($this->$f));
            }

            $xml->endElement();

            return $xml->outputMemory(true);

        default:
            throw new Exception("Unknown returntype '$type'");
        }
    }

    /**
    * \brief Get values of primary keys, or generate new ones if not existing
    *
    * \return   array   Values of primarykeys
    */
    public function getPrimaryKeyValues($autocreate = true)
    {
        $arr = array();

        foreach ($this->primarykeys as $pk) {
            if (!$this->$pk) {
                $tmp = array();
                foreach ($this->primarykeys as $pk) {
                    $tmp[$pk] = $this->$pk;
                }

                if ($autocreate) {
                    return $this->generateTablePrimaryKey($tmp);
                } else {
                    return $tmp;
                }
            } else {
                $arr[$pk] = $this->$pk;
            }
        }

        return $arr;
    }

    /**
    * \brief Get View Only property
    *
    * \return   boolean     View Only
    */
    public function isViewOnly()
    {
        return $this->viewonly;
    }

    /**
    * \brief Set View Only property
    *
    */
    public function setViewOnly()
    {
        $this->viewonly = true;
        MQF_Log::log(get_class($this)." was changed to view-only");
    }

    /**
    * \brief Generates new primarykeys. Must be implemented in child class
    */
    protected function generateTablePrimaryKey($currpk = array())
    {
        return true;
    }

    /**
     *
     */
    public function getMetadataAndValue($field)
    {
        if (!isset($this->metadata[$field])) {
            throw new Exception("Unknown field '$field'");
        }

        $dt    = $this->metadata[$field]['datatype'];
        $len   = $this->metadata[$field]['size'];

        return array(
            'datatype' => $dt,
            'length'   => $len,
            'field'    => $field,
            'value'    => $this->$field,
        );
    }
}
