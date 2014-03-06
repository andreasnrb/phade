<?php
/**
 * Created by PhpStorm.
 * User: Andreas
 * Date: 2013-10-12
 * Time: 21:54
 */

namespace Phade\Nodes;


class Each extends Node {

    public $alternative;
    public $line;
    public $block;
    public $obj;
    public $key;
    public $val;

    function __construct($code, $val, $key) {
    	$this->obj = $code;
    	$this->val = $val;
    	$this->key = $key;
    }
}
