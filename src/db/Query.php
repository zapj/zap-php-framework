<?php

namespace zap\db;

use PDO;
use zap\DB;
use zap\util\Random;

class Query
{
    /**
     *
     * @var string (SELECT|DELETE|UPDATE)
     */
    protected $sqlType = 'SELECT';
    protected $query = array();
    protected $select = array();
    protected $fields = array();

    protected $from = array();

    protected $where = array();

    protected $having = array();


    protected $join = array();

    protected $params = array();
    protected $orderBy = array();
    protected $groupBy = array();
    protected $limit = 0;
    protected $offset = 0;
    protected $distinct = false;

    protected $isClosureWhere = false;

    protected $fetchMode = null;
    protected $fetchClass = null;

    /**
     *
     * @var \zap\db\ZPDO
     */
    protected $db;

    public function __construct($zPDO = null) {
        $this->db = $zPDO ?: DB::connect();
    }

    public function setFetchClass($class){
        $this->fetchMode = PDO::FETCH_CLASS;
        $this->fetchClass = $class;
        return $this;
    }

    public function setFetchMode($fetchMode=null,$class=null){
        $this->fetchMode = $fetchMode;
        $this->fetchClass = $class;
        return $this;
    }

    /**
     * @param array|string $columns 列名
     *
     * @return \zap\db\Query $this
     */
    public function select($columns = '*') {
        if (is_string($columns)) {
            $this->select[] = $columns;
        } else if (is_array($columns)) {
            $this->select[] = implode(',', $columns);
        }
        return $this;
    }

    /**
     * @param array|string $tables
     * @param null|string $alias
     *
     * @return \zap\db\Query $this
     */
    public function from($tables,$alias = null) {
        if(is_array($tables)){
            foreach ($tables as $table=>$aliasName) {
                if(is_string($table)){
                    $this->from[] = $this->db->quoteTable($table) . ($aliasName ? " AS $aliasName" : '');
                }else{
                    $this->from[] = $this->db->quoteTable($aliasName);
                }
            }
        }else{
            $this->from[] = $this->db->quoteTable($tables) . ($alias ? " AS $alias" : '');
        }
        return $this;
    }

    /**
     * where
     * @param string $name
     * @param string $operator (in|not in|<>|=|!=|....)
     * @param string|array $value
     * @return \zap\db\Query $this
     */
    function where($name, $operator = '=', $value = null) {
        if (is_callable($name)) {
            $this->isClosureWhere = true;
            $this->_addWhere('(', 'AND');
            call_user_func($name, $this);
            $this->where[] = ')';
            $this->isClosureWhere = false;
            return $this;
        }
        $where = $this->db->quoteColumn($name);
        $paramName = Random::rand(Random::ALPHA);
        $colName = ':' . $paramName;

        switch (strtoupper($operator)) {
            case 'IN':
            case 'NOT IN':
                if (is_array($value)) {
                    $value = implode(',', $value);
                    $where .= ' ' . $operator . ' (' . $value . ')';
                } elseif ($value instanceof Expr) {
                    $where .= ' ' . $operator . ' (' . $value->raw . ')';
                } else {
                    $where .= ' ' . $operator . ' (' . $colName . ')';
                }

                break;
            case 'NOT LIKE':
            case 'LIKE':
                $where .= ' ' . $operator . ' ' . $colName . ' ';
                break;
            case '=':
            case '!=':
            case '<>':
            case '<=>':
            case '>':
            case '<':
            case '<=':
            case '>=':
                if ($value instanceof Expr) {
                    $where .= ' ' . $operator . $value->raw;
                } else {
                    $where .= $operator . $colName;
                }

                break;
            case 'IS NULL':
            case 'IS NOT NULL':
                $value = NULL;
                $where .= ' ' . $operator;
                break;
            default:
                $value = $operator;
                $where .= '=' . $colName;
                break;
        }
        $this->_addWhere($where, 'AND');
        if (!is_null($value) || !$value instanceof Expr) {
            $this->params[$paramName] = $value;
        }
        return $this;
    }

    function orWhere($name, $operator = '=', $value = null) {
        if (is_callable($name)) {
            $this->isClosureWhere = true;
            $this->_addWhere('(', 'OR');
            call_user_func($name, $this);
            $this->where[] = ')';
            $this->isClosureWhere = false;
            return $this;
        }
        $where = $this->db->quoteColumn($name);
        $paramName = Random::rand(Random::ALPHA);
        $colName = ':' . $paramName;

        switch (strtoupper($operator)) {
            case 'IN':
            case 'NOT IN':
                if (is_array($value)) {
                    $value = implode(',', $value);
                    $where .= ' ' . $operator . ' (' . $value . ')';
                } elseif ($value instanceof Expr) {
                    $where .= ' ' . $operator . ' (' . $value->raw . ')';
                } else {
                    $where .= ' ' . $operator . ' (' . $colName . ')';
                }

                break;
            case 'NOT LIKE':
            case 'LIKE':
                $where .= ' ' . $operator . ' ' . $colName . ' ';
                break;
            case '=':
            case '!=':
            case '<>':
            case '<=>':
            case '>':
            case '<':
            case '<=':
            case '>=':
                if ($value instanceof Expr) {
                    $where .= ' ' . $operator . $value->raw;
                } else {
                    $where .= $operator . $colName;
                }

                break;
            case 'IS NULL':
            case 'IS NOT NULL':
                $value = NULL;
                $where .= ' ' . $operator;
                break;
            default:
                $value = $operator;
                $where .= '=' . $colName;
                break;
        }
        $this->_addWhere($where, 'OR');
        if (!is_null($value) || !$value instanceof Expr) {
            $this->params[$paramName] = $value;
        }

        return $this;
    }

