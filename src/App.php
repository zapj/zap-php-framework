<?php

namespace zap;

use ArrayObject;
use ReflectionClass;
use zap\http\Router;
use zap\util\Arr;

define('ZAP_SRC', realpath(__DIR__));

class App implements \ArrayAccess
{

    public const VERSION = '1.0.2';


    protected $rootPath;

    protected $basePath;

    protected $baseUrl;

    protected $logger = [];

    protected static $instance;

    protected static $container;

    public function __construct($basePath)
    {
        if ($this->isWin()) {
            $this->basePath = str_replace('\\', '/', $basePath);
            $this->rootPath = str_replace('\\', '/', Arr::get($_SERVER, 'DOCUMENT_ROOT', $basePath));
        } else {
            $this->basePath = $basePath;
            $this->rootPath = Arr::get($_SERVER, 'DOCUMENT_ROOT', $basePath);
        }
        self::$instance = $this;
        if(config('config.debug',false)){
            error_reporting(E_ALL ^ E_NOTICE);
        }else{
            error_reporting(0);
        }
        ErrorHandler::register();
        $this->prepare();


    }

    /**
     * @return mixed
     */
    public static function instance()
    {
        if ( ! isset(self::$instance)) {
            self::$instance = new App(realpath('../../../'));
        }

        return self::$instance;
    }


    public function baseUrl($path = null)
    {
        if ($path) {
            return $this->baseUrl.$path;
        }

        return $this->baseUrl;
    }

    public function rootPath($path = null)
    {
        if ($path) {
            return $this->rootPath.$path;
        }

        return $this->rootPath;
    }

    public function basePath($path = null)
    {
        if ($path) {
            return $this->basePath.$path;
        }

        return $this->basePath;
    }

    public function configPath($filename = null)
    {
        if ($filename) {
            return $this->basePath.'/config'.$filename;
        }

        return $this->basePath.'/config';
    }

    public function assetsPath($filename = null)
    {
        if ($filename) {
            return $this->basePath.'/assets'.$filename;
        }

        return $this->basePath.'/assets';
    }

    public function storagePath($filename = null)
    {
        if ($filename) {
            return $this->basePath.'/storage'.$filename;
        }

        return $this->basePath.'/storage';
    }

    public function resourcesPath($filename = null)
    {
        if ($filename) {
            return $this->basePath.'/resources'.$filename;
        }

        return $this->basePath.'/resources';
    }

    public function themesPath($filename = null)
    {
        if ($filename) {
            return $this->basePath.'/themes'.$filename;
        }

        return $this->basePath.'/themes';
    }

    public function isWin()
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    public function isConsole()
    {
        return php_sapi_name() == 'cli';
    }

    protected function prepare()
    {
        $this->baseUrl = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) ;
        static::$container = new ArrayObject([], ArrayObject::ARRAY_AS_PROPS);


        define('ROOT_PATH',$this->rootPath);
        define('BASE_PATH',$this->basePath);

    }

    public function __get($key){
        if(isset(static::$container[$key])){
            return static::$container[$key];
        }
        return null;
    }

    public function __set($key,$value){
        static::$container[$key] = $value;
    }

    public function __has($key){
        return isset(static::$container[$key]);
    }



    /**
     * @return \zap\http\Router
     */
    public function createRouter(){
        return Router::create();
    }

    public function run(){
        $router = Router::create();
        return $router->dispatch();
    }


    public function getLogger($name = 'app'){
        $name = config('log.default',$name);
        if(isset($this->logger[$name])){
            return $this->logger[$name];
        }

        if(!class_exists('\Monolog\Logger')){
            throw new \Exception('Monolog is not installed,  Please run \'composer require monolog/monolog\'');
        }

        try{
            $this->logger[$name] = new \Monolog\Logger($name);
            $handlerClass = config("log.{$name}.handler");
            $params = config("log.{$name}.params",[]);
            $class = new ReflectionClass($handlerClass);
            if(!($class->isSubclassOf('\Monolog\Handler\HandlerInterface'))){
                throw new \Exception('['.$handlerClass.'] must implement \Monolog\Handler\HandlerInterface interface ');
            }
            $handler = $class->newInstanceArgs($params);

            $this->logger[$name]->pushHandler($handler);
        }catch (\ReflectionException $e){
            throw new \Exception('Class not found ['.$handlerClass.']');
        }

        return $this->logger[$name];
    }

    /**
     * Whether a offset exists
     *
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param  mixed  $offset  <p>
     *                         An offset to check for.
     *                         </p>
     *
     * @return bool true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return isset(static::$container[$offset]);
    }

    /**
     * Offset to retrieve
     *
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param  mixed  $offset  <p>
     *                         The offset to retrieve.
     *                         </p>
     *
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return static::$container[$offset];
    }

    /**
     * Offset to set
     *
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param  mixed  $offset  <p>
     *                         The offset to assign the value to.
     *                         </p>
     * @param  mixed  $value   <p>
     *                         The value to set.
     *                         </p>
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        static::$container[$offset] = $value;
    }

    /**
     * Offset to unset
     *
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param  mixed  $offset  <p>
     *                         The offset to unset.
     *                         </p>
     *
     * @return void
     */
    public function offsetUnset($offset){
        unset(static::$container[$offset]);
    }

    public function has($name){
        return $this->offsetExists($name);
    }

    public function get($name){
        return $this->offsetGet($name);
    }

    public function set($name,$value){
        $this->offsetSet($name,$value);
    }
}