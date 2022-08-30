<?php


use zap\util\Str;

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
 * @return \stdClass
 */
function arrayToObject($array) {
    if (!is_array($array)) {
        return $array;
    }

    $object = new \stdClass();
    foreach ($array as $name => $value) {
        $name = strtolower(trim($name));
        if (!empty($name)) {
            $object->$name = arrayToObject($value);
        }
    }
    return $object;
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


function is_assoc($array) {
    return is_array($array) and (array_values($array) !== $array);
}

function utf8_uri_encode( $utf8_string, $length = 0 ) {
    $unicode = '';
    $values = array();
    $num_octets = 1;
    $unicode_length = 0;

    $string_length = strlen( $utf8_string );
    for ($i = 0; $i < $string_length; $i++ ) {

        $value = ord( $utf8_string[ $i ] );

        if ( $value < 128 ) {
            if ( $length && ( $unicode_length >= $length ) )
                break;
            $unicode .= chr($value);
            $unicode_length++;
        } else {
            if ( count( $values ) == 0 ) $num_octets = ( $value < 224 ) ? 2 : 3;

            $values[] = $value;

            if ( $length && ( $unicode_length + ($num_octets * 3) ) > $length )
                break;
            if ( count( $values ) == $num_octets ) {
                if ($num_octets == 3) {
                    $unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]) . '%' . dechex($values[2]);
                    $unicode_length += 9;
                } else {
                    $unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]);
                    $unicode_length += 6;
                }

                $values = array();
                $num_octets = 1;
            }
        }
    }

    return $unicode;
}
function seems_utf8($str) {
    $length = strlen($str);
    for ($i=0; $i < $length; $i++) {
        $c = ord($str[$i]);
        if ($c < 0x80) $n = 0; # 0bbbbbbb
        elseif (($c & 0xE0) == 0xC0) $n=1; # 110bbbbb
        elseif (($c & 0xF0) == 0xE0) $n=2; # 1110bbbb
        elseif (($c & 0xF8) == 0xF0) $n=3; # 11110bbb
        elseif (($c & 0xFC) == 0xF8) $n=4; # 111110bb
        elseif (($c & 0xFE) == 0xFC) $n=5; # 1111110b
        else return false; # Does not match any model
        for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
            if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
                return false;
        }
    }
    return true;
}
function sanitize($title) {
    $title = strip_tags($title);
    // Preserve escaped octets.
    $title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
    // Remove percent signs that are not part of an octet.
    $title = str_replace('%', '', $title);
    // Restore octets.
    $title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

    if (seems_utf8($title)) {
        if (function_exists('mb_strtolower')) {
            $title = mb_strtolower($title, 'UTF-8');
        }
        $title = utf8_uri_encode($title, 200);
    }

    $title = strtolower($title);
    $title = preg_replace('/&.+?;/', '', $title); // kill entities
    $title = str_replace('.', '-', $title);
    $title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
    $title = preg_replace('/\s+/', '-', $title);
    $title = preg_replace('|-+|', '-', $title);
    $title = trim($title, '-');

    return $title;
}


function url_to($url, $params = null, $queryString = true) {
    if (is_array($params) && $queryString == true) {
        $url .= '?' . http_build_query($params);
    } else if (is_array($params) && $queryString == false) {
        $url = Str::format($url, $params);
    }
    return base_url($url);
}

