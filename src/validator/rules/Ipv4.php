<?php

namespace zap\validator\rules;

class Ipv4 extends \zap\validator\AbstractRule
{

    public function validate($name, $value, $params = [])
    {
        return filter_var($value, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) !== false;
    }

}