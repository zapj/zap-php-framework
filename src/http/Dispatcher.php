<?php

namespace zap\http;


class Dispatcher implements Middleware
{

    public $baseUrl;

    public $currentUri;

    /**
     * @var \zap\http\Router
     */
    public $router;

    public $controller;

    public $method;

    public $options;

    public $hasParams = false;

    public function __construct($options)
    {
        $default_params = ['controller' => 'index', 'action' => 'index'];
        $this->options = array_merge($options, $default_params);
        $this->controller = $this->options['controller'];
        $this->method = $this->options['action'];
    }

    public function handle()
    {
        $this->parseUrlPath();

        if ( ! class_exists($this->controller)) {
            $this->router->trigger404();
            return false;
        }

        try {
            app()->controller = new $this->controller();
            if (method_exists(app()->controller, '_invoke')) {
                call_user_func_array([app()->controller, '_invoke'],
                    ['method' => $this->method, 'params' => $this->router->params]);
            }else{
                if (method_exists(app()->controller, $this->method)) {
                    call_user_func_array([app()->controller, $this->method], $this->router->params);
                } else {
                    throw new \Exception('not found');
                }
            }
        } catch (\Exception $e) {
            if (method_exists(app()->controller, '_notfound')) {
                call_user_func_array([app()->controller, '_notfound'],
                    ['method' => $this->method,
                     'params' => $this->router->params]);
            } else {
                $this->router->trigger404();
            }
        }
        return false;
    }

    private function parseUrlPath()
    {
        $namespace = $this->options['namespace'];
        $routeBase = rtrim($this->router->currentRoute['pattern'], '.*');
        $url = trim(
            preg_replace("#$routeBase#iu", '', $this->currentUri, 1), '/ '
        );
        $segments = preg_split(
            '#/#', trim($url, '/'), -1, PREG_SPLIT_NO_EMPTY
        );
        $controller = array_shift($segments);
        $method = array_shift($segments);
        if ($controller != null
            && preg_match(
                '/^[a-z]+[-_]{0,3}\w+$/i', $controller
            )
        ) {
            $this->controller = $controller;
            if ($method != null
                && preg_match(
                    '/^[a-z]+[-_]{0,3}\w+$/i', $method
                )
            ) {
                $this->method = $method;
            } elseif ($method != null) {
                array_unshift($segments, $method);
            }
        } else {
            array_unshift($segments, $controller);
        }

        $this->hasParams = (count($segments) == 0) ? false : true;

        $this->router->params = $segments;
        $this->controller = $namespace.'\\'.str_replace(
                ' ', '',
                ucwords(str_replace(['-', '_'], ' ', $this->controller))
            );
    }


}