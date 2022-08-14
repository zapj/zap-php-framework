<?php

namespace zap\util;


class Html
{
    public static function decode($text) {
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }

    public static function encode($text) {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function tag($tag,$options) {

    }
}