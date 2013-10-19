<?php

namespace Phade\Nodes;


class Block extends Node {

    public $prepended;
    public $appended;
    public $mode;
    public $parser;
    protected $isBlock = true;

    /**
     * @var bool
     */
    private $yield;
    /**
     * @var Node[]
     */
    private $nodes = [];

    public function setLine($line)
    {
        $this->line = $line;
    }

    public function push($node)
    {
        echo __METHOD__,"\n";
        array_push($this->nodes, $node);
    }

    public function setYield($yield)
    {
        $this->yield = $yield;
    }

    /**
     * @return boolean
     */
    public function getYield()
    {
        return $this->yield;
    }

    /**
     * @param int $n
     * @return Node|Node[]
     */
    public function getNodes($n = null)
    {
        if (!is_null($n))
            if (isset($this->nodes[$n]))
                return $this->nodes[$n];
            else
                return false;
        return $this->nodes;
    }

    public function setNodes($nodes)
    {
        $this->nodes = $nodes;
    }

    public function isEmpty()
    {
        return empty($this->nodes);
    }
}