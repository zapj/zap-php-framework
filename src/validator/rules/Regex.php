<?php

namespace zap\validator\rules;

class Regex extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        return preg_match($params,$value) !== false;
    }

}