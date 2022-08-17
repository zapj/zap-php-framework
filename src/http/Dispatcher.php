<?php

namespace zap\http;



use zap\util\Arr;

class Dispatcher implements Middleware
{

    public $basePath;

    public $currentUri;

    /**
     * @var \zap\http\Router
     */
    public $router;

    public $controller;
    public $method;

    public $options;

    public function __construct($options)
    {
        $default_params = ['controller'=>'index','action'=>'index'];
        $this->options = array_merge($options,$default_params);
    }

    public function handle()
    {
        $namespace = $this->options['namespace'];
        $routeBase = rtrim($this->router->currentRoute['pattern'],'.*');
        $url = trim(preg_replace("#$routeBase#iu",'',$this->currentUri,1),'/ ');
        $segments = preg_split('#/#', trim($url, '/'), NULL, PREG_SPLIT_NO_EMPTY);
        $this->controller = array_shift($segments) ?: 'Index';
        $this->method = array_shift($segments) ?: 'index';
        $this->hasPathParam = count($segments) == 0 ? false : true;
        $this->router->params = $segments;
        $this->controller = $namespace .'\\'. str_replace(' ','',ucwords(str_replace(['-','_'],' ',$this->controller)));
        if(!class_exists($this->controller)){
            echo "Controller [{$this->controller}] not found";
            return false;
        }

        try {
            $reflectedMethod = new \ReflectionMethod($this->controller, $this->method);
            if ($reflectedMethod->isPublic() && (!$reflectedMethod->isAbstract())) {
                if ($reflectedMethod->isStatic()) {
                    forward_static_call_array(array($this->controller, $this->method), []);
                } else {
                    if (is_string($this->controller)) {
                        $controller = new $this->controller();
                    }
                    $reflectedMethod->invokeArgs($controller,$this->router->params );
                }
            }
        } catch (\ReflectionException $reflectionException) {
            echo "ReflectionException : " . $reflectionException->getMessage();
            return false;
        }
        return false;
    }



}