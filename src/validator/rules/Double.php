<?php

namespace zap\validator\rules;

class Double extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        return is_float($value);
    }

}