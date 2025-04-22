<?php
/*
 * Copyright (c) 2025.  ZAP.CN  - ZAP CMS - All Rights Reserved
 * @author Allen
 * @email zapcms@zap.cn
 * @date 2025/4/22 16:01
 * @lastModified 2025/4/22 16:01
 *
 */

namespace zap\html;

class Element
{

    public string $tag;
    public $attributes;
    public $children;


    public function __construct($tag, $attributes = []) {
        $this->tag = $tag;
        $this->attributes = $attributes;
    }

    public function getTag(): string {
        return $this->tag;
    }

    public function setTag(string $tag): void {
        $this->tag = $tag;
    }

    public function getAttributes(): array {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void {
        $this->attributes = $attributes;
    }

    public function getChildren(): array {
        return $this->children;
    }

    public function setChildren(array $children): void {
        $this->children = $children;
    }

    public function addChild(Element $child): void {
        $this->children[] = $child;
    }
}