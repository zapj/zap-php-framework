<?php

namespace zap\validator;

use zap\http\Request;
use zap\util\Str;
use zap\validator\RuleFactory;

class Validator
{
    protected $rules = [];

    /**
     * 遇到第一个错误停止检测
     * @var bool
     */
    protected $stopFirstFail = false;

    public $data;

    protected $errors = [];

    protected $fieldLabels = [];

    public function __construct($data = null){
        if($data == null){
            $this->data = Request::method() == 'GET' ? $_GET : $_POST;
        }else{
            $this->data = $data;
        }
    }

    /**
     * @return mixed
     */
    public function rule($ruleName,$fields = [],$params = [])
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }
        foreach($fields as $field){
            $this->rules[$ruleName][$field] = $params;
        }
    }

    public function addRule($name,$callback,$message){
        $this->instanceRules[$name] = $callback;
        $this->instanceRulesMessage[$name] = $message;
        return $this;
    }

    /**
     * 添加Rule命名空间实现自定义验证规则
     * @param $namespace
     *
     * @return \zap\validator\Validator
     */
    public function addNamespace($namespace){
        RuleFactory::instance()->addNamespace($namespace);
        return $this;
    }

    public function validate(){
        foreach($this->rules as $ruleName => $rule){
            $r = RuleFactory::instance()->rule($ruleName,$this);

            foreach ($rule as $field=>$params) {
                $value = $this->getValue($this->data, $field);
                $ret = $r->validate($field,$value,$params);
                if(!$ret){
                    $this->error($field,'validator.'.strtolower($ruleName),$params);
                }

            }
        }
        return !((bool)count($this->errors));
    }



    public function getValue($data,$field)
    {
        $parent_is_wildcard = false;
        foreach (explode('.', $field) as $segment) {
            if (!is_array($data)) {
                return null;
            }

            if($parent_is_wildcard){
                $values = array();
                foreach ($data as $val) {
                    $values[] = $val[$segment];
                }
                $data = $values;
                $parent_is_wildcard = false;
                continue;
            }

            if($segment == '*'){
                $parent_is_wildcard = true;
                $values = array();
                foreach ($data as $val) {
                    $values[] = $val;
                }
                $data = $values;
                continue;
            }

            $data = $data[$segment];

        }
        return $data;
    }

    public function error($field, $message, $params = array())
    {
        $params['field'] = $field;
        $this->errors[$field][] = Str::format($message,$params);
        return $this;
    }

    public function reset(){
        $this->data = [];
        $this->rules = [];
        $this->fieldLabels = [];
    }

    public function setData($data){
        $this->reset();
        $this->data = $data;
    }

    public function label($label,$name){
        $this->fieldLabels[$label] = $name;
        return $this;
    }

    public function labels($labels = []){
       $this->fieldLabels = array_merge($this->fieldLabels, $labels);
        return $this;
    }

    public function errors(){
        return $this->errors;
    }


}