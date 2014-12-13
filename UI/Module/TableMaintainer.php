<?php

class MQF_Module_TableMaintainer extends MQF_Module
{
    protected $editrules = false;
    protected $can_delete = false;
    protected $columns = false;
    protected $pad_empty = false;

    protected $tableid = '';
    protected $restrict_colums = false;
    protected $reload_on_refresh = false;
    protected $tabledata = array();

    /**
    *
    */
    public function __construct($options = array())
    {
        $this->dbid  = $options['tm.dbid'];
        $this->table = $options['tm.table'];

        if (isset($options['tm.editrules'])) {
            $this->editrules = $options['tm.editrules'];
        }

        if (isset($options['tm.columns'])) {
            $this->columns = $options['tm.columns'];
        }

        if (isset($options['tm.can-delete'])) {
            $this->can_delete = $options['tm.can-delete'];
        }

        if (isset($options['tm.pad-empty'])) {
            $this->pad_empty = $options['tm.pad-empty'];
        }

        if (isset($options['tm.reload-on-refresh'])) {
            $this->reload_on_refresh = $options['tm.reload-on-refresh'];
        }

        $options['tplplugin'] = $this;
        parent::__construct($options);

        // Må kanskje ha en config setting som sier noen om hva som er path til MQF rammeverket?
        $this->setTemplateName(MQF_APPLICATION_PATH.'/../MQF/data/mqfTableMaintainer.tpl');
    }

    /**
    *
    */
    public function init()
    {
        $this->tableid = $this->dbid.'_'.$this->table;

        if (is_array($this->columns) and count($this->columns)) {
            $this->restrict_columns = true;
        }

        $ar = new MQF_ActiveRecord($this->dbid, $this->table);
        $this->tabledata = $ar->find();

        $this->export('tabledata');
        $this->export('tableid');
        $this->export('table');
    }

    /**
    *
    */
    public function beforeRefresh()
    {
        if ($this->reload_on_refresh) {
            $ar = new MQF_ActiveRecord($this->dbid, $this->table);
            $this->tabledata = $ar->find();
        }
    }

    /**
    *
    */
    public function onChange(&$orm, $field, $value)
    {
    }

    /**
    *
    */
    public function changeFieldValue($domid, $value)
    {
        $parts = explode('_', $domid);

        if (!count($parts)) {
            throw new Exception("Unable to decode DOMID $domid");
        }

        $offset = count($parts)-1;

        // primary key
        $id = $parts[$offset];
        unset($parts[$offset]);

        // table name
        unset($parts[0]);

        // Field name
        $field = implode('_', $parts);

        $orm = new MQF_ActiveRecord($this->dbid, $this->table);

        $orm->load($id);

        $old_value = $orm->$field;

        $orm->$field = $value;

        if ($old_value != $value) {
            $this->onChange($orm, $field, $value);
        }

        $orm->save();

        $ret = new stdClass();
        $ret->LASTACTION = 'UPDATE';
        $ret->ROWDOMID = "{$this->table}_{$id}";
        $ret->CELLDOMID = "{$this->table}_{$field}_{$id}";
        $ret->DOMID = $domid;
        $ret->PK = $id;
        $ret->FIELD = $field;
        $ret->TABLE = $this->table;
        $ret->ORM = $orm->getValues();

        return $ret;
    }

    /**
    *
    */
    public function deleteRow($id)
    {
        $orm = new MQF_ActiveRecord($this->dbid, $this->table);

        $orm->load($id);

        $orm->delete();

        $ret = new stdClass();
        $ret->LASTACTION = 'DELETE';
        $ret->ROWDOMID = "{$this->table}_{$id}";
        $ret->CELLDOMID = false;
        $ret->DOMID = false;
        $ret->PK = $id;
        $ret->FIELD = false;
        $ret->TABLE = $this->table;
        $ret->ORM = false;

        return $ret;
    }

    //--------------------------------------------------------------

    public function tplfuncTMRowCells($params, &$smarty)
    {
        if (!isset($params['activerecord']) or !$params['activerecord'] instanceof MQF_ActiveRecord) {
            throw new Exception("ActiveRecord object is not given!");
        }

        $o = $params['activerecord'];

        $pk = $o->getPrimaryKeyValues();
        if (count($pk) != 1) {
            throw new Exception("Table must have one, and only one, primary key!");
        }

        $pk = current($pk);

        $o = $o->getValues(MQF_ActiveRecord::VALUES_AS_ARRAY);

        // only show columns as specified in options. Fields will also be ordered like specified.
        if ($this->restrict_columns) {
            $use = array();

            foreach ($this->columns as $c => $name) {
                $use[$c] = $o[$c];
            }

            $o = $use;
        }

        if ($this->can_delete) {
            $html .= "<td class='mqfTMCell'><button class='mqfTMDelButton' onClick=\"mqfCallMethod('{$this->id}.deleteRow', [{$pk}], 'function mqfTMCallback');\">Del</button></td>\n";
        }

        foreach ($o as $f => $v) {
            if (isset($this->editrules[$f]) and $this->editrules[$f] === true) {
                $editid = "{$this->table}_{$f}_{$pk}";

                $html .= "<td class='mqfTMCell mqfTMCellEditable'>";

                if (!strlen($v)) {
                    $v = '&nbsp;';
                }

                $html .= "<span id='$editid'>$v</span>\n";
                $html .= "<script>\n";
                $html .= "new mqfEditInPlace('$editid', '{$this->id}.changeFieldValue', {callback_hook: mqfTMCallback, mouseover_class: 'yellow'});\n";
                $html .= "</script>\n";
            } else {
                $html .= "<td class='mqfTMCell' id='{$this->table}_{$f}_{$pk}'>";

                if (!strlen($v) and $this->pad_empty) {
                    $v = '&nbsp;';
                }

                $html .= $v;
            }

            $html .= "</td>\n";
        }

        return $html;
    }

    /**
    *
    */
    public function tplfuncTMHeaderRow($params, &$smarty)
    {
        if (count($this->tabledata) == 0) {
            return '';
        }

        $o = current($this->tabledata);

        $o = $o->getValues(MQF_ActiveRecord::VALUES_AS_ARRAY);

        $html .= "<tr id='header_{$this->table}' class='mqfTMHeader'>\n";

        if ($this->can_delete) {
            $html .= "<th id='headercell_{$this->table}_delete' class='mqfTMHeaderCell'>&nbsp;</th>\n";
        }

        if ($this->restrict_columns) {
            $use = array();

            foreach ($this->columns as $c => $name) {
                $use[$c] = $o[$c];
            }

            $o = $use;
        }

        foreach ($o as $f => $v) {
            $html .= "<th class='mqfTMHeaderCell' id='headercell_{$this->table}_{$f}'>";
            if ($this->restrict_columns) {
                $html .= $this->columns[$f];
            } else {
                $html .= $f;
            }
            $html .= "</th>\n";
        }
        $html .= "</tr>\n";

        return $html;
    }

    /**
    *
    */
    public function tplfuncTMPK($params, &$smarty)
    {
        if (!isset($params['activerecord']) or !$params['activerecord'] instanceof MQF_ActiveRecord) {
            throw new Exception("ActiveRecord object is not given!");
        }

        $o = $params['activerecord'];

        $pk = $o->getPrimaryKeyValues();
        if (count($pk) != 1) {
            throw new Exception("Table must have one, and only one, primary key!");
        }

        $pk = current($pk);

        return $pk;
    }
}
