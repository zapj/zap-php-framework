<?php

namespace zap\zdb;

use PDO;
use PDOException;

class ZPDO extends PDO
{

    protected $table_prefix = 'z_';

    protected $driver;

    public function __construct($dsn, $username = null, $password = null,
        $options = null
    ) {
        parent::__construct($dsn, $username, $password, $options);
        $this->driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function setTablePrefix($prefix){
        $this->table_prefix = $prefix;
    }

    public function last_id($name = null){
        return $this->lastInsertId($name);
    }

    public function prepare_table_prefix($sql){
        if($this->table_prefix){
            return preg_replace_callback(
                '/(\\{(%?[\w\-\. ]+%?)\\}|\\[([\w\-\. ]+)\\])/',
                function ($matches) {
                    if (isset($matches[3])) {
                        return $this->quoteColumn($matches[3]);
                    } else {
                        return $this->quoteTable($matches[2]);
                    }
                }, $sql
            );
        }
        return $sql;
    }

    public function quoteColumn($columnName) {
        $colAlias = explode('.', $columnName);
        if (is_array($colAlias) && count($colAlias) == 2) {
            return $this->quoteColumn($colAlias[0]) . '.' . $this->quoteColumn($colAlias[1]);
        }
        switch ($this->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
            case 'mariadb':
                return "`$columnName`";
            case 'mssql':
                return "[$columnName]";
            case 'pssql':
                return '"' . $columnName . '"';
            default:
                return $columnName;
        }
    }

    public function quoteTable($table) {
        $table = $this->table_prefix . $table;
        switch ($this->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
            case 'mariadb':
                return '`' . $table . '`';
            case 'mssql':
                return "[$table]";
            case 'pssql':
                return '"' . $table . '"';
            default:
                return $table;
        }
    }

    public function info(): array
    {
        $key_names = [
            'server'     => PDO::ATTR_SERVER_INFO,
            'driver'     => PDO::ATTR_DRIVER_NAME,
            'client'     => PDO::ATTR_CLIENT_VERSION,
            'version'    => PDO::ATTR_SERVER_VERSION,
            'connection' => PDO::ATTR_CONNECTION_STATUS,
        ];

        foreach ($key_names as $key => $value) {
            try {
                $key_names[$key] = $this->getAttribute($value);
            } catch (PDOException $e) {
                $key_names[$key] = $e->getMessage();
            }
        }

        return $key_names;
    }




}