<?php


function app(){
    return \zap\App::instance();
}

function base_url($url = null) {
    return app()->baseUrl($url);
}

/**
 * Site Root Path
 * @param $path
 *
 * @return string
 */
function root_path($path = null){
    return \zap\App::instance()->rootPath($path);
}

function base_path($path = null){
    return \zap\App::instance()->basePath($path);
}

function config_path($filename = null){
    return \zap\App::instance()->configPath($filename);
}

function storage_path($filename = null){
    return \zap\App::instance()->storagePath($filename);
}

function resource_path($filename = null){
    return \zap\App::instance()->resourcesPath($filename);
}

function assets_path($filename = null){
    return \zap\App::instance()->assetsPath($filename);
}

function themes_path($filename = null){
    return \zap\App::instance()->themesPath($filename);
}

/**
 * 访问配置文件
 * @param $name
 * @param $default
 *
 * @return array|mixed|null
 */
function config($name,$default = null){
    return \zap\Config::get($name,$default);
}

function config_set($name,$value){
    \zap\Config::instance()->set($name,$value);
}

function config_has($name){
    return \zap\Config::instance()->has($name);
}

function _e($html) {
    return htmlentities($html, ENT_QUOTES, 'UTF-8');
}


function object_get($object, $key, $default = null) {
    if (is_null($key) || trim($key) == '') {
        return $object;
    }
    foreach (explode('.', $key) as $segment) {
        if (!is_object($object) || !isset($object->{$segment})) {
            return $default;
        }
        $object = $object->{$segment};
    }
    return $object;
}

function arr_get(&$array, $key, $default = null) {
    return \zap\util\Arr::get($array,$key,$default);
}

function arr_has(&$array, $key) {
    return \zap\util\Arr::has($array,$key);
}

function arr_set(&$array, $key,$value) {
    return \zap\util\Arr::set($array,$key,$value);
}

/**
 * array to object
 * @param array $array
 * @return \stdClass|boolean
 */
function arrayToObject($array) {
    if (!is_array($array)) {
        return $array;
    }

    $object = new \stdClass();
    if (is_array($array) && count($array) > 0) {
        foreach ($array as $name => $value) {
            $name = strtolower(trim($name));
            if (!empty($name)) {
                $object->$name = arrayToObject($value);
            }
        }
        return $object;
    } else {
        return FALSE;
    }
}

function zap_pp() {
    $params = func_get_args();
    echo '<pre>';
    foreach ($params as $value) {
        print_r($value);
    }
    echo '</pre>';
    exit;
}

function base64_url_encode($data)
{
    $base64Url = strtr(base64_encode($data), '+/', '-_');

    return rtrim($base64Url, '=');
}

function base64_url_decode($base64Url)
{
    return base64_decode(strtr($base64Url, '-_', '+/'));
}

/**
 * Log
 *
 * @param string $name default app name
 *
 * @return \Monolog\Logger
 * @throws \Exception
 */
function logger($name = 'app'){
    return app()->getLogger($name);
}

function trans($key,$params=null,$value=null){
    return \zap\i18n\Language::trans($key,$params,$value);
}





