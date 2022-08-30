<?php

namespace zap\db;

use zap\DB;
use zap\util\Arr;

use zap\util\Str;

use function app;

abstract class Model implements \ArrayAccess
{
    protected $primaryKey = 'id';

    protected $table;

    protected $attributes = array();

    public $isNewRecord = false;

    protected $connection;

    protected $fillable = null;

    protected $columnAlias = [];

    /**
     * Table Attributes
     * @param array $attributes
     */
    public function __construct(array $attributes = array(),$keys = []) {
        $this->fill($attributes,$keys);
        $this->init();
    }

    protected function getAliasName(){
        return $this->getTable();
    }

    public static function where($name,$operator = '=',$value = null){
        $model = new static;
        $query = DB::table($model->getTable(),$model->getAliasName());
        $query->setFetchClass(get_called_class());
        return $query->where($name,$operator,$value);
    }

    public function db($connection = null) {
        return DB::connect(is_null($connection) ? $this->connection : $connection);
    }

    public function init() {

    }

    /**
     * Set New Record
     * @param boolean $flag
     * @return \zap\db\Model
     */
    public function setNewRecord($flag) {
        $this->isNewRecord = $flag;
        return $this;
    }

    /**
     * Get TableName or Class Basename
     * @return string Table Name
     */
    public function getTable() {
        if (!is_null($this->table) || !empty($this->table)) {
            return $this->table;
        }
        return $this->getClassName();
    }

    /**
     * Get PrimaryKey Name
     * @return string
     */
    public function getPrimaryKey() {
        return $this->primaryKey;
    }

    /**
     * Set PrimaryKey
     * @param string $key
     * @return \zap\db\Model
     */
    public function setPrimaryKey($key) {
        $this->primaryKey = $key;
        return $this;
    }

    /**
     *
     * @return int PrimaryKey Value
     */
    public function getId() {
        if(is_array($this->primaryKey)){
            return Arr::find($this->attributes,$this->primaryKey);
        }
        return $this->getAttribute($this->getPrimaryKey());
    }

    public function save() {
        if ($this->isNewRecord) {
            $this->db()->insert($this->getTable(), $this->getAttributes());
            $this->setNewRecord(false);
            $this->setAttribute($this->getPrimaryKey(), $this->db()->lastInsertId());
        } else {
            $query = DB::table($this->getTable())->set($this->getAttributes());

            $primaryKeyValue = $this->getId();
            if(is_array($primaryKeyValue)){
                foreach ($primaryKeyValue as $key=>$value){
                    $query->where($key,$value);
                }
            }else{
                $query->where($this->getPrimaryKey(), $this->getId());
            }
            $query->update();
        }
        return $this;
    }


    /**
     * fill attributes
     * @param array $attributes
     * @return \zap\db\Model
     */
    public function fill(array $attributes = array(),$keys = []) {
        if(!empty($keys)){
            $attributes = Arr::find($attributes,$keys);
        }else if(is_array($this->fillable)){
            $attributes = Arr::find($attributes,$this->fillable);
        }

        foreach ($attributes as $key => $value) {
            $this->offsetSet($key,$value);
        }
        return $this;
    }

    public function destory() {
        $id = $this->getId();
        if (!$id) {
            return false;
        }
        $pkey = $this->getPrimaryKey();
        return $this->db()->delete($this->getTable(), $pkey . '=:' . $pkey, array(
            $pkey => $id
        ));
    }

    public static function find() {
        $model = new static;
        $query = DB::table($model->getTable(),$model->getAliasName());
        $query->setFetchClass(get_called_class());
        return $query;
    }

    /**
     *
     * @param array|int $ids
     * @return mixed
     */
    public static function findById($ids) {
        $ids = is_array($ids) ? $ids : func_get_args();
        $model = new static;
        $query = DB::table($model->getTable(),$model->getAliasName())->whereIn($model->getPrimaryKey(),$ids);
        $query->setFetchClass(get_called_class());
        if (func_num_args() == 1) {
            return $query->first();
        }
        return $query->get();
    }

    public static function findAll($params = array(),$options = []) {
        $model = new static;
        $query = DB::table($model->getTable(),$model->getAliasName());
        if (is_array($params) && !empty($params)) {
            foreach ($params as $key => $value) {
                $query->where($key,$value);
            }
        }
        if(isset($options['orderBy'])){
            $query->orderBy($options['orderBy']);
        }
        if(isset($options['groupBy'])){
            $query->groupBy($options['groupBy']);
        }

        if(isset($options['limit'])){
            is_string($options['limit']) && $query->limit($options['limit']);
            is_array($options['limit']) && $query->limit($options['limit'][0],$options['limit'][1]);
        }
        return $query->get();
    }

    /**
     *
     * @param int|array $ids
     * @return int
     */
    public static function delete($ids) {
        $ids = is_array($ids) ? $ids : func_get_args();
        $model = new static;
        $query = DB::table($model->getTable());
        $primaryKey = $model->getPrimaryKey();
        if(is_string($primaryKey)){
            return $query->where($primaryKey, $ids)->delete();
        }
        return 0;
    }

    /**
     *
     * @param array $attributes
     * @return \static
     */
    public static function create(array $attributes = array()) {
        $model = new static($attributes);
        $model->setNewRecord(true);
        $model->save();
        return $model;
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key) {
        return $this->attributes[$key];
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value) {
        $this->offsetSet($key,$value);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset) {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset) {
        return $this->attributes[$offset];
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value) {
        if(isset($this->columnAlias[$offset])){
            $offset = $this->columnAlias[$offset];
        }
        $this->attributes[$offset] = $value;
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset) {
        unset($this->attributes[$offset]);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key) {
        return isset($this->attributes[$key]);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key) {
        unset($this->attributes[$key]);
    }

    public function setAttribute($key, $value) {
        $this->offsetSet($key,$value);
        return $this;
    }

    public function hasAttribute($key) {
        return isset($this->attributes[$key]);
    }

    public function getAttribute($key) {
        if ($this->hasAttribute($key)) {
            return $this->attributes[$key];
        }
        return NULL;
    }

    public function getAttributes($keys = []) {
        if(!empty($keys)){
            return Arr::find($this->attributes,$keys);
        }else if(is_array($this->fillable)){
            return Arr::find($this->attributes,$this->fillable);
        }

        return $this->attributes;
    }

    public function collect($class,$keys = []){
        $model = new $class($this->attributes,$keys);
        return $model;
    }

    private function getClassName(){
        $className = explode('\\', get_class($this))[0];
        return strtolower($className);
    }

    public static function getDefaultTableName(){
        $className = explode('\\', get_called_class())[0];
        $className = preg_replace('/([A-Z])/', '_$1', $className);
        return strtolower(trim($className,'_'));
    }


    public function __call($name, $arguments)
    {
        $query = DB::table($this->getTable(),$this->getAliasName());
        $query->setFetchClass(get_called_class());
        return call_user_func_array([$query,$name],$arguments);
    }

    public static function __callStatic($name, $arguments)
    {

        $model = new static;
        $query = DB::table($model->getTable(),$model->getAliasName());
        $query->setFetchClass(get_called_class());
        if(Str::startsWith($name,'findBy')){
            $columnName = preg_replace('/([A-Z])/', '_$1', str_ireplace('findBy','',$name));
            $columnName = strtolower(trim($columnName,'_'));
            array_unshift($arguments,$columnName);
            return call_user_func_array([$query,'where'],$arguments);
        }
        return call_user_func_array([$query,$name],$arguments);
    }


}