<?php
/**
 * Created by PhpStorm.
 * User: Andreas
 * Date: 2013-10-12
 * Time: 21:50
 */

namespace Phade\Nodes;


class Comment extends Node{
	public function __construct($val, $buffer) {
		$this->val = $val;
		$this->buffer = $buffer;
	}
}