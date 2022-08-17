<?php

namespace zap\validator\rules;

class In extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        if(is_array($params) && in_array($value,$params)){
            return true;
        }
        return false;
    }

}