    /**
     *
     * @param string $conditions
     * @param array $params
     * @return \zap\db\Query $this
     */
    function rawWhere($conditions, $params = array()) {
        $this->where[] = $conditions;
        $this->addParams($params);
        return $this;
    }

    /**
     * Add where in statement
     *
     * @param string $column
     * @param array $params
     *
     * @return Query
     */
    public function whereIn($column, $params) {
        $this->prepareWhereInStatement($column, $params, false);
        return $this;
    }

    /**
     * Add where not in statement
     *
     * @param $column
     * @param $params
     * @return Query
     */
    public function whereNotIn($column, $params) {
        $this->prepareWhereInStatement($column, $params, true);
        return $this;
    }

    private function _addWhere($where, $type = 'AND') {
        if (empty($this->where) || end($this->where) == '(') {
            $this->where[] = $where;
        } else {
            $this->where[] = $type . ' ';
            $this->where[] = $where;
        }
    }

    /**
     *
     * @param string $statement
     * @param array $params
     * @return \zap\db\Query
     */
    public function having($statement, $params = null) {
        $this->having[] = $statement;
        $this->addParams($params);
        return $this;
    }

    public function join($type, $table, $on, $params = array()) {
        if(is_array($table) && count($table) == 2){
            $table = $this->db->quoteTable($table[0]) . ' AS '. $table[1];
        }else{
            $table = $this->db->quoteTable($table);
        }

        $type = strtoupper($type);
        $this->join[] = "$type $table ON $on";
        $this->addParams($params);
        return $this;
    }

    public function leftJoin($table, $on, $params = array()) {
        $this->join('LEFT JOIN', $table, $on, $params);
        return $this;
    }

    public function rightJoin($table, $on, $params = array()) {
        $this->join('RIGHT JOIN', $table, $on, $params);
        return $this;
    }

    public function innerJoin($table, $on, $params = array()) {
        $this->join('INNER JOIN', $table, $on, $params);
        return $this;
    }

    public function groupBy($statement) {
        $this->groupBy[] = $statement;
        return $this;
    }

    public function orderBy($statement) {
        $this->orderBy[] = $statement;
        return $this;
    }

    /**
     * Returns generated SQL query
     *
     * @return string
     */
    public function getSQL() {
        $sql = '';
        switch ($this->sqlType) {
            case 'SELECT':
                $sql .= 'SELECT ' . $this->prepareSelectString() . $this->prepareFrom();
                break;
            case 'DELETE FROM':
                $sql .= 'DELETE FROM ' . $this->prepareFrom();
                break;
            case 'UPDATE':
                $sql .= 'UPDATE ' . $this->prepareFrom() . $this->prepareUpdateSet();
                break;
            default:
                break;
        }
        $sql .= $this->prepareJoinString();
        $sql .= $this->prepareWhereString();
        $sql .= $this->prepareGroupByString();
        $sql .= $this->prepareHavingString();
        $sql .= $this->prepareOrderByString();
        $sql .= $this->prepareLimitString();
        return $sql;
    }

    public function getFullSQL(){
        return $this->getSQL() . ' PARAMS: ' . print_r($this->getParams(),true);
    }

    /**
     * @return false|\PDOStatement
     */
    public function execute() {
        $stm = $this->db->prepare($this->getSQL());
        $stm->execute($this->params);
        $this->db->rowCount = $stm->rowCount();
        return $stm;
    }

    public function get($fetchMode = \PDO::FETCH_OBJ,$fetch_argument = null,...$args) {
        $stm = $this->db->prepare($this->getSQL());
        $stm->execute($this->params);
        $this->db->rowCount = $stm->rowCount();
        if(!is_null($this->fetchMode)){
            return $stm->fetchAll($this->fetchMode,$this->fetchClass);
        }
        if(is_int($fetch_argument)){
            return $stm->fetchAll($fetchMode,$fetch_argument);
        }else if(is_string($fetch_argument)){
            return $stm->fetchAll($fetchMode,$fetch_argument,$args);
        }
        return $stm->fetchAll($fetchMode);

    }

