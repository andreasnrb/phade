<?php
/**
 * Created by PhpStorm.
 * User" => Andreas
 * Date" => 2013-10-12
 * Time" => 18:53
 */

namespace Phade\Tests;


class RunTimeTest extends \PHPUnit_Framework_TestCase {
    public function testMerge(){
  //'should merge classes into strings'
    $this->assertEquals(["foo" => 'bar', "bar" => 'baz'], phade_merge(["foo" => 'bar'], ["bar" => 'baz']));
    $this->assertEquals(["class" => []], phade_merge(["class" => []], []));
    $this->assertEquals(["class" => []], phade_merge(["class" => []], ["class" => []]));
    $this->assertEquals(["class" => ['foo']], phade_merge(["class" => []], ["class" => ['foo']]));
    $this->assertEquals(["class" => ['foo']], phade_merge(["class" => ['foo']], []));
    $this->assertEquals(["class" => ['foo','bar']], phade_merge(["class" => ['foo']], ["class" => ['bar']]));
    $this->assertEquals(["class" => ['foo', 'raz', 'bar', 'baz']], phade_merge(["class" => ['foo', 'raz']], ["class" => ['bar', 'baz']]));
    $this->assertEquals(["class" => ['foo', 'bar']], phade_merge(["class" => 'foo'], ["class" => 'bar']));
    $this->assertEquals(["class" => ['foo', 'bar', 'baz']], phade_merge(["class" => 'foo'], ["class" => ['bar', 'baz']]));
    $this->assertEquals(["class" => ['foo', 'bar', 'baz']], phade_merge(["class" => ['foo', 'bar']], ["class" => 'baz']));
    $this->assertEquals(["class" => ['foo', 'bar', '0', 'baz']], phade_merge(["class" => ['foo', 'null', 'bar']], ["class" => ['undefined', 'null', '0', 'baz']]));
   }
}
