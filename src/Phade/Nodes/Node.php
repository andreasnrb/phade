<?php

namespace Phade\Nodes;


class Node {
    /**
     * @var Block
     */
    public $block;
    /**
     * @var string
     */
    public $name;
    /**
     * @var int
     */
    public $line;
    public $filename;
    public $debug;
    public $val;
    public $buffer;
    public $escape;
    protected $isBlock = false;
    public $isInline = false;
    public function isText()
    {
        return false;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function isBlock()
    {
        return $this->isBlock;
    }

    public function isInline()
    {
        return false;
    }
}
