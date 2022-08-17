<?php

namespace zap\validator\rules;

class Integer extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        return is_int($value);
    }

}