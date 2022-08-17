<?php

namespace zap\validator;

abstract class AbstractRule
{

    /**
     * @var \zap\validator\Validator
     */
    protected $validator;


    public function __construct($validator)
    {
        $this->validator = $validator;
    }

    public function validate($name,$value,$params = []){
        return false;
    }
}