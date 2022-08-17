<?php

namespace zap\validator\rules;

class Ascii extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        if (function_exists('mb_detect_encoding')) {
            return mb_detect_encoding($value, 'ASCII', true);
        }
        return 0 === preg_match('/[^\x00-\x7F]/', $value);
    }

}