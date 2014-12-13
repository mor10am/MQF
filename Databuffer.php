<?php

/**
* \class MQF_Databuffer
*
* \author Morten Amundsen <mortena@tpn.no>
* \author Ken-Roger Andersen <kenny@tpn.no>
* \author Magnus Espeland <magg@tpn.no>
* \author Gunnar Graver <gunnar.graver@teleperformance.no>
* \remark Copyright 2006-2007 Teleperformance Norge AS
* \version $Id: Databuffer.php 806 2007-11-28 14:03:50Z mortena $
*
*/
class MQF_Databuffer
{
    private $id;            ///< MQF_Databuffer Id
    private $signature;     ///< Signature
    private $metadata;      ///< CREATE TABLE query
    private $isnew = true;  ///< Is a new database

    /**
    * \brief Create new databuffer
    */
    public function __construct($id, $options = array())
    {
        $path = '.';
        if (isset($options['path'])) {
            $path = $options['path'];
        }

        $this->id = strtolower(trim($id));

        $filename = "{$path}/MQF_db_".$id.".sqlite";
        MQF_Log::log('filename: '.$filename);
        if (isset($options['ttl'])) {
            $ttl = $options['ttl'];

            $stat = @filemtime($filename);
            MQF_Log::log('Refreshtime:'.($stat + $ttl).' time:'.time().' remaining:'.(($stat + $ttl)-time()));
            // Offset 10 = time of last inode change (Unix timestamp)
            if (is_numeric($stat) && (($stat + $ttl) < time())) {
                unlink($filename);
            }
        }

        $this->db = new PDO("sqlite2:{$filename}");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($obj = $this->db->Query("select name, sql from sqlite_master where type = 'table' and name = '{$this->id}'")->fetchObject()) {
            $this->metadata = $obj->sql;

            MQF_Log::log(print_r($obj, true));

            $this->signature = $this->_sigFromSQL($obj->sql);

            $this->isnew = false;
        } else {
            if (isset($options['metadata'])) {
                $this->_buildFromOptions($options['metadata']);
            }
        }
    }

    /**
    *
    */
    public function __destruct()
    {
    }

    /**
    * \brief is this a new and empty buffer?
    *
    * \return bool new
    */
    public function isNew()
    {
        return $this->isnew;
    }

    /**
    * \brief Create a 'unique' Id from a given SQL query
    *
    * \param string Query
    * \return string signature
    */
    private function _sigFromSQL($sql)
    {
        if (!preg_match('/\((.*)\)$/', $sql, $matches)) {
            throw new Exception("Unable to understand metadata '$sql'");
        }

        $d   = $matches[1];
        $tmp = explode(',', $d);

        $sig = '';

        foreach ($tmp as $m) {
            $tmp2 = explode(' ', $m);

            $sig .= strtoupper($tmp2[0]).' ';
        }

        return md5($sig);
    }

    /**
    * \brief Set a signature
    *
    * \param string signature
    *
    */
    public function setSignature($sig)
    {
        if ($this->signature) {
            throw new Exception("Databuffer already has a signature");
        }
        $this->signature = $sig;

        return true;
    }

    /**
    * \brief Create database from options
    *
    * \param array Options
    * \throw Exception
    *
    */
    private function _buildFromOptions($opt)
    {
        $tmp = array();
        $sig = '';

        foreach ($opt as $m) {
            $str = strtoupper($m['field']).' ';
            $sig = strtoupper($m['field']).' ';

            switch ($m['datatype']) {
            case 'int':
            case 'integer':
                $str .= 'integer(11)';
                break;
            case 'datetime':
            case 'date':
                $str .= 'datetime';
                break;
            case 'string':
            case 'varchar':
            default:
                $str .= 'varchar(255)';
                break;
            }

            $tmp[] = $str;
        }

        $sql = "create table {$this->id} (".implode(',', $tmp).")";

        $this->metadata = $sql;

        MQF_Log::log($sql);

        try {
            $this->db->exec($sql);
        } catch (Exception $e) {
            $msg = strtolower($e->getMessage());
            if (strstr($msg, 'already exist') === false) {
                throw $e;
            }
            MQF_Log::log($e->getMessage(), MQF_WARNING);
        }

        $this->setSignature(md5($sig));

        return true;
    }

