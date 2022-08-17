<?php

namespace zap\validator\rules;

class Min extends \zap\validator\AbstractRule
{
    public function validate($name, $value, $params = [])
    {
        if (!is_numeric($value)) {
            return false;
        }
        return $value >= $params;
    }
}