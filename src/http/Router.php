<?php

namespace zap\http;

use \Exception;

class Router
{
    /** @var array 已注册的路由 */
    public $routes = [];

    /** @var array 当前路由参数（匹配后填充） */
    public array $params = [];

    /** @var callable|string|null notFound 处理器 */
    protected $_notfound;

    /** @var array 命名路由表 ['name' => 'pattern'] */
    protected static array $namedRoutes = [];

    /** @var array 路由组属性栈 */
    protected array $groupStack = [];

    /** @var int 当前分组内路由计数 */
    protected int $groupRouteCount = 0;

    // ───────────────────── HTTP 方法快捷注册 ─────────────────────

    public function get(string $pattern, $fn): Route
    {
        return $this->match(['GET', 'HEAD'], $pattern, $fn);
    }

    public function post(string $pattern, $fn): Route
    {
        return $this->match(['POST'], $pattern, $fn);
    }

    public function put(string $pattern, $fn): Route
    {
        return $this->match(['PUT'], $pattern, $fn);
    }

    public function patch(string $pattern, $fn): Route
    {
        return $this->match(['PATCH'], $pattern, $fn);
    }

    public function delete(string $pattern, $fn): Route
    {
        return $this->match(['DELETE'], $pattern, $fn);
    }

    public function options(string $pattern, $fn): Route
    {
        return $this->match(['OPTIONS'], $pattern, $fn);
    }

    /**
     * 注册任意 HTTP 方法路由
     */
    public function any(string $pattern, $fn): Route
    {
        return $this->match(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $pattern, $fn);
    }

    /**
     * 注册多条 HTTP 方法的路由
     */
    public function match(array $methods, string $pattern, $fn): Route
    {
        $pattern = $this->applyGroupPrefix($pattern);
        $route = new Route($pattern, $fn, $methods);
        $this->routes[] = $route;
        $this->groupRouteCount++;
        return $route;
    }

    // ───────────────────── 路由组 ─────────────────────

    /**
     * 路由分组
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $this->groupRouteCount = 0;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * 应用分组前缀和中间件
     */
    private function applyGroupPatterns(Route $route): void
    {
        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $mw = $group['middleware'];
                if (is_string($mw)) {
                    $route->middleware($mw);
                } elseif (is_array($mw)) {
                    foreach ($mw as $m) {
                        $route->middleware($m);
                    }
                }
            }
        }
    }

    /**
     * 应用分组前缀
     */
    private function applyGroupPrefix(string $pattern): string
    {
        if (empty($this->groupStack)) {
            return $pattern;
        }
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }
        return $prefix . '/' . ltrim($pattern, '/');
    }

    // ───────────────────── 资源路由 ─────────────────────

    /**
     * 注册 RESTful 资源路由
     *
     * @param string $name       资源名称
     * @param string $controller 控制器类名
     * @param array  $options    选项 ['only'=>[...], 'except'=>[...]]
     */
    public function resource(string $name, string $controller, array $options = []): void
    {
        $actions = [
            'index'   => ['get',    "/{$name}",               '@index'],
            'create'  => ['get',    "/{$name}/create",        '@create'],
            'save'    => ['post',   "/{$name}",               '@save'],
            'show'    => ['get',    "/{$name}/{id:\d+}",      '@show'],
            'edit'    => ['get',    "/{$name}/{id:\d+}/edit", '@edit'],
            'update'  => ['put',    "/{$name}/{id:\d+}",      '@update'],
            'destroy' => ['delete', "/{$name}/{id:\d+}",      '@destroy'],
        ];

        // 仅注册指定动作
        if (isset($options['only'])) {
            $actions = array_intersect_key($actions, array_flip((array)$options['only']));
        }
        // 排除指定动作
        if (isset($options['except'])) {
            $actions = array_diff_key($actions, array_flip((array)$options['except']));
        }

        foreach ($actions as $action => [$method, $pattern, $suffix]) {
            $route = $this->$method($pattern, $controller . $suffix);
            $route->name("{$name}.{$action}");
        }
    }

    // ───────────────────── 命名路由 ─────────────────────

    /**
     * 注册命名路由（用于 URL 生成）
     */
    public function name(string $name, string $pattern): void
    {
        static::$namedRoutes[$name] = $pattern;
    }

    /**
     * 根据路由名称生成 URL
     *
     * @param string $name   路由名称
     * @param array  $params 参数替换 ['id' => 5]
     * @return string
     */
    public static function url(string $name, array $params = []): string
    {
        if (!isset(static::$namedRoutes[$name])) {
            throw new \InvalidArgumentException("Named route '{$name}' not found.");
        }

        $url = static::$namedRoutes[$name];

        // 替换 {param} 占位符
        foreach ($params as $key => $value) {
            $url = preg_replace('/\{' . preg_quote($key, '/') . '(:[^}]+)?\}/', (string)$value, $url);
        }

        // 移除未填充的可选参数
        $url = preg_replace('/\{[^}]+\}/', '', $url);

        // 清理多余的 /
        $url = preg_replace('#/+#', '/', $url);

        return $url;
    }

    /**
     * 获取所有命名路由
     */
    public static function getNamedRoutes(): array
    {
        return static::$namedRoutes;
    }

    // ───────────────────── NotFound ─────────────────────

    /**
     * 注册 404 处理器
     *
     * @param callable|string $handler 回调或 'Controller@method'
     */
    public function setNotFound($handler): void
    {
        $this->_notfound = $handler;
    }

    /**
     * 获取 404 处理器
     *
     * @return callable|string|null
     */
    public function getNotFound()
    {
        return $this->_notfound;
    }

    // ───────────────────── 调度 ─────────────────────

    /**
     * 匹配并执行路由
     *
     * @param string $requestUrl    请求 URL（已解析）
     * @param string $requestMethod HTTP 方法
     * @return bool
     */
    public function dispatch(string $requestUrl, string $requestMethod = 'GET'): bool
    {
        $matched = false;

        // HEAD 请求复用 GET 路由
        if ($requestMethod === 'HEAD') {
            if (!ob_get_level()) {
                ob_start();
            }
            $matched = $this->dispatchInternal($requestUrl, 'GET', true);
        } else {
            $matched = $this->dispatchInternal($requestUrl, $requestMethod, false);
        }

        return $matched;
    }

    /**
     * 内部调度逻辑
     */
    private function dispatchInternal(string $requestUrl, string $requestMethod, bool $headMode): bool
    {
        foreach ($this->routes as $route) {
            // 方法不匹配则跳过
            if (!in_array($requestMethod, $route->methods, true)) {
                continue;
            }

            // 特殊路由（直接回调）
            if (is_string($route->fn) && $route->fn[0] === '/') {
                if ($route->fn === $requestUrl || $route->fn === '*') {
                    $this->params = [];
                    $route->invoke($this->params);
                    return true;
                }
                continue;
            }

            // 模式匹配
            if ($route->matchPattern($requestUrl)) {
                $this->params = $route->params;
                $route->invoke($this->params);
                return true;
            }
        }

        return false;
    }
}
