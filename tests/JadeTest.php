<?php

namespace Phade\Tests;


use Phade\Jade;
use Phade\Nodes\Tag;

class JadeTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Jade $jade
     */
    private $jade;

    public function setUp()
    {
        $this->jade = new Jade();
    }

    public function testRender_str_fn()
    {
        $tester = $this;
        $this->jade->render('p foo bar', [], [], function ($err, $str) use (&$tester) {
            $tester->assertTrue(!$err);
            $tester->assertEquals('<p>foo bar</p>', $str);
        });
    }

    public function testRender_str_options_fn()
    {
        $tester = $this;
        $this->jade->render('p #{foo}', ["foo" => 'bar'], [],
            function ($err, $str) use (&$tester) {
                /**
                 * @var \Exception $err
                 * @var string $str
                 */
                if ($err)
                    echo $err->getMessage();
            $tester->assertTrue(!$err);
            $this->assertEquals('<p>bar</p>', $str);
        });
    }

    public function testRender_str_options_fn_cache()
    {
        $tester = $this;
        $this->jade->render('p bar', [], ["cache" => true], function ($err, $str) use (&$tester) {
            $tester->assertTrue(1 == preg_match('/the "filename" option is required for caching/', $err->getMessage()));
        });

        $this->jade->render('p foo bar', [], ["cache" => true, "filename" => 'test'], function ($err, $str) use (&$tester) {
            $tester->assertTrue(is_null($err));
            $this->assertEquals('<p>foo bar</p>', $str);
        });
    }

    public function testCompile()
    {
        $fn = $this->jade->compile('p foo');
        $this->assertEquals('<p>foo</p>', $fn());
    }

    public function testCompileLocals()
    {
        $fn = $this->jade->compile('p= foo');
        $this->assertEquals('<p>bar</p>', $fn(['foo' => 'bar']));
    }

    public function testCompileNoDebug()
    {
        $fn = $this->jade->compile("p foo\np #{bar}", ["compileDebug" => false]);
        $this->assertEquals('<p>foo</p><p>baz</p>', $fn(['bar' => 'baz']));
    }

    public function testCompileNoDebugAndGlobalHelpers()
    {
        $fn = $this->jade->compile("p foo\np #{bar}", ["compileDebug" => false, "helpers" => 'global']);
        $this->assertEquals('<p>foo</p><p>baz</p>', $fn(["bar" => 'baz']));
    }

    public function testNodeNullAttrsOnTag()
    {
        $tag = new Tag('a');
        $name = 'href';
        $val = '"/"';
        $tag->setAttribute($name, $val);
        $this->assertEquals($tag->getAttribute($name), $val);
        $tag->removeAttribute($name);
        $this->assertTrue(!$tag->getAttribute($name));
    }

    public function testAssignment()
    {/*
        $this->assertEquals('<div>5</div>', $this->jade->render("- var a = 5;\ndiv= a"));
        $this->assertEquals('<div>5</div>', $this->jade->render("- var a = 5\ndiv= a"));
        $this->assertEquals('<div>foo bar baz</div>', $this->jade->render("- var a = \"foo bar baz\"\ndiv= a"));
        $this->assertEquals('<div>5</div>', $this->jade->render("- var a = 5      \ndiv= a"));
        $this->assertEquals('<div>5</div>', $this->jade->render("- var a = 5      ; \ndiv= a"));
*/
        $fn = $this->jade->compile("- var test = local\np=test");
        $this->assertEquals('<p>bar</p>', $fn(["local" => 'bar']));/**/
    }

    /* public function testReasonablyFast() {
             $this->jade->compile(perfTest, {})
     }*/
}
