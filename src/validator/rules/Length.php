<?php

namespace zap\validator\rules;

class Length extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        $length = mb_strlen($value);
        if (is_array($params) && count($params) == 2) {
            return $length >= $params[0] && $length <= $params[1];
        }
        return $length == (is_array($params) ? $params[0] : $params);
    }

}