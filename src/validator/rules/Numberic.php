<?php

namespace zap\validator\rules;

class Numberic extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        return is_numeric($value);
    }

}