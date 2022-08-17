<?php

namespace zap;

/**
 * Level Log::DEBUG|Log::INFO|Log::NOTICE|Log::WARNING|Log::ERROR|Log::CRITICAL|Log::ALERT|Log::EMERGENCY
 */
class Log
{

    public const DEBUG = 100;

    public const INFO = 200;

    public const NOTICE = 250;

    public const WARNING = 300;

    public const ERROR = 400;

    public const CRITICAL = 500;

    public const ALERT = 550;

    public const EMERGENCY = 600;


    public static function info($message, array $context = []){
        if(config('config.log',false)){
            app()->getLogger()->info($message, $context);
        }
    }

    public static function warning($message, array $context = []){
        if(config('config.log',false)) {
            app()->getLogger()->warning($message, $context);
        }
    }

    public static function error($message, array $context = []){
        if(config('config.log',false)) {
            app()->getLogger()->error($message, $context);
        }
    }

    public static function debug($message, array $context = []){
        app()->getLogger()->debug($message, $context);
    }

    public static function alert($message, array $context = []){
        app()->getLogger()->alert($message, $context);
    }

    public static function emergency($message, array $context = []){
        app()->getLogger()->emergency($message, $context);
    }

    public static function critical($message, array $context = []){
        app()->getLogger()->critical($message, $context);
    }

}