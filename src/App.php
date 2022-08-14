<?php

namespace zap;

use ArrayObject;
use zap\http\Request;
use zap\http\Router;
use zap\util\Arr;
use zap\http\Dispatcher;
use ReflectionClass;

define('ZAP_SRC', realpath(__DIR__));

class App
{

    public const VERSION = '0.0.1';


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
        $this->router = new Router();
        //加载路由配置
        $route_file = $this->configPath('/route.php');
        if(is_file($route_file)){
            require_once $route_file;
        }
        return $this->router;
    }

    public function getLogger($name = 'zap'){
        if(!class_exists('\Monolog\Logger')){
            throw new \Exception('Class not found [\Monolog\Logger]');
        }
        if(isset($this->logger[$name])){
            return $this->logger[$name];
        }
        $this->logger[$name] = new \Monolog\Logger($name);
        $handlerClass = config("log.{$name}.handler");
        $params = config("log.{$name}.params",[]);
        if(!class_exists($handlerClass)){
            throw new \Exception('Class not found ['.$handlerClass.']');
        }
        $class = new ReflectionClass($handlerClass);
        if(!($class->isSubclassOf('\Monolog\Handler\HandlerInterface'))){
            throw new \Exception('['.$handlerClass.'] must implement \Monolog\Handler\HandlerInterface interface ');
        }
        $handler = $class->newInstanceArgs($params);

        $this->logger[$name]->pushHandler($handler);
        return $this->logger[$name];
    }
}