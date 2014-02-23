<?php

namespace Phade\Nodes;


class Tag extends Attrs
{

    public $buffer;
    public $name;
    public $selfClosing;
    public $textOnly;
    public $code;
    public $attrs;
    /**
     * @var Block $block
     */
    public $block;
    private $inline = [
        'a'
        , 'abbr'
        , 'acronym'
        , 'b'
        , 'br'
        , 'code'
        , 'em'
        , 'font'
        , 'i'
        , 'img'
        , 'ins'
        , 'kbd'
        , 'map'
        , 'samp'
        , 'small'
        , 'span'
        , 'strong'
        , 'sub'
        , 'sup'
    ];

    function __construct($name, $block = null)
    {
        $this->name = $name;
        $this->block = $block ? $block : new Block;
    }

    /**
     * @return bool
     */
    public function isInline()
    {
        return in_array($this->name, $this->inline);
    }

    public function canInline()
    {
        $nodes = $this->block->getNodes();

        /**
         * @param Node $node
         * @return bool
         */
        $isInline = function ($node) {
            // Recurse if the node is a block
            if ($node->isBlock())
                /** @var Block $node */
                return array_walk($node->getNodes(), $isInline);
            return $node->isText() || ($node->isInline && $node->isInline());
        };

        // Empty tag
        if (!sizeof($nodes)) return true;

        // Text-only or inline-only tag
        if (1 == sizeof($nodes)) return $isInline($nodes[0]);

        // Multi-line inline-only tag
        if (array_walk($this->block->getNodes(), $isInline)) {
            for ($i = 1, $len = sizeof($nodes); $i < $len; ++$i) {
                if ($nodes[$i - 1]->isText() && $nodes[$i]->isText())
                    return false;
            }
            return true;
        }

        // Mixed tag
        return false;
    }
}
