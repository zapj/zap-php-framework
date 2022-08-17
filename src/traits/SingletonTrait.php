<?php

namespace zap\traits;

trait SingletonTrait {
    private static $instance;

    protected function __construct() { }

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    protected function __clone() { }
    protected function __sleep() { }
    protected function __wakeup() { }
}