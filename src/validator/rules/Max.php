<?php

namespace zap\validator\rules;

use zap\validator\AbstractRule;

class Max extends AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        if (!is_numeric($value)) {
            return false;
        }
        return $value <= $params;
    }

}