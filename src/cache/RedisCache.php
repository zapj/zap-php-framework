<?php

namespace zap\cache;

class RedisCache implements CacheInterface
{

    /**
     * @var object|\Redis|null
     */
    protected $redis;

    public function __construct($options = null)
    {
        $default = ['client'=>'redis'];
        $options += $default;

        $redisClass = '\Redis';
        if($options['client'] == 'predis'){
            $redisClass = '\Predis\Client';
            $reflect = new \ReflectionClass($redisClass);
            $this->redis = $reflect->newInstanceArgs($options['params']);
        }else{
            $this->redis = new $redisClass();
            call_user_func_array([$this->redis,'connect'],$options['params']);
        }
    }

    public function get($key, $default = null, $ttl = null)
    {
        $value = $this->redis->get($key);

        if($value !== false){
            return $value;
        }

        if(is_callable($default)){
            $data = $default();
            if($data !== null){
                $this->set($key,$data,$ttl);
                return $data;
            }
        }

        return $default;
    }

    public function set($key, $value, $ttl = null) {

        if($ttl === null){
            return $this->redis->set($key, $value);
        }

        return $this->redis->setex($key, $ttl, $value);
    }

    public function delete($key)
    {
        return (bool)$this->redis->del($key);
    }

    public function clear()
    {
        return $this->redis->flushDB();
    }

    public function getMultiple($keys, $default = null)
    {
        $items = [];
        foreach ($keys as $key){
            $items[$key] = $this->get($key, is_array($default) ? $default[$key] : $default);
        }
        return $items;
    }

    public function setMultiple($values, $ttl = null) {
        foreach($values as $key => $value){
            $this->set($key,$value,$ttl);
        }
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key){
            $this->delete($key);
        }
    }

    public function has($key)
    {
        return (bool)$this->get($key);
    }


}