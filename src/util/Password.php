<?php

namespace zap\util;

class Password
{
    static public function hash($password) {
        return password_hash($password,PASSWORD_BCRYPT);
    }

    static public function verify($password, $hash) {
        return password_verify($password, $hash);
    }
}