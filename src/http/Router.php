<?php

namespace zap\http;


use Exception;
use ReflectionMethod;
use zap\App;
use zap\util\Arr;
use zap\util\Str;
use zap\view\ZView;

class Router
{

    const NOT_FOUND = 1;
    const FOUND = 2;

    /** @var string 路由缓存文件路径 */
    const CACHE_FILE = '/var/cache/routes.php';

    /**
     * @var array 路由规则
     */
    private $routes = [];

    private $middlewares = [];

    protected $notFoundCallback = [];

    private $baseRoute = '';

    private $requestMethod = 'GET';

    public $baseUrl;

    public $currentRoute;

    public $currentUri;

    public $params = [];

    private $defaultMethods = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';

    protected $isAjax = false;
    /**
     * @return string
     */
    public function __construct()
    {
        $this->requestMethod = Request::method();
        $this->currentUri = $this->getCurrentUri();
        $this->isAjax = Request::isAjax();
    }

    /**
     * @return Router
     */
    public static function create(): Router
    {
        app()->router = new Router();
        // 加载路由配置
        $route_file = config_path('route.php');
        if(is_file($route_file)){
            // 尝试加载缓存
            if (static::loadCachedRoutes(app()->router)) {
                return app()->router;
            }
            require_once $route_file;
        }
        return app()->router;
    }


