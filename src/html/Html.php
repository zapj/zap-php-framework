<?php

namespace zap\html;

class Html
{
    public static function __callStatic($name, $arguments)
    {

    }

    public static function create($tagName, $attributes = []) : Element {
        return new Element($tagName, $attributes);
    }

}
