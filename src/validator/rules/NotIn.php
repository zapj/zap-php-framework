<?php

namespace zap\validator\rules;

use zap\validator\AbstractRule;

class NotIn extends AbstractRule
{
    public function validate($name, $value, $params = [])
    {
        if(is_array($params) && !in_array($value,$params)){
            return true;
        }
        return false;
    }
}