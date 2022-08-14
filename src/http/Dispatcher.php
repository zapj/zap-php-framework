<?php

namespace zap\http;



class Dispatcher
{

    protected $basePath;

    protected $currentUri;

    /**
     * @var \zap\http\Router
     */
    private $router;

    public function __construct($router)
    {
        $this->router = $router;
        $this->basePath = $router->getBasePath();
        $this->currentUri = $router->getCurrentUri();
    }

    public function dispatch($options){
        $default_params = ['controller'=>'index','action'=>'index'];
        $params = array_merge($options,$default_params);
        $namespace = $params['namespace'];
        $routeBase = rtrim($this->router->currentRoute['pattern'],'.*');
        $url = trim(preg_replace("#$routeBase#iu",'',$this->currentUri,1),'/ ');
        //        $segments = explode('/',$url);
        $segments = preg_split('#/#', $url, NULL, PREG_SPLIT_NO_EMPTY);
        $classSegments = [];
        foreach($segments as $segment){
            if(!is_string($segment)) break;
            $classSegments[] = str_replace(' ','',ucwords(str_replace(['-','_'],'',$segment)));
        }

        $i = count($classSegments);
        $i = $i < 3 ? $i : 3;
        $args = [];
        $found = 0;
        while($i > 0){

            $ns_prefix = strtolower(join('\\',array_slice($classSegments,0,intval($i-1 ))));
            $controller = $classSegments[$i-1];
            $controller = rtrim($namespace . "\\" . $ns_prefix,'\\') .'\\'. $controller;
            if(class_exists($controller)){
                $method = isset($classSegments[$i]) ? $classSegments[$i] : 'index';
                $args = array_slice($segments,$i+1);
                $found = 1;
                break;
            }
            $i--;

        }
        if($found == 0 && count($classSegments) == 0){
            $controller = $namespace . '\Index';
            $method = 'index';
        }
        if(!class_exists($controller)){
            echo "Controller {$controller} not found";
            return;
        }


        try {
            $reflectedMethod = new \ReflectionMethod($controller, $method);
            if ($reflectedMethod->isPublic() && (!$reflectedMethod->isAbstract())) {
                if ($reflectedMethod->isStatic()) {
                    forward_static_call_array(array($controller, $method), []);
                } else {
                    if (is_string($controller)) {
                        $controller = new $controller();
                    }
                    //                    call_user_func_array(array($controller, $method), $args);
                    $reflectedMethod->invokeArgs($controller,$args);
                }
            }
        } catch (\ReflectionException $reflectionException) {
            echo "ReflectionException : " . $reflectionException->getMessage();
            return;
        }
    }




}