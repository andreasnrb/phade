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
    private $isText;
    public $val;
    public $buffer;
    public $escape;
    protected $isBlock = false;
    protected $isInline = false;

    public function isText()
    {
        return $this->isText;
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
        return $this->isInline;
    }
}