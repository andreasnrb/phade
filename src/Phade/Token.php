<?php

namespace Phade;


class Token {

    public $val;
    public $buffer;
    public $escape;
    public $attrs;
    public $key;
    public $code;
    public $mode;
    public $args;
    public $selfClosing;
    public $escaped;
    public $line;
    public $type;

    public function getType()
    {
        return $this->type;
    }
}