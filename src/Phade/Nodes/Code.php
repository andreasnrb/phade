<?php

namespace Phade\Nodes;


class Code extends Node{
    public $val;
    public $buffer;
    public $escape;
    public $debug;
    public function __construct($val, $buffer, $escape) {
        $this->val = $val;
        $this->buffer = $buffer;
        $this->escape = $escape;
        if (preg_match('/^ *else/', $val)) $this->debug = false;
    }
}