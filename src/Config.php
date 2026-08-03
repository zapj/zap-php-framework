<?php

namespace zap;

use zap\util\ZArray;

class Config
{

    protected static $storage;

    /** @var array 已加载的配置文件名缓存 */
    protected static array $loaded = [];

    /**
     * @return ZArray
     */
    public static function instance(): ZArray
    {
        if(is_null(static::$storage)){
            static::$storage = new ZArray();
        }
        return static::$storage;
    }

    public static function load($name) {
        if (isset(static::$loaded[$name])) {
            return;
        }
        $filename = config_path("{$name}.php");
        if(is_file($filename)){
            $data = include $filename;
            static::instance()->replace([$name=>$data]);
            static::$loaded[$name] = true;
        }
    }

    public static function get($name,$default = null) {
        $keys = explode('.', $name);
        if (isset($keys[0]) && !static::instance()->has($keys[0])) {
            static::load($keys[0]);
        }
        return static::instance()->get($name,$default);
    }

    public static function set($name,$value) {
        static::instance()->set($name,$value);
    }

    /**
     * 清除配置缓存，强制下次重新加载配置文件
     */
    public static function clearCache(): void
    {
        static::$loaded = [];
        static::$storage = null;
    }

}