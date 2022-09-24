<?php

namespace zap;

use PDO;
use PDOException;
use zap\db\Expr;
use zap\db\Query;
use zap\db\ZPDO;
use zap\util\Arr;

/**
 * @method static upsert($table, $data, $duplicate = null )
 * @method static insert($table, $data)
 * @method static replace($table, $data)
 * @method static update($table, $data, $conditions = '', $params = array())
 * @method static delete($table, $conditions = '', $params = array())
 * @method static count($table, $conditions = '', $params = array())
 * @method static keyPair($table, $columns, $conditions = '', $params = array())
 * @method static rowCount()
 * @method static toSnakeCase($name)
 * @method static prepareSQL($sql)
 * @method static quoteColumn($columnName)
 * @method static quoteTable($table)
 * @method static setFetchMode($mode)
 * @method static setAutoCommit($value)
 * @method static getAutoCommit()
 * @method static buildParams($array,$name)
 * @method static statement($statement, $params = [])
 * @method static renameTable($oldName, $newName)
 * @method static dropTable($table)
 * @method static truncateTable($table)
 */
class DB
{

    /**
     * @var array 连接池
     */
    protected static $conn_pool = [];

    /**
     * @var string 默认连接名字
     */
    protected static $default_name;

    /**
     * @param $default_name string connection name
     * @return \zap\db\ZPDO
     */
    public static function connect($default_name = null)
    {
        if(is_null(static::$default_name)){
            static::$default_name = config("database.default");
        }
        if(is_null($default_name)){
            $default_name = static::$default_name;
        }
        if(isset(static::$conn_pool[$default_name])){
            return static::$conn_pool[$default_name];
        }

        $opt  = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES   => FALSE,
        );
        $config = config("database.connections.{$default_name}");
        if(empty($config)){
            throw new \Exception("could not find database config : {$default_name},Please check config/database.php");
        }
        $db_driver = Arr::get($config,'driver','mysql');
        $db_host = Arr::get($config,'host','localhost');
        $db_name = Arr::get($config,'database');
        $db_user = Arr::get($config,'username');
        $db_passwd = Arr::get($config,'password');
        $db_charset = Arr::get($config,'charset','utf8');
        $db_collation = Arr::get($config,'collation','');
        $db_prefix = Arr::get($config,'prefix');
        $db_port = Arr::get($config,'port',3306);
        $unix_socket = Arr::get($config,'unix_socket');
        if($unix_socket){
            $dsn = sprintf('%s:unix_socket=%s;dbname=%s;;charset=%s',$db_driver,$unix_socket,$db_name,$db_charset);
        }else{
            $dsn = sprintf('%s:host=%s;dbname=%s;port=%d;charset=%s',$db_driver,$db_host,$db_name,$db_port,$db_charset);
        }

        static::$conn_pool[$default_name] = new ZPDO($dsn, $db_user, $db_passwd, $opt);
        static::$conn_pool[$default_name]->setTablePrefix($db_prefix);
        if($db_driver == 'mysql'){
            $db_collation = empty($db_collation) ? '' : " collate {$db_collation} ";
            static::$conn_pool[$default_name]->exec("set names {$db_charset} {$db_collation}");
        }
        return static::$conn_pool[$default_name];
    }

    /**
     * @param  string  $default_name
     *
     * @return \zap\db\ZPDO
     */
    public static function getPDO($default_name = null)
    {
        return static::connect($default_name);
    }

    public static function quote($value)
    {
        $pdo = static::connect(static::$default_name);
        if(is_array($value)){
            return array_map(function($value) use ($pdo){
                return $pdo->quote($value);
                },$value);
        }
        return $pdo->quote($value);
    }

    public static function prepare($statement, $options = [])
    {
        $pdo = static::connect(static::$default_name);
        return $pdo->prepare($pdo->prepareSQL($statement),$options);
    }

    /**
     * @param $statement
     *
     * @return false|int
     */
    public static function exec($statement)
    {
        $pdo = static::connect(static::$default_name);
        return $pdo->exec($pdo->prepareSQL($statement));
    }

    /**
     * @param $statement
     * @param $params
     *
     * @return false|\zap\db\Statement
     */
    public static function query($statement,$params = [])
    {
        $stm = static::prepare($statement);
        $stm->execute($params);
        return $stm;
    }

    public static function scalar($statement,$params = []){
        $stm = static::prepare($statement);
        $stm->execute($params);
        return $stm->fetchColumn();
    }

    public static function getAll($statement,$params = [])
    {
        $stm = static::prepare($statement);
        $stm->execute($params);
        return $stm->fetchAll();
    }

    public static function getOne($statement,$params = [])
    {
        $stm = static::prepare($statement);
        $stm->execute($params);
        return $stm->fetch();
    }

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([static::connect(static::$default_name),$name],$arguments);
    }

    public static function table($table,$alias = null){
        $query = new Query(static::connect(static::$default_name));
        return $query->from($table,$alias);
    }

    /**
     * @param $callback \Closure
     *
     * @return bool 事务成功返回true
     */
    public static function transaction($callback,$connection = null){
        try{
            static::connect($connection)->beginTransaction();
            if(is_callable($callback)){
                $callback();
            }
            return static::connect($connection)->commit();
        }catch (PDOException $exception){
            static::connect($connection)->rollBack();
            return false;
        }
    }

    public static function connection($connection = null , $callback = null){

        $default_name = static::$default_name;
        static::$default_name = $connection;
        if(is_callable($callback)){
            $callback();
        }
        static::$default_name = $default_name;
    }

    public static function raw($value){
        return Expr::make($value);
    }

    public static function beginTransaction($connection = null)
    {
        $pdo = static::connect($connection);
        return $pdo->beginTransaction();
    }

    public static function commit($connection = null)
    {
        $pdo = static::connect($connection);
        return $pdo->commit();
    }

    public static function rollback($connection = null)
    {
        $pdo = static::connect($connection);
        return $pdo->rollBack();
    }


}