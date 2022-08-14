<?php

namespace zap\traits;

trait SingletonTrait {
    private $instance;

    protected function __construct() { }

    public function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    protected function __clone() { }
    protected function __sleep() { }
    protected function __wakeup() { }
}