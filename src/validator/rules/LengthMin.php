<?php

namespace zap\validator\rules;

class LengthMin extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        $length = mb_strlen($value);
        if (is_array($params)) {
            return false;
        }
        return $length >= $params;
    }

}