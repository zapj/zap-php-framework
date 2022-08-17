<?php

namespace zap\validator\rules;

class IsArray extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        return is_array($value);
    }

}