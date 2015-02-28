<?php
/**
 * Created by PhpStorm.
 * User: Andreas
 * Date: 2013-10-12
 * Time: 21:50
 */

namespace Phade\Nodes;


class BlockComment extends Node{
	public function __construct($val, $block, $buffer) {
		$this->val = $val;
		$this->block = $block;
		$this->buffer = $buffer;
	}
} 