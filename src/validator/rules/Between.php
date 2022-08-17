<?php

namespace zap\validator\rules;

class Between extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        if (!is_numeric($value)) {
            return false;
        }
        if (!is_array($params) && count($params[0]) !== 2) {
            return false;
        }
        $min = $params[0];
        $max = $params[1];
        return $value >= $min && $value <= $max;


    }

}