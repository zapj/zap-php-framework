<?php

namespace zap\i18n;

use zap\util\Str;

class Language {
    public static $languages = [];
    public static $language = 'zh_CN';
    public static $languagePath = [];

    /**
     *
     * @param string $key
     * @param null|mixed $value
     */
    public static function with($key, $value = null) {
        if (is_array($key)) {
            static::$languages = array_merge(static::$languages, $key);
        } else {
            static::$languages[$key] = $value;
        }
    }

    /**
     * Language set
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value) {
        static::$languages[$key] = $value;
    }

    /**
     * 获取语言
     * @param string $key
     * @return mixed
     */
    public static function get($key) {
        if (isset(static::$languages[$key])) {
            return static::$languages[$key];
        }
        return $key;
    }

    public static function trans($name,$params = null){
        global $_LANG;
        list($filename,$langName) = explode('.',$name);

        if(!isset($_LANG[$name])){
            return $name;
        }
        if(!is_null($params)){
            return $_LANG[$name];
        }
        return Str::format($_LANG[$name],$params);
    }

    /**
     * @param $key
     * @return bool
     */
    public static function has($key) {
        return array_key_exists($key, static::$languages);
    }

    /**
     * 加载语言文件
     * @param string $name
     * @return bool
     */
    public static function loadLanguage($name) {
        if (isset(static::$languages['load.records']) && array_key_exists($name, static::$languages['load.records'])) {
            return true;
        }
        $found = false;
        if (is_array(static::$languagePath)) {
            foreach (static::$languagePath as $path) {
                $file = $path . '/' . $name . '.php';
                if (file_exists($file)) {
                    $_LANG = include($file);
                    if (!empty($_LANG) && is_array($_LANG)) {
                        static::$languages+=$_LANG;
                        $found = true;
                    }
                }
            }
        }
        return $found;
    }

    /**
     * @param string $language
     */
    public static function setLanguage($language) {
        static::$language = $language;
        static::$languagePath[] = resource_path('lang/');
        Language::loadLanguage($language);
    }

    /**
     * @param string $path
     */
    public static function addSearchPath($path) {
        static::$languagePath[] = realpath($path);
    }

    public static function getLanguagePaths() {
        return static::$languagePath;
    }
}