    /**
    * \brief Empty the database by dropping the table, creating it, and the vacuuming it.
    */
    public function emptyBuffer()
    {
        $this->db->exec("drop table {$this->id}");
        $this->db->exec($this->metadata);
        $this->db->exec("vacuum");

        return true;
    }

    /**
    * \brief Search the databuffer
    */
    public function search($options = array())
    {
        if (!$this->signature) {
            return false;
        }

        if (isset($options['count']) and $options['count']) {
            $select = " count(*) as COUNT ";
        } else {
            if (isset($options['only'])) {
                foreach ($options['only'] as $key => $value) {
                    $options['only'][$key] = strtoupper($value);
                }

                $select = " ".implode(',', $options['only'])." ";
            } else {
                $select = " * ";
            }
        }

        if (isset($options['group'])) {
            $select .= ', count(*) as COUNT ';
        }

        $sql = "select {$select} from {$this->id} ";

        $tmp = array();

        if (count($options['search']) || count($options['lt']) || count($options['gt'])) {
            $sql .= ' where ';
            if (isset($options['search'])) {
                foreach ($options['search'] as $field => $value) {
                    $tmp[] = strtoupper($field)." = '".$value."' ";
                }
            }
            if (isset($options['lt'])) {
                foreach ($options['lt'] as $field => $value) {
                    $tmp[] = strtoupper($field)." > '".$value."' ";
                }
            }
            if (isset($options['gt'])) {
                foreach ($options['gt'] as $field => $value) {
                    $tmp[] = strtoupper($field)." < '".$value."' ";
                }
            }
            $sql .= implode(' and ', $tmp);
        }

        if (isset($options['group'])) {
            foreach ($options['group'] as $key => $value) {
                $options['group'][$key] = strtoupper($value);
            }

            $sql .= " group by ".implode(',', $options['group']);
        }

        $result = array();

        MQF_Log::log($sql);

        foreach ($this->db->query($sql) as $row) {
            $result[] = $row;
        }

        return $result;
    }

    /**
    * \brief Add data to databuffer
    */
    public function add($data, $signature = null)
    {
        if (!is_array($data) or count($data) == 0) {
            throw new Exception("Unable to add empty dataset");
        }

        if ($signature and $this->signature != $signature) {
            throw new Exception("Your signature does not match the loaded signature!");
        }

        if (!$this->signature) {
            $tmp = current($data);
            $key = key($data);

            if (is_array($tmp)) {
                $fc      = 1;
                $options = array();
                foreach (array_keys($tmp) as $field) {
                    if (is_numeric($field)) {
                        $field = 'field'.$fc;
                    }

                    $mt                    = array('field' => $field, 'datatype' => 'varchar');
                    $options['metadata'][] = $mt;

                    $fc++;
                }
            } else {
                $options = array('metadata' => array(
                                                'field' => 'field1', 'datatype' => 'varchar',
                                                ),
                                );
            }

            $this->_buildFromOptions($options['metadata']);
        }

        $fields = array_keys(current($data));
        $into   = array();

        foreach ($fields as $field) {
            $into[] = ":".strtoupper($field);
        }

        $sql = "insert into {$this->id} (".implode(',', $fields).") values (".implode(',', $into).")";

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare($sql);

            foreach ($data as $line) {
                $params = array();
                foreach ($line as $field => $value) {
                    $field = strtoupper($field);
                    $params[":{$field}"] = $value;
                }
                try {
                    $stmt->execute($params);
                } catch (Exception $e) {
                    $msg = strtolower($e->getMessage());
                    if (strstr($msg, 'primary') === false) {
                        throw $e;
                    }
                }
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