    public function filter($pattern, $fn, $options = []): Router
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        $this->middlewares[] = array(
            'pattern' => $pattern,
            'fn' => $fn,
            'options' => $options
        );
        return $this;
    }

    public function prefix($pattern, $fn, $options = [])
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;
        $this->middlewares[] = array(
            'pattern' => $pattern,
            'fn' => $fn,
            'options' => $options
        );
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
        $this->match($this->defaultMethods, $pattern, $action);
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

    public function dispatch()
    {
        if ($this->handleMiddlewares($this->middlewares) === FALSE) {
            return true;
        }

        $found = false;
        if (isset($this->routes[$this->requestMethod])) {
            $found = $this->handle($this->routes[$this->requestMethod]);
        }

        if (!$found) {
            if (isset($this->routes[$this->requestMethod])) {
                $this->trigger404($this->routes[$this->requestMethod]);
            }
        }
        if ($this->requestMethod == 'HEAD') {
            ob_end_clean();
        }
        return $found;
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

        $numHandled = 0;

        if (count($this->notFoundCallback) > 0)
        {
            foreach ($this->notFoundCallback as $route_pattern => $route_callable) {

                $matches = [];

                $is_match = $this->patternMatches($route_pattern, $this->getCurrentUri(), $matches, PREG_OFFSET_CAPTURE);

                if ($is_match) {

                    $matches = array_slice($matches, 1);

                    $params = [];
                    for ($i = 0; $i < count($matches); $i=$i+2) {
                        $params[]= $matches[$i+1][0][0] ?? null;
                    }

                    $this->invoke($route_callable,$params);

                    ++$numHandled;
                }
            }
        }
        if (($numHandled == 0) && (isset($this->notFoundCallback['/']))) {
            $this->invoke($this->notFoundCallback['/']);
        } elseif ($numHandled == 0) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            if($this->isAjax){
                Response::json(['code'=>404,'msg'=>'404 Not Found']);
            }
            ZView::render(ZAP_SRC.'/resources/views/http/404.html');
        }
        exit(0);
    }

    private function patternMatches($pattern, $uri, &$matches, $flags)
    {
        $pattern = preg_replace('/\/{\w+:(.*?)}/', '/($1)', $pattern);
        $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);
        return boolval(preg_match_all('#^' . $pattern . '$#', $uri, $matches, $flags));
    }

    private function handleMiddlewares($routes){
        foreach ($routes as $route) {
            $is_match = boolval(preg_match("#^" . $route['pattern'] . "#i", $this->currentUri));
            if(!$is_match){
                continue;
            }
            $is_match && $this->currentRoute = $route;
            if ($is_match && $this->invokeMiddleware($route['fn'],$route['options']) === false) {
                if ($this->requestMethod == 'HEAD') {
                    ob_end_clean();
                }
                return false;
            }
        }
        return true;
    }

    private function handle($routes): bool
    {
        $is_match = false;
        foreach ($routes as $route) {
            $is_match = $this->patternMatches($route['pattern'], $this->currentUri, $matches, PREG_OFFSET_CAPTURE);
            if ($is_match) {

                $matches = array_slice($matches, 1);
                $this->currentRoute = $route;
                $params = [];
                for ($i = 0; $i < count($matches); $i=$i+2) {
                    $params[]= $matches[$i+1][0][0] ?? null;
                }

                $this->invoke($route['fn'], $params, $route['options']);



                break;
            }
        }

        return $is_match;
    }

    private function invoke($fn, $params = array(), $options = [])
    {
        if (is_callable($fn)) {
            call_user_func_array($fn, $params);
        }
        elseif (stripos($fn, '@') !== false) {
            [$controller, $method] = explode('@', $fn);
            try {
                $reflectedMethod = new \ReflectionMethod($controller, $method);
                if ($reflectedMethod->isPublic() && (!$reflectedMethod->isAbstract())) {
                    if ($reflectedMethod->isStatic()) {
                        forward_static_call_array(array($controller, $method), $params);
                    } else {
                        $controller = new $controller();
                        call_user_func_array(array($controller, $method), $params);
                    }
                }
            } catch (\ReflectionException $e) {
                if(call_user_func_array(array($controller, '_notfound'), $params) === NULL){
                    $this->trigger404();
                }
            }
        }else if(class_exists($fn)){
            $controller = new $fn();
            if(is_array($params) && isset($params[0]) && method_exists($controller,$params[0])){
                call_user_func_array([$controller,$params[0]],array_slice($params,1));
            }
        }
    }

    private function invokeMiddleware($fn, $options = []): bool
    {
        $ret = true;
        if (is_callable($fn)) {
            $ret = call_user_func_array($fn, ['router' => $this]);
        }else if(stripos($fn, '@') !== false){
            [$controllerName, $method] = explode('@', $fn);
            $controller = new $controllerName();
            $ret = call_user_func_array([$controller,$method],$options);
        }else {
            $ret = $this->callMiddleware($fn,$options);
        }

        if((is_null($ret) || $ret) && isset($options['namespace'])) {
            $class = $options['dispatcher'] = $options['dispatcher'] ?? Dispatcher::class;
            $ret = $this->callMiddleware($class,$options);
        }

        return (is_null($ret) || $ret);
    }

    private function callMiddleware($fn,$options = []){
        try{
            $reflect = new \ReflectionClass($fn);
            if(!$reflect->isInstantiable() || !$reflect->isSubclassOf(Middleware::class)){
                return false;
            }
            if($reflect->getConstructor()->getNumberOfParameters()){
                $middleware = $reflect->newInstanceArgs(['options'=>$options]);
            }else{
                $middleware = $reflect->newInstance();
            }

            $middleware->router = $this;
            $middleware->baseUrl = $this->getBaseUrl();
            $middleware->currentUri = $this->getCurrentUri();
            if(isset($options['dispatcher'])){
                app()->dispatcher = $middleware;
            }
            return $middleware->handle();
        }catch (\ReflectionException $e){
            return false;
        }
    }

    public function getCurrentUri(): string
    {
        $uri = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen($this->getBaseUrl()));
        if (strstr($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }else if(strstr($uri, '#')){
            $uri = substr($uri, 0, strpos($uri, '#'));
        }
        if(($suffix = config('config.suffix',false)) !== false){
            $uri = preg_replace('/'.preg_quote($suffix).'$/','',$uri);
        }
        return '/' . trim($uri, '/');
    }


    public function getBaseUrl(): string
    {
        if ($this->baseUrl === null) {
            $this->baseUrl = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) ;
        }

        return $this->baseUrl;
    }

    public function setBaseUrl($baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    public static function convertToUrlName($name) : string {
        return strtolower(trim(preg_replace('/([A-Z])/', '-$1', $name),'-'));
    }

    public static function convertToName($name): string
    {
        return str_replace(['-', '_'], '',ucwords($name,'-_'));
    }

    /**
     * 获取路由缓存文件路径
     */
    protected static function getCacheFilePath(): string
    {
        return base_path(static::CACHE_FILE);
    }

    /**
     * 编译路由并写入缓存文件
     * 在部署后调用一次以提升性能
     *
     * @return bool
     */
    public static function cacheRoutes(): bool
    {
        if (!is_file(config_path('route.php'))) {
            return false;
        }

        // 创建一个临时 Router 来收集路由
        $tempRouter = new self();
        require config_path('route.php');

        $cachePath = static::getCacheFilePath();
        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $cache = sprintf(
            '<?php return %s;',
            var_export([
                'routes' => $tempRouter->routes,
                'middlewares' => $tempRouter->middlewares,
                'notFoundCallback' => $tempRouter->notFoundCallback,
            ], true)
        );

        return @file_put_contents($cachePath, $cache) !== false;
    }

    /**
     * 从缓存文件加载路由
     *
     * @param Router $router
     * @return bool 是否成功从缓存加载
     */
    protected static function loadCachedRoutes(Router $router): bool
    {
        $cachePath = static::getCacheFilePath();
        if (!is_file($cachePath)) {
            return false;
        }

        // 检查缓存是否过期（路由文件更新时）
        $routeFile = config_path('route.php');
        if (is_file($routeFile) && filemtime($cachePath) < filemtime($routeFile)) {
            @unlink($cachePath);
            return false;
        }

        $cached = include $cachePath;
        if (!is_array($cached)) {
            return false;
        }

        $router->routes = $cached['routes'] ?? [];
        $router->middlewares = $cached['middlewares'] ?? [];
        $router->notFoundCallback = $cached['notFoundCallback'] ?? [];

        return true;
    }

    /**
     * 清除路由缓存
     */
    public static function clearRouteCache(): void
    {
        $cachePath = static::getCacheFilePath();
        if (is_file($cachePath)) {
            @unlink($cachePath);
        }
    }
}