    public function first($fetchMode = \PDO::FETCH_OBJ,$fetchClass = null) {
        $stm = $this->db->prepare($this->getSQL());
        $stm->execute($this->params);
        $this->db->rowCount = $stm->rowCount();
        if(!is_null($this->fetchMode)){
            $stm->setFetchMode($this->fetchMode,$this->fetchClass);
            return $stm->fetch();
        }else if(!is_null($fetchClass)){
            $stm->setFetchMode($fetchMode,$fetchClass);
            return $stm->fetch();
        }
        return $stm->fetch($fetchMode);
    }

    public function count($columnName = '*') {
        $sql = "SELECT count($columnName) as rowcount FROM " . $this->prepareFrom();
        $sql .= $this->prepareJoinString();
        $sql .= $this->prepareWhereString();
        $sql .= $this->prepareGroupByString();
        $sql .= $this->prepareHavingString();
//        $sql .= $this->prepareOrderByString();
        $sql .= $this->prepareLimitString();
        $stm = $this->db->prepare($sql);
        $stm->execute($this->params);
        return $stm->fetchColumn();
    }

    public function distinct() {
        $this->distinct = true;
        return $this;
    }

    /**
     * Add param(s) to stack
     *
     * @param array $params
     *
     * @return \zap\db\Query
     */
    public function addParams($params) {
        if (is_null($params)) {
            return $this;
        }
        if (!is_array($params)) {
            $params = array($params);
        }
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * update set
     * @param string|array $name
     * @param mixed $value
     * @return \zap\db\Query
     */
    public function set($name, $value = null) {
        if (is_array($name)) {
            foreach ($name as $key => $val) {
                $this->fields[] = "$key=:$key";
                $this->params[$key] = $val;
            }
        } else {
            $this->fields[] = "$name=:$name";
            $this->params[$name] = $value;
        }
        return $this;
    }

    public function getParams() {
        return $this->params;
    }

    public function bindValues($params) {
        $this->addParams($params);
        return $this;
    }

    public function bind($name, $value) {
        $this->params[$name] = $value;
        return $this;
    }

    private function prepareSelectString() {
        if (empty($this->select)) {
            $this->select("*");
        }
        return $this->distinct ? 'distinct ' . implode(", ", $this->select) . ' FROM ' : implode(", ", $this->select) . ' FROM ';
    }

    private function prepareFrom() {
        return implode(", ", $this->from) . " ";
    }

    private function prepareUpdateSet() {
        return ' SET ' . implode(",", $this->fields);
    }

    private function prepareWhereInStatement($column, $params, $not_in = false) {
        $in = ($not_in) ? "NOT IN" : "IN";
        if (!is_array($params)) {
            $params = array($params);
        }
        $this->where[] = $this->db->quoteColumn($column) . " " . $in . ' (' . implode(',', $params) . ')';
    }

    private function prepareJoinString() {
        if (!empty($this->join)) {
            return implode(" ", $this->join) . " ";
        }
        return '';
    }

    private function prepareWhereString() {
        $where = '';
        if (!empty($this->where)) {
            $where = ' WHERE ' . implode(' ', $this->where);
        }
        return $where;
    }

    private function prepareGroupByString() {
        if (!empty($this->groupBy)) {
            return " GROUP BY " . implode(", ", $this->groupBy) . " ";
        }
        return '';
    }

    private function prepareHavingString() {
        if (!empty($this->having)) {
            return " HAVING " . implode(", ", $this->having) . " ";
        }
        return '';
    }

    private function prepareOrderByString() {
        if (!empty($this->orderBy)) {
            return " ORDER BY " . implode(", ", $this->orderBy) . " ";
        }
        return '';
    }

    private function prepareLimitString() {
        if (!empty($this->limit) && empty($this->offset)) {
            return " LIMIT {$this->limit}";
        } else if ($this->offset) {
            return " LIMIT {$this->offset},{$this->limit}";
        }
        return '';
    }

    public function limit($limit, $offset = null) {
        $this->limit = $limit;
        if ($offset) {
            $this->offset = $offset;
        }
        return $this;
    }

    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }

    public function reset() {
        $this->query = array();
        $this->select = array();
        $this->from = array();
        $this->where = array();
        $this->having = array();
        $this->join = array();
        $this->params = array();
        $this->orderBy = array();
        $this->groupBy = array();
        $this->limit = 0;
        $this->offset = 0;
    }

    public function insert($tableName, $columns) {
        return $this->db->insert($tableName, $columns);
    }

    public function update($table = NULL, $columns = NULL, $conditions = '', $params = array()) {
        if (is_null($table)) {
            $this->sqlType = 'UPDATE';
            $this->execute();
            return $this->db->rowCount();
        }
        return $this->db->update($table, $columns, $conditions, $params);
    }

    public function delete($table = NULL, $conditions = '', $params = array()) {
        if (is_null($table)) {
            $this->sqlType = 'DELETE FROM';
            $this->execute();
            return $this->db->rowCount();
        }
        return $this->db->delete($table, $conditions, $params);
    }
}