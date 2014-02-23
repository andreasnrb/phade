<?php
/**
 * Created by PhpStorm.
 * User: Andreas
 * Date: 2013-10-12
 * Time: 21:36
 */

namespace Phade\Nodes;


class Text extends Node {
    public function __construct($val) {
        $this->val = $val;
    }

    public function isText() {
        return true;
    }
}
