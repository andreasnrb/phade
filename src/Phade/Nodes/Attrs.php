<?php

namespace Phade\Nodes;


class Attrs extends Node{
    private $attrs = [];
    public function setAttribute($name, $val, $escaped = '') {
        $attr = ['name'=> $name, 'val' => $val, 'escaped' => $escaped];
        $this->attrs[] = $attr;
        return $this;
    }

    public function getAttribute($name) {
        foreach($this->attrs as $attr) {
            if ($name == $attr['name'])
                return $attr->val;
        }
    }

    public function removeAttribute($name)
    {
        foreach($this->attrs as $i => $attr)
            if ($name == $attr['name'])
                unset($this->attrs[$i]);
    }

    public function getAttributes()
    {
        return $this->attrs;
    }
    public function setAttributes($param)
    {
        $this->attrs = $param;
    }
}
