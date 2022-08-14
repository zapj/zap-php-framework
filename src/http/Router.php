<?php

namespace zap\http;


use Exception;
use ReflectionMethod;
use zap\App;
use zap\util\Arr;
use zap\util\Str;

class Router
{

    const NOT_FOUND = 1;
    const FOUND = 2;
    /**
     * @var array 路由规则
     */
    private $routes = [];

    private $middlewares = [];

    /**
     * @var array [object|callable] The function to be executed when no route has been matched
     */
    protected $notFoundCallback = [];

    /**
     * @var string Current base route, used for (sub)route mounting
     */
    private $baseRoute = '';

    private $requestMethod = 'GET';

    /**
     * @var string The Server Base Path for Router Execution
     */
    private $serverBasePath;

    public $currentRoute;

    public $currentUri;

    const ALL_METHOD = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';

    /**
     * @return string
     */
    public function __construct()
    {
        $this->requestMethod = Request::method();
        $this->currentUri = $this->getCurrentUri();
    }

    /**
     * @return \zap\http\Router
     */
    public static function create(){
        app()->router = new Router();
        //加载路由配置
        $route_file = config_path('/route.php');
        if(is_file($route_file)){
            require_once $route_file;
        }
        return app()->router;
    }


    public function filter($methods, $pattern, $fn, $options = [])
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        foreach (explode('|', $methods) as $method) {
            $this->middlewares[$method][] = array(
                'pattern' => $pattern,
                'fn' => $fn,
                'options' => $options
            );
        }
    }
    
    public function match($methods, $pattern, $fn)
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        foreach (explode('|', $methods) as $method) {
            $this->routes[$method][] = array(
                'pattern' => $pattern,
                'fn' => $fn,
            );
        }
    }

    public function any($pattern, $action)
    {
        $this->match('GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD', $pattern, $action);
    }

    public function get($pattern, $action)
    {
        $this->match('GET', $pattern, $action);
    }

    public function post($pattern, $action)
    {
        $this->match('POST', $pattern, $action);
    }

    public function patch($pattern, $action)
    {
        $this->match('PATCH', $pattern, $action);
    }

    public function delete($pattern, $action)
    {
        $this->match('DELETE', $pattern, $action);
    }

    public function put($pattern, $action)
    {
        $this->match('PUT', $pattern, $action);
    }

    public function options($pattern, $action)
    {
        $this->match('OPTIONS', $pattern, $action);
    }


    public function group($baseRoute, $action)
    {
        $curBaseRoute = $this->baseRoute;

        $this->baseRoute .= $baseRoute;

        call_user_func($action);

        $this->baseRoute = $curBaseRoute;
    }

    public function dispatch($callback = null)
    {
        // middlewares
        if (isset($this->middlewares[$this->requestMethod])
            && $this->handle($this->middlewares[$this->requestMethod]) === FALSE
        ) {
            if ($callback && is_callable($callback)) {
                $callback();
            }

            if ($this->requestMethod == 'HEAD') {
                ob_end_clean();
            }
            return true;
        }
        // Handle all routes
        $numHandled = 0;
        if (isset($this->routes[$this->requestMethod])) {
            $numHandled = $this->handle($this->routes[$this->requestMethod], true);
        }

        // If no route was handled, trigger the 404 (if any)
        if ($numHandled === 0) {
            if (isset($this->routes[$this->requestMethod])) {
                $this->trigger404($this->routes[$this->requestMethod]);
            }
        } // If a route was handled, perform the finish callback (if any)
        elseif ($callback && is_callable($callback)) {
            $callback();
        }

        // If it originally was a HEAD request, clean up after ourselves by emptying the output buffer
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_end_clean();
        }
        // Return true if a route was handled, false otherwise
        return $numHandled !== 0;
    }

    public function setNotFound($match_fn, $func = null)
    {
        if (!is_null($func)) {
            $this->notFoundCallback[$match_fn] = $func;
        } else {
            $this->notFoundCallback['/'] = $match_fn;
        }
    }

    public function trigger404($match = null){

        // Counter to keep track of the number of routes we've handled
        $numHandled = 0;

        // handle 404 pattern
        if (count($this->notFoundCallback) > 0)
        {
            // loop fallback-routes
            foreach ($this->notFoundCallback as $route_pattern => $route_callable) {

                // matches result
                $matches = [];

                // check if there is a match and get matches as $matches (pointer)
                $is_match = $this->patternMatches($route_pattern, $this->getCurrentUri(), $matches, PREG_OFFSET_CAPTURE);

                // is fallback route match?
                if ($is_match) {

                    // Rework matches to only contain the matches, not the orig string
                    $matches = array_slice($matches, 1);

                    // Extract the matched URL parameters (and only the parameters)
                    $params = array_map(function ($match, $index) use ($matches) {

                        // We have a following parameter: take the substring from the current param position until the next one's position (thank you PREG_OFFSET_CAPTURE)
                        if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                            if ($matches[$index + 1][0][1] > -1) {
                                return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                            }
                        } // We have no following parameters: return the whole lot

                        return isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
                    }, $matches, array_keys($matches));

                    $this->invoke($route_callable);

                    ++$numHandled;
                }
            }
        }
        if (($numHandled == 0) && (isset($this->notFoundCallback['/']))) {
            $this->invoke($this->notFoundCallback['/']);
        } elseif ($numHandled == 0) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        }
    }

    private function patternMatches($pattern, $uri, &$matches, $flags)
    {
        $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);
        return boolval(preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_OFFSET_CAPTURE));
    }

    private function handle($routes, $quitAfterRun = false)
    {
        $numHandled = 0;

        foreach ($routes as $route) {

            // get routing matches
            $is_match = $this->patternMatches($route['pattern'], $this->currentUri, $matches, PREG_OFFSET_CAPTURE);

            // is there a valid match?
            if ($is_match) {

                // Rework matches to only contain the matches, not the orig string
                $matches = array_slice($matches, 1);

                $this->currentRoute = $route;
                // Extract the matched URL parameters (and only the parameters)
                $params = array_map(function ($match, $index) use ($matches) {

                    // We have a following parameter: take the substring from the current param position until the next one's position (thank you PREG_OFFSET_CAPTURE)
                    if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                        if ($matches[$index + 1][0][1] > -1) {
                            return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                        }
                    } // We have no following parameters: return the whole lot

                    return isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
                }, $matches, array_keys($matches));

                // Call the handling function with the URL parameters if the desired input is callable
                if($this->invoke($route['fn'], $params, $route['options']) === false){
                    return false;
                }
                ++$numHandled;

                if ($quitAfterRun) {
                    break;
                }
            }
        }

        return $numHandled;
    }

    private function invoke($fn, $params = array(), $options = [])
    {
        if (is_callable($fn)) {
            call_user_func_array($fn, $params);
        }
        else if ($fn == Dispatcher::class) {
            app()->dispatcher = new Dispatcher($this);
            app()->dispatcher->dispatch($options);
            return false;
        }
        elseif (stripos($fn, '@') !== false) {
            [$controller, $method] = explode('@', $fn);

            try {
                $reflectedMethod = new \ReflectionMethod($controller, $method);
                if ($reflectedMethod->isPublic() && (!$reflectedMethod->isAbstract())) {
                    if ($reflectedMethod->isStatic()) {
                        forward_static_call_array(array($controller, $method), $params);
                    } else {
                        if (\is_string($controller)) {
                            $controller = new $controller();
                        }
                        call_user_func_array(array($controller, $method), $params);
                    }
                }
            } catch (\ReflectionException $reflectionException) {
            }
        }
        return true;
    }

    public function getCurrentUri()
    {
        $uri = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen($this->getBasePath()));

        if (strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }else if(strstr($uri, '#')){
            $uri = substr($uri, 0, strpos($uri, '#'));
        }
        return '/' . trim($uri, '/');
    }


    public function getBasePath()
    {
        if ($this->serverBasePath === null) {
            $this->serverBasePath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
        }

        return $this->serverBasePath;
    }

    public function setBasePath($serverBasePath)
    {
        $this->serverBasePath = $serverBasePath;
    }
}