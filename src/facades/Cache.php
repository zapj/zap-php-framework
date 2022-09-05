<?php

namespace zap\facades;

use zap\cache\CacheFactory;
use zap\cache\FileCache;
use zap\cache\RedisCache;

/**
 * @method static get(string $key, $default = null)
 * @method static set($key, $value, $ttl = null)
 * @method static delete(string $key)
 * @method static clear()
 * @method static getMultiple($keys, $default = null)
 * @method static setMultiple($values, $ttl = null)
 * @method static deleteMultiple($keys)
 * @method static has($key)
 *
 */
class Cache extends Facade
{


    protected static $cacheDrivers = [];

    /**
     * @param $name
     *
     * @return \zap\cache\CacheInterface
     * @throws \zap\cache\CacheException
     */
    public static function store($name){
        if(isset(static::$cacheDrivers[$name])){
            return static::$cacheDrivers[$name];
        }
        static::$cacheDrivers[$name] = static::make($name);
        return static::$cacheDrivers[$name];
    }

    /**
     * @param $name
     *
     * @return \zap\cache\CacheInterface
     * @throws \zap\cache\CacheException
     */
    private static function make($name){
        switch ($name){
            case 'redis':
                return new RedisCache( config('cache.redis'));
            case 'file':
            default:
                $cacheDir = config('cache.file.path',storage_path('/cache'));
                return new FileCache(['cacheDir'=>$cacheDir]);
        }
    }

    /**
     * @return \zap\cache\CacheInterface
     */
    protected static function getInstance()
    {
        if(app()->_cache === null){
            app()->_cache = static::store(config('cache.default','file'));
        }

        return app()->_cache;
    }

}