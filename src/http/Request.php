<?php
namespace zap\http;

use zap\util\Arr;
use zap\util\Str;

class Request {

    /**
     * @var \zap\http\Request
     */
    protected static $instance;

    protected $method;

    protected $_methods = [ "GET","POST","DELETE", "HEAD", "PUT" ];

    /**
     * @return \zap\http\Request
     */
    public static function instance()
    {
        if(is_null(static::$instance)){
            static::$instance = new Request();
            static::$instance->init();
        }
        return static::$instance;
    }

    /**
     * Get the public ip address of the user.
     *
     * @param   string $default
     * @return  array|string
     */
    public static function ip($default = '0.0.0.0') {
        $clientIP = $default;
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $clientIP = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $clientIP = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $clientIP = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $clientIP = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $clientIP = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $clientIP = $_SERVER['REMOTE_ADDR'];
        }

        return $clientIP;
    }

    /**
     * 获取真实IP地址
     * @param $default
     * @param $exclude_reserved
     *
     * @return false|mixed|string
     */
    public static function real_ip($default = '0.0.0.0', $exclude_reserved = false) {
        static $server_keys = null;
        if (empty($server_keys)) {
            $server_keys = array('HTTP_CLIENT_IP', 'REMOTE_ADDR', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_X_FORWARDED_FOR');
        }
        foreach ($server_keys as $key) {
            if (!static::server($key)) {
                continue;
            }
            $ips = explode(',', static::server($key));
            array_walk($ips, function (&$ip) {
                $ip = trim($ip);
            });
            $ips = array_filter($ips,
                function ($ip) use ($exclude_reserved) {
                    return filter_var($ip, FILTER_VALIDATE_IP, $exclude_reserved ? FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE : null);
                });
            if ($ips) {
                return reset($ips);
            }
        }
        return $default;
    }

    /**
     * Request Get
     * @param string $name
     * @param null|mixed $default
     * @return mixed
     */
    public static function get($name, $default = null) {
        if ($name == null && $default == null) {
            return $_GET;
        }
        return Arr::get($_GET, $name, $default);
    }

    /**
     * Request Post
     * @param string $name
     * @param null|mixed $default
     * @return mixed
     */
    public static function post($name = null, $default = null) {
        if ($name == null && $default == null) {
            return $_POST;
        }
        return Arr::get($_POST, $name, $default);
    }

    /**
     * Request All
     * @param string $name
     * @param null|mixed $default
     * @return mixed
     */
    public static function all($name = null, $default = null) {
        if ($name == null && $default == null) {
            return $_REQUEST;
        }
        return Arr::get($_REQUEST, $name, $default);
    }



    /**
     * Return's the protocol that the request was made with
     *
     * @return  string
     */
    public static function protocol() {
        if (static::server('HTTPS') == 'on' or
            static::server('HTTPS') == 1 or
            static::server('SERVER_PORT') == 443 or
            static::server('HTTP_X_FORWARDED_PROTO') == 'https' or
            static::server('HTTP_X_FORWARDED_PORT') == 443) {
            return 'https';
        }
        return 'http';
    }


    public static function isAjax() {
        return (static::server('HTTP_X_REQUESTED_WITH') !== null) and strtolower(static::server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest';
    }

    public static function isJson() {
        $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        return (stripos($content_type, 'application/json') !== false);
    }

    public static function isXml() {
        $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        return (stripos($content_type, 'application/xml') !== false);
    }

    public static function referer($default = '') {
        return static::server('HTTP_REFERER', $default);
    }

    public static function user_agent($default = '') {
        return static::server('HTTP_USER_AGENT', $default);
    }

    public static function file($key = null, $default = null) {
        return (func_num_args() === 0) ? $_FILES : Arr::get($_FILES, $key, $default);
    }

    /**
     * 从$_COOKIE中获取cookie
     *
     * @param    string  $index    索引 (如果为空返回全部)
     * @param    mixed   $default  默认值
     * @return   string|array
     */
    public static function cookie($key = null, $default = null) {
        return (func_num_args() === 0) ? $_COOKIE : Arr::get($_COOKIE, $key, $default);
    }

    public static function server($key = null, $default = null) {
        return (func_num_args() === 0) ? $_SERVER : Arr::get($_SERVER, strtoupper($key), $default);
    }

    public static function headers($key = null, $default = null) {
        static $headers = null;
        if ($headers === null) {
            if (function_exists('getallheaders')) {
                $headers = getallheaders();

                if ($headers !== false) {
                    return $headers;
                }
            }

            foreach ($_SERVER as $name => $value) {
                if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                    $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }
        return empty($headers) ? $default : ((func_num_args() === 0) ? $headers : Arr::get($headers, $key, $default));
    }


    public static function query_string($default = '') {
        return static::server('QUERY_STRING', $default);
    }

    /**
     * HTTP isMethod
     * @param string $method
     * @return bool
     */
    public static function isMethod($method = 'get') {
        return (strtoupper(static::instance()->method) == strtoupper($method));
    }

    public static function isPost() {
        return static::isMethod('post');
    }

    public static function isGet() {
        return static::isMethod('get');
    }

    /**
     * Get Method
     * @return string
     */
    public static function method() {
        return Request::instance()->method;
    }

    public static function raw() {
        return file_get_contents('php://input');
    }

    public static function getScriptName($suffix = '') {
        if (isset($_SERVER['SCRIPT_FILENAME']) && !empty($_SERVER['SCRIPT_FILENAME'])) {
            return basename($_SERVER['SCRIPT_FILENAME'], $suffix);
        } else if (isset($_SERVER['PHP_SELF']) && !empty($_SERVER['PHP_SELF'])) {
            return basename($_SERVER['PHP_SELF'], $suffix);
        } else if (isset($_SERVER['SCRIPT_NAME']) && !empty($_SERVER['SCRIPT_NAME'])) {
            return basename($_SERVER['SCRIPT_NAME'], $suffix);
        } else if (isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI'])) {
            return basename($_SERVER['REQUEST_URI'], $suffix);
        }
        return basename('index.php', $suffix);
    }


    public function init(){
        $this->method = $_SERVER['REQUEST_METHOD'];
        if ($this->method == 'POST' ) {
            array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER) && $this->method = $_SERVER['HTTP_X_HTTP_METHOD'];
            array_key_exists('X-HTTP-Method-Override', $_SERVER) && $this->method = $_SERVER['X-HTTP-Method-Override'];
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST' && array_key_exists('_method', $_POST)) {
            $HTTP_X_HTTP_METHOD = $_POST['_method'];
            if ($HTTP_X_HTTP_METHOD == 'DELETE' || $HTTP_X_HTTP_METHOD == 'PUT') {
                $this->method = $HTTP_X_HTTP_METHOD;
            }
        }
    }



}
