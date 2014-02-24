<?php
namespace Phade\Tests;


use Phade\Jade;

class CompilerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Jade $jade
     */
    private $jade;

    public function setUp()
    {
        $this->jade = new Jade();
    }

    public function testDoctypes()
    {
        $this->assertEquals("<?xml version=\"1.0\" encoding=\"utf-8\" ?>", $this->jade->render("!!! xml"));
        $this->assertEquals("<!DOCTYPE html>", $this->jade->render("doctype html"));
        $this->assertEquals("<!DOCTYPE foo bar baz>", $this->jade->render("doctype foo bar baz"));
        $this->assertEquals("<!DOCTYPE html>", $this->jade->render("!!! 5"));
        $this->assertEquals("<!DOCTYPE html>", $this->jade->render("!!!",[], ['doctype' => 'html']));
        $this->assertEquals("<!DOCTYPE html>", $this->jade->render("!!! html",[], ['doctype' => 'xml']));
        $this->assertEquals("<html></html>", $this->jade->render("html"));
        $this->assertEquals("<!DOCTYPE html><html></html>", $this->jade->render("html",[], ['doctype' => 'html']));
        $this->assertEquals("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML Basic 1.1//EN>", $this->jade->render("doctype html PUBLIC \"-//W3C//DTD XHTML Basic 1.1//EN"));
    }

    /*
        public function testBuffers() {
            $this->assertEquals("<p>foo</p>", $this->jade->render(new Buffer("p foo")));
        }*/

    public function testLineEndings()
    {
        $str = join("\r\n", [
            'p',
            'div',
            'img'
        ]);

        $html = join("", [
            '<p></p>',
            '<div></div>',
            '<img/>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\r", [
            'p',
            'div',
            'img'
        ]);

        $html = join("", [
            '<p></p>',
            '<div></div>',
            '<img/>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\r\n", [
            'p',
            'div',
            'img'
        ]);

        $html = join("", [
            '<p></p>',
            '<div></div>',
            '<img>'
        ]);

        $this->assertEquals($html, $this->jade->render($str,[], ['doctype' => 'html']));
    }

    public function testSingleQuotes()
    {
       $this->assertEquals("<p>'foo'</p>", $this->jade->render("p 'foo'"));
       $this->assertEquals("<p>'foo'</p>", $this->jade->render("p\n  | 'foo'"));
       $this->assertEquals("<a href=\"/foo\"></a>", $this->jade->render("- var path = 'foo';\na(href='/' + path)"));
    }

    public function testBlockExpansion()
    {
        $this->assertEquals("<li><a>foo</a></li><li><a>bar</a></li><li><a>baz</a></li>", $this->jade->render("li: a foo\nli: a bar\nli: a baz"));
        $this->assertEquals("<li class=\"first\"><a>foo</a></li><li><a>bar</a></li><li><a>baz</a></li>", $this->jade->render("li.first: a foo\nli: a bar\nli: a baz"));
        $this->assertEquals("<div class=\"foo\"><div class=\"bar\">baz</div></div>", $this->jade->render(".foo: .bar baz"));
    }

    public function testTags()
    {
        $str = join("\n", [
            'p',
            'div',
            'img'
        ]);

        $html = join("", [
            '<p></p>',
            '<div></div>',
            '<img/>'
        ]);

        $this->assertEquals($html, $this->jade->render($str), 'Test basic tags');
        $this->assertEquals('<fb:foo-bar></fb:foo-bar>', $this->jade->render("fb:foo-bar"), 'Test hyphens');
        $this->assertEquals('<div class=\"something\"></div>', $this->jade->render('div.something'), 'Test classes');
        $this->assertEquals('<div id="something"></div>', $this->jade->render("div#something"), 'Test ids');
        $this->assertEquals('<div class=\"something\"></div>', $this->jade->render('.something'), 'Test stand-alone classes');
        $this->assertEquals('<div id="something"></div>', $this->jade->render('#something'), 'Test stand-alone ids');
        $this->assertEquals('<div id="foo" class="bar"></div>', $this->jade->render('#foo.bar'));
        $this->assertEquals('<div id="foo" class="bar"></div>', $this->jade->render('.bar#foo'));
        $this->assertEquals('<div id="foo" class="bar"></div>', $this->jade->render('div#foo(class="bar")'));
        $this->assertEquals('<div id="foo" class="bar"></div>', $this->jade->render('div(class="bar")#foo'));
        $this->assertEquals('<div id="bar" class="foo"></div>', $this->jade->render('div(id="bar").foo'));
        $this->assertEquals('<div class="foo bar baz"></div>', $this->jade->render('div.foo.bar.baz'));
        $this->assertEquals('<div class="foo bar baz"></div>', $this->jade->render('div(class="foo").bar.baz'));
        $this->assertEquals('<div class="foo bar baz"></div>', $this->jade->render('div.foo(class="bar").baz'));
        $this->assertEquals('<div class="foo bar baz"></div>', $this->jade->render('div.foo.bar(class="baz")'));
        $this->assertEquals('<div class="a-b2"></div>', $this->jade->render('div.a-b2'));
        $this->assertEquals('<div class="a_b2"></div>', $this->jade->render('div.a_b2'));
        $this->assertEquals('<fb:user></fb:user>', $this->jade->render('fb:user'));
        $this->assertEquals('<fb:user:role></fb:user:role>', $this->jade->render('fb:user:role'));
        $this->assertEquals('<colgroup><col class="test"/></colgroup>', $this->jade->render("colgroup\n  col.test", [], ['prettyprint' => false]));/**/
    }

    public function testNestedTags()
    {
        $str = join("\n", [
            'ul',
            '  li a',
            '  li b',
            '  li',
            '    ul',
            '      li c',
            '      li d',
            '  li e',
        ]);

        $html = join("", [
            '<ul>',
            '<li>a</li>',
            '<li>b</li>',
            '<li><ul><li>c</li><li>d</li></ul></li>',
            '<li>e</li>',
            '</ul>'
        ]);

        $this->assertEquals($html, $this->jade->render($str, [], ['prettyprint' => false]));

        $str = join("\n", [
            'a(href="#")',
            '  | foo ',
            '  | bar ',
            '  | baz'
        ]);

        $this->assertEquals("<a href=\"#\">foo \nbar \nbaz</a>", $this->jade->render($str, [], ['prettyprint' => false]));

        $str = join("\n", [
            'ul',
            '  li one',
            '  ul',
            '    | two',
            '    li three'
        ]);

        $html = join("", [
            '<ul>',
            '<li>one</li>',
            '<ul>two',
            '<li>three</li>',
            '</ul>',
            '</ul>'
        ]);
        $this->assertEquals($html, $this->jade->render($str, [], ['prettyprint' => false]));
    }

    public function testVariableLengthNewlines()
    {
        $str = join("\n", [
            'ul',
            '  li a',
            '  ',
            '  li b',
            ' ',
            '         ',
            '  li',
            '    ul',
            '      li c',
            '',
            '      li d',
            '  li e',
        ]);

        $html = join("", [
            '<ul>',
            '<li>a</li>',
            '<li>b</li>',
            '<li><ul><li>c</li><li>d</li></ul></li>',
            '<li>e</li>',
            '</ul>'
        ]);

        $this->assertEquals($html, $this->jade->render($str, [], ['prettyprint' => false]));
    }

    public function testTabConversion()
    {
        $str = join("\n", [
            'ul',
            "\tli a",
            "\t",
            "\tli b",
            "\t\t",
            "\t\t\t\t\t\t",
            "\tli",
            "\t\tul",
            "\t\t\tli c",
            '',
            "\t\t\tli d",
            "\tli e",
        ]);

        $html = join("", [
            '<ul>',
            '<li>a</li>',
            '<li>b</li>',
            '<li><ul><li>c</li><li>d</li></ul></li>',
            '<li>e</li>',
            '</ul>'
        ]);

        $this->assertEquals($html, $this->jade->render($str, [], ['prettyprint' => false]));
    }

    public function testNewlines()
    {
        $str = join("\n", [
            'ul',
            '  li a',
            '  ',
            '    ',
            '',
            ' ',
            '  li b',
            '  li',
            '    ',
            '        ',
            ' ',
            '    ul',
            '      ',
            '      li c',
            '      li d',
            '  li e',
        ]);

        $html = join("", [
            '<ul>',
            '<li>a</li>',
            '<li>b</li>',
            '<li><ul><li>c</li><li>d</li></ul></li>',
            '<li>e</li>',
            '</ul>'
        ]);

        $this->assertEquals($html, $this->jade->render($str, [], ['prettyprint' => false]));

        $str = join("\n", [
            'html',
            ' ',
            '  head',
            '    != "test"',
            '  ',
            '  ',
            '  ',
            '  body'
        ]);

        $html = join("\n", [
            '<html>',
            '  <head>',
            '    test',
            '  </head>',
            '  <body></body>',
            '</html>',
            ''
        ]);

        $this->assertEquals($html, $this->jade->render($str, [], ['prettyprint' => true]));
        $this->assertEquals("<foo></foo>something<bar></bar>", $this->jade->render("foo\n= \"something\"\nbar", [], ['prettyprint' => false]));
        $this->assertEquals("<foo></foo>something<bar></bar>else", $this->jade->render("foo\n= \"something\"\nbar\n= \"else\"", [], ['prettyprint' => false]));
    }

    public function testText()
    {
        $this->assertEquals("foo\nbar\nbaz", $this->jade->render("| foo\n| bar\n| baz"));
        $this->assertEquals("foo \nbar \nbaz", $this->jade->render("| foo \n| bar \n| baz"));
        $this->assertEquals("(hey)", $this->jade->render("| (hey)"));
        $this->assertEquals("some random text", $this->jade->render("| some random text"));
        $this->assertEquals("  foo", $this->jade->render("|   foo"));
        $this->assertEquals("  foo  ", $this->jade->render("|   foo  "));
        $this->assertEquals("  foo  \n bar    ", $this->jade->render("|   foo  \n|  bar    "));
    }

    public function testPipeLessText()
    {
        $this->assertEquals("<pre><code><foo></foo><bar></bar></code></pre>", $this->jade->render("pre\n  code\n    foo\n\n    bar", [], ['prettyprint' => false]));
        $this->assertEquals("<p>foo\n\nbar</p>", $this->jade->render("p.\n  foo\n\n  bar", [], ['prettyprint' => false]));
        $this->assertEquals("<p>foo\n\n\n\nbar</p>", $this->jade->render("p.\n  foo\n\n\n\n  bar", [], ['prettyprint' => false]));
        $this->assertEquals("<p>foo\n  bar\nfoo</p>", $this->jade->render("p.\n  foo\n    bar\n  foo", [], ['prettyprint' => false]));
        $this->assertEquals("<script>s.parentNode.insertBefore(g,s)</script>", $this->jade->render("script.\n  s.parentNode.insertBefore(g,s)\n", [], ['prettyprint' => false]));
        $this->assertEquals("<script>s.parentNode.insertBefore(g,s)</script>", $this->jade->render("script.\n  s.parentNode.insertBefore(g,s)", [], ['prettyprint' => false]));
    }

    public function testTagText()
    {
        $this->assertEquals("<p>some random text</p>", $this->jade->render("p some random text"));
        $this->assertEquals("<p>click<a>Google</a>.</p>", $this->jade->render("p\n  | click\n  a Google\n  | ."));
        $this->assertEquals("<p>(parens)</p>", $this->jade->render("p (parens)"));
        $this->assertEquals('<p foo="bar">(parens)</p>', $this->jade->render('p(foo="bar") (parens)'));
        $this->assertEquals('<option value="">-- (optional) foo --</option>', $this->jade->render('option(value="") -- (optional) foo --'));
    }


    public function testTagTextBlock()
    {
        $this->assertEquals("<p>foo \nbar \nbaz</p>", $this->jade->render("p\n  | foo \n  | bar \n  | baz", [], ['prettyprint' => false]));
        $this->assertEquals("<label>Password:<input/></label>", $this->jade->render("label\n  | Password:\n  input", [], ['prettyprint' => false]));
        $this->assertEquals("<label>Password:<input/></label>", $this->jade->render("label Password:\n  input", [], ['prettyprint' => false]));
    }

    public function testTagTextInterpolation()
    {
        $this->assertEquals("yo, jade is cool", $this->jade->render("| yo, #{name} is cool\n", ['name' => 'jade']));
        $this->assertEquals("<p>yo, jade is cool</p>", $this->jade->render("p yo, #{name} is cool", ['name' => 'jade']));
        $this->assertEquals("yo, jade is cool", $this->jade->render('| yo, #{name || "jade"} is cool', ['name' => null]));
        $this->assertEquals("yo, 'jade' is cool", $this->jade->render('| yo, #{name || "\'jade\'"} is cool', ['name' => null]));
        $this->assertEquals("foo &lt;script&gt; bar", $this->jade->render("| foo #{code} bar", ['code' => '<script>']));
        $this->assertEquals("foo <script> bar", $this->jade->render("| foo !{code} bar", ['code' => '<script>']));

    }

    public function testFlexibleIndentation()
    {
        $this->assertEquals("<html><body><h1>Wahoo</h1><p>test</p></body></html>", $this->jade->render("html\n  body\n   h1 Wahoo\n   p test", [], ['prettyprint' => false]));
    }

    public function testInterpolationValues()
    {
        $this->assertEquals("<p>Users: 15</p>", $this->jade->render("p Users: #{15}"));
        $this->assertEquals("<p>Users: </p>", $this->jade->render("p Users: #{null}"));
        $this->assertEquals("<p>Users: </p>", $this->jade->render("p Users: #{undefined}"));
        $this->assertEquals("<p>Users: none</p>", $this->jade->render('p Users: #{undefined || "none"}'));
        $this->assertEquals("<p>Users: 0</p>", $this->jade->render("p Users: #{0}"));
        $this->assertEquals("<p>Users: false</p>", $this->jade->render("p Users: #{false}"));
    }

    public function  testHtml5Mode()
    {
        $this->assertEquals('<!DOCTYPE html><input type="checkbox" checked>', $this->jade->render("!!! 5\ninput(type=\"checkbox\", checked)", [], ['prettyprint' => false]));
        $this->assertEquals('<!DOCTYPE html><input type="checkbox" checked>', $this->jade->render("!!! 5\ninput(type=\"checkbox\", checked=true)", [], ['prettyprint' => false]));
        $this->assertEquals('<!DOCTYPE html><input type="checkbox">', $this->jade->render("!!! 5\ninput(type=\"checkbox\", checked= false)", [], ['prettyprint' => false]));
    }

    public function testMultiLineAttrs()
    {
        $this->assertEquals('<a foo="bar" bar="baz" checked="checked">foo</a>', $this->jade->render("a(foo=\"bar\"\n  bar=\"baz\"\n  checked) foo", [], ['prettyprint' => false]));
        $this->assertEquals('<a foo="bar" bar="baz" checked="checked">foo</a>', $this->jade->render("a(foo=\"bar\"\nbar=\"baz\"\nchecked) foo", [], ['prettyprint' => false]));
        $this->assertEquals('<a foo="bar" bar="baz" checked="checked">foo</a>', $this->jade->render("a(foo=\"bar\"\n,bar=\"baz\"\n,checked) foo", [], ['prettyprint' => false]));
        $this->assertEquals('<a foo="bar" bar="baz" checked="checked">foo</a>', $this->jade->render("a(foo=\"bar\",\nbar=\"baz\",\nchecked) foo", [], ['prettyprint' => false]));
    }

    public function testAttrs()
    {
        $this->assertEquals('<img src="&lt;script&gt;"/>', $this->jade->render('img(src="<script>")'), 'Test attr escaping');

        $this->assertEquals('<a data-attr="bar"></a>', $this->jade->render('a(data-attr="bar")'));
       $this->assertEquals('<a data-attr="bar" data-attr-2="baz"></a>', $this->jade->render('a(data-attr="bar", data-attr-2="baz")'));

        $this->assertEquals('<a title="foo,bar"></a>', $this->jade->render('a(title= "foo,bar")'));
        $this->assertEquals('<a title="foo,bar"></a>', $this->jade->render('a(title="foo,bar")'));
        $this->assertEquals('<a title="foo,bar" href="#"></a>', $this->jade->render('a(title= "foo,bar", href="#")'));

        $this->assertEquals('<p class="foo"></p>', $this->jade->render("p(class='foo')"), 'Test single quoted attrs');
        $this->assertEquals('<input type="checkbox" checked="checked"/>', $this->jade->render('input( type="checkbox", checked )'));
       $this->assertEquals('<input type="checkbox" checked="checked"/>', $this->jade->render('input( type="checkbox", checked = true )'));
        $this->assertEquals('<input type="checkbox"/>', $this->jade->render('input(type="checkbox", checked= false)'));
        $this->assertEquals('<input type="checkbox"/>', $this->jade->render('input(type="checkbox", checked= null)'));
        $this->assertEquals('<input type="checkbox"/>', $this->jade->render('input(type="checkbox", checked= undefined)'));

        $this->assertEquals('<img src="/foo.png"/>', $this->jade->render('img(src="/foo.png")'), 'Test attr =');
        $this->assertEquals('<img src="/foo.png"/>', $this->jade->render('img(src  =  "/foo.png")'), 'Test attr = whitespace');
        $this->assertEquals('<img src="/foo.png"/>', $this->jade->render('img(src="/foo.png")'), 'Test attr :');
        $this->assertEquals('<img src="/foo.png"/>', $this->jade->render('img(src  =  "/foo.png")'), 'Test attr : whitespace');

        $this->assertEquals('<img src="/foo.png" alt="just some foo"/>', $this->jade->render('img(src="/foo.png", alt="just some foo")'));
        $this->assertEquals('<img src="/foo.png" alt="just some foo"/>', $this->jade->render('img(src = "/foo.png", alt = "just some foo")'));

        $this->assertEquals('<p class="foo,bar,baz"></p>', $this->jade->render('p(class="foo,bar,baz")'));
        $this->assertEquals('<a href="http://google.com" title="Some : weird = title"></a>', $this->jade->render('a(href= "http://google.com", title= "Some : weird = title")'));
        $this->assertEquals('<label for="name"></label>', $this->jade->render('label(for="name")'));
        $this->assertEquals('<meta name="viewport" content="width=device-width"/>', $this->jade->render("meta(name= 'viewport', content='width=device-width')"), 'Test attrs that contain attr separators');
        $this->assertEquals('<div style="color= white"></div>', $this->jade->render("div(style='color= white')"));
        $this->assertEquals('<div style="color: white"></div>', $this->jade->render("div(style='color: white')"));
        $this->assertEquals('<p class="foo"></p>', $this->jade->render("p('class'='foo')"), 'Test keys with single quotes');
        $this->assertEquals('<p class="foo"></p>', $this->jade->render("p(\"class\"= 'foo')"), 'Test keys with double quotes');

        $this->assertEquals('<p data-lang="en"></p>', $this->jade->render('p(data-lang = "en")'));

        $this->assertEquals('<p data-dynamic="true"></p>', $this->jade->render('p("data-dynamic"= "true")'));
        $this->assertEquals('<p data-dynamic="true" class="name"></p>', $this->jade->render('p("class"= "name", "data-dynamic"= "true")'));
        $this->assertEquals('<p data-dynamic="true"></p>', $this->jade->render('p(\'data-dynamic\'= "true")'));
        $this->assertEquals('<p data-dynamic="true" class="name"></p>', $this->jade->render('p(\'class\'= "name", \'data-dynamic\'= "true")'));
        $this->assertEquals('<p data-dynamic="true" yay="yay" class="name"></p>', $this->jade->render('p(\'class\'= "name", \'data-dynamic\'= "true", yay)'));

        $this->assertEquals('<input checked="checked" type="checkbox"/>', $this->jade->render('input(checked, type="checkbox")'));

        $this->assertEquals('<a data-foo="{ foo: \'bar\', bar= \'baz\' }"></a>', $this->jade->render('a(data-foo  = "{ foo: \'bar\', bar= \'baz\' }")'));

        $this->assertEquals('<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"/>', $this->jade->render('meta(http-equiv="X-UA-Compatible", content="IE=edge,chrome=1")'));

        $this->assertEquals('<div style="background: url(/' . 'images/test.png)">Foo</div>', $this->jade->render("div(style= 'background: url(/images/test.png)') Foo"));
        $this->assertEquals('<div style="background = url(/' . 'images/test.png)">Foo</div>', $this->jade->render("div(style= 'background = url(/images/test.png)') Foo"));
        $this->assertEquals('<div style="foo">Foo</div>', $this->jade->render("div(style= ['foo', 'bar'][0]) Foo"));
        $this->assertEquals('<div style="bar">Foo</div>', $this->jade->render("div(style= { foo: 'bar', baz: 'raz' }['foo']) Foo"));
        //TODO: Add better support for js in attrubytes
/*        $this->assertEquals('<a href="def">Foo</a>', $this->jade->render("a(href='abcdefg'.substr(3,3)) Foo"));
        $this->assertEquals('<a href="def">Foo</a>', $this->jade->render("a(href={test: 'abcdefg'}.test.substr(3,3)) Foo"));
        $this->assertEquals('<a href="def">Foo</a>', $this->jade->render("a(href={test: 'abcdefg'}.test.substr(3,[0,3][1])) Foo"));
*/

        $this->assertEquals('<rss xmlns:atom="atom"></rss>', $this->jade->render("rss(xmlns:atom=\"atom\")"));
        $this->assertEquals('<rss xmlns:atom="atom"></rss>', $this->jade->render("rss('xmlns:atom'=\"atom\")"));
        $this->assertEquals('<rss xmlns:atom="atom"></rss>', $this->jade->render("rss(\"xmlns:atom\"='atom')"));
        $this->assertEquals('<rss xmlns:atom="atom" foo="bar"></rss>', $this->jade->render("rss('xmlns:atom'=\"atom\", 'foo'= 'bar')"));
        $this->assertEquals('<a data-obj="{ foo: \'bar\' }"></a>', $this->jade->render("a(data-obj= \"{ foo: 'bar' }\")"));

        $this->assertEquals('<meta content="what\'s up? \'weee\'"/>', $this->jade->render('meta(content="what\'s up? \'weee\'")'));
    }
/*
    public function testColonsOption()
    {
        $this->assertEquals('<a href="/bar"></a>", $this->jade->render('a(href:"/bar")", "{ colons: true }"));
    }

    public function testClassAttrArray()
    {
        $this->assertEquals('<body class="foo bar baz"></body>", $this->jade->render('body(class=["foo", "bar", "baz"])"));
    }

    public function testAttrInterpolation()
    {
        // Test single quote interpolation
        $this->assertEquals('<a href="/user/12">tj</a>'
            , $this->jade->render('a(href='/user/#{id}") #{name}", "{ name: 'tj", id: 12 }"));

        $this->assertEquals('<a href="/user/12-tj">tj</a>'
            , $this->jade->render('a(href='/user/#{id}-#{name}") #{name}", "{ name: 'tj", id: 12 }"));

        $this->assertEquals('<a href="/user/&lt;script&gt;">tj</a>'
            , $this->jade->render('a(href='/user/#{id}") #{name}", "{ name: 'tj", id: '<script>' }"));

        // Test double quote interpolation
        $this->assertEquals('<a href="/user/13">ds</a>'
            , $this->jade->render('a(href="/user/#{id}") #{name}", "{ name: 'ds", id: 13 }"));

        $this->assertEquals('<a href="/user/13-ds">ds</a>'
            , $this->jade->render('a(href="/user/#{id}-#{name}") #{name}", "{ name: 'ds", id: 13 }"));

        $this->assertEquals('<a href="/user/&lt;script&gt;">ds</a>'
            , $this->jade->render('a(href="/user/#{id}") #{name}", "{ name: 'ds", id: '<script>' }"));

        // Test escaping the interpolation
        $this->assertEquals('<a href="/user/#{id}">#{name}</a>'
            , $this->jade->render('a(href="/user/\\#{id}") \\#{name}", "{}"));
        $this->assertEquals('<a href="/user/#{id}">ds</a>'
            , $this->jade->render('a(href="/user/\\#{id}") #{name}", "{name: 'ds'}"));
    }

    public function testAttrParens()
    {
        $this->assertEquals('<p foo="bar">baz</p>", $this->jade->render('p(foo=((('bar"))))= ((('baz")))"));
    }

    public function testCodeAttrs()
    {
        $this->assertEquals('<p></p>", $this->jade->render('p(id= name)", "{ name: undefined }"));
        $this->assertEquals('<p></p>", $this->jade->render('p(id= name)", "{ name: null }"));
        $this->assertEquals('<p></p>", $this->jade->render('p(id= name)", "{ name: false }"));
        $this->assertEquals('<p id=""></p>", $this->jade->render('p(id= name)", "{ name: '' }"));
        $this->assertEquals('<p id="tj"></p>", $this->jade->render('p(id= name)", "{ name: 'tj' }"));
        $this->assertEquals('<p id="default"></p>", $this->jade->render('p(id= name || "default")", "{ name: null }"));
        $this->assertEquals('<p id="something"></p>", $this->jade->render('p(id= 'something")", "{ name: null }"));
        $this->assertEquals('<p id="something"></p>", $this->jade->render('p(id = 'something")", "{ name: null }"));
        $this->assertEquals('<p id="foo"></p>", $this->jade->render('p(id= (true ? 'foo' : 'bar"))"));
        $this->assertEquals('<option value="">Foo</option>", $this->jade->render('option(value='") Foo"));
    }

    public function testCodeAttrsClass()
    {
        $this->assertEquals('<p class="tj"></p>", $this->jade->render('p(class= name)", "{ name: 'tj' }"));
        $this->assertEquals('<p class="tj"></p>", $this->jade->render('p( class= name )", "{ name: 'tj' }"));
        $this->assertEquals('<p class="default"></p>", $this->jade->render('p(class= name || "default")", "{ name: null }"));
        $this->assertEquals('<p class="foo default"></p>", $this->jade->render('p.foo(class= name || "default")", "{ name: null }"));
        $this->assertEquals('<p class="default foo"></p>", $this->jade->render('p(class= name || "default").foo", "{ name: null }"));
        $this->assertEquals('<p id="default"></p>", $this->jade->render('p(id = name || "default")", "{ name: null }"));
        $this->assertEquals('<p id="user-1"></p>", $this->jade->render('p(id = "user-" + 1)"));
        $this->assertEquals('<p class="user-1"></p>", $this->jade->render('p(class = "user-" + 1)"));
    }

    public function testCodeBuffering()
    {
        $this->assertEquals('<p></p>", $this->jade->render('p= null"));
        $this->assertEquals('<p></p>", $this->jade->render('p= undefined"));
        $this->assertEquals('<p>0</p>", $this->jade->render('p= 0"));
        $this->assertEquals('<p>false</p>", $this->jade->render('p= false"));
    }
/*
    public function testScriptText()
    {
        $str = join('\n", [
            'script.",
            '  p foo",
            '",
            'script(type="text/template")",
            '  p foo",
            '",
            'script(type="text/template").",
            '  p foo'
        ]);

        $html = join('\n", [
            "<script>p" . " foo\n</script>",
            '<script type="text/template"><p>foo</p></script>",
            '<script type="text/template">p foo</script>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));
    }

    public function testComments()
    {
        // Regular
        $str = join('\n", [
            '//foo",
            'p bar'
        ]);

        $html = join("\n", [
            '<!--foo-->",
            '<p>bar</p>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        // Arbitrary indentation

        $str = join("\n", [
            '     //foo",
            'p bar'
        ]);

        $html = join("\n", [
            '<!--foo-->",
            '<p>bar</p>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        // Between tags

        $str = join("\n", [
            'p foo",
            '// bar ",
            'p baz'
        ]);

        $html = join("\n", [
            '<p>foo</p>",
            '<!-- bar -->",
            '<p>baz</p>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        // Quotes

        $str = "<!-- script(src: '/js/validate.js") -->";
        $js = "// script(src: '/js/validate.js") ";
        $this->assertEquals($str, $this->jade->render($js));
    }

    public function testUnbufferedComments()
    {
        $str = join("\n", [
            "//- foo",
            'p bar'
        ]);

        $html = join("\n", [
            '<p>bar</p>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\n", [
            'p foo",
            '//- bar ",
            'p baz'
        ]);

        $html = join("\n", [
            '<p>foo</p>",
            '<p>baz</p>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));
    }

    public function testLiteralHtml()
    {
        $this->assertEquals("<!--[if IE lt 9]>weeee<![endif]-->", $this->jade->render("<!--[if IE lt 9]>weeee<![endif]-->"));
    }

    public function testCode()
    {
        $this->assertEquals("test", $this->jade->render("!= "test""));
        $this->assertEquals("test", $this->jade->render("= "test""));
        $this->assertEquals("test", $this->jade->render("- var foo = "test"\n=foo"));
        $this->assertEquals("foo<em>test</em>bar", $this->jade->render("- var foo = "test"\n| foo\nem= foo\n| bar"));
        $this->assertEquals("test<h2>something</h2>", $this->jade->render("!= "test"\nh2 something"));

        $str = join("\n", [
            '- var foo = "<script>";",
            '= foo",
            '!= foo'
        ]);

        $html = join("", [
            '&lt;script&gt;",
            '<script>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\n", [
            '- var foo = "<script>";",
            '- if (foo)",
            '  p= foo'
        ]);

        $html = join("", [
            '<p>&lt;script&gt;</p>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\n", [
            '- var foo = "<script>";",
            '- if (foo)",
            '  p!= foo'
        ]);

        $html = join("", [
            '<p><script></p>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\n", [
            '- var foo;",
            '- if (foo)",
            '  p.hasFoo= foo",
            '- else",
            '  p.noFoo no foo'
        ]);

        $html = join("", [
            '<p class="noFoo">no foo</p>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\n", [
            '- var foo;",
            '- if (foo)",
            '  p.hasFoo= foo",
            '- else if (true)",
            '  p kinda foo",
            '- else",
            '  p.noFoo no foo'
        ]);

        $html = join("", [
            '<p>kinda foo</p>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\n", [
            'p foo",
            '= "bar"",
        ]);

        $html = join("", [
            '<p>foo</p>bar'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\n", [
            'title foo",
            '- if (true)",
            '  p something",
        ]);

        $html = join("", [
            '<title>foo</title><p>something</p>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\n", [
            'foo",
            '  bar= "bar"",
            '    baz= "baz"",
        ]);

        $html = join("", [
            '<foo>",
            '<bar>bar",
            '<baz>baz</baz>",
            '</bar>",
            '</foo>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));
    }
/*
    public function testEach()
    {
        // Array
        $str = join("\n", [
            '- var items = ["one", "two", "three"];",
            '- each item in items",
            '  li= item'
        ]);

        $html = join("", [
            '<li>one</li>",
            '<li>two</li>",
            '<li>three</li>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        // Any enumerable (length property)
        $str = join("\n", [
            '- var jQuery = { length: 3, 0: 1, 1: 2, 2: 3 };",
            '- each item in jQuery",
            '  li= item'
        ]);

        $html = join("", [
            '<li>1</li>",
            '<li>2</li>",
            '<li>3</li>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        // Empty array
        $str = join("\n", [
            '- var items = [];",
            '- each item in items",
            '  li= item'
        ]);

        $this->assertEquals("", $this->jade->render($str));

        // Object
        $str = join("\n", [
            '- var obj = { foo: "bar", baz: "raz" };",
            '- each val in obj",
            '  li= val'
        ]);

        $html = join("", [
            '<li>bar</li>",
            '<li>raz</li>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        // Complex
        $str = join("\n", [
            '- var obj = { foo: "bar", baz: "raz" };",
            '- each key in Object.keys(obj)",
            '  li= key'
        ]);

        $html = join("", [
            '<li>foo</li>",
            '<li>baz</li>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        // Keys
        $str = join("\n", [
            '- var obj = { foo: "bar", baz: "raz" };",
            '- each val, key in obj",
            '  li #{key}: #{val}'
        ]);

        $html = join("", [
            '<li>foo: bar</li>",
            '<li>baz: raz</li>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        // Nested
        $str = join("\n", [
            '- var users = [{ name: "tj" }]",
            '- each user in users",
            '  - each val, key in user",
            '    li #{key} #{val}",
        ]);

        $html = join("", [
            '<li>name tj</li>'
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\n", [
            '- var users = ["tobi", "loki", "jane"]",
            'each user in users",
            '  li= user",
        ]);

        $html = join("", [
            '<li>tobi</li>",
            '<li>loki</li>",
            '<li>jane</li>",
        ]);

        $this->assertEquals($html, $this->jade->render($str));

        $str = join("\n", [
            '- var users = ["tobi", "loki", "jane"]",
            'for user in users",
            '  li= user",
        ]);

        $html = join("", [
            '<li>tobi</li>",
            '<li>loki</li>",
            '<li>jane</li>",
        ]);
/*
        $this->assertEquals($html, $this->jade->render($str));
    }

    public function testIf()
    {
        $str = join("\n", [
            '- var users = ["tobi", "loki", "jane"]",
            'if users.length",
            '  p users: #{users.length}",
        ]);

        $this->assertEquals("<p>users: 3</p>", $this->jade->render($str));

        $this->assertEquals("<iframe foo="bar"></iframe>", $this->jade->render("iframe(foo="bar")"));
    }
/*
    public function testUnless()
    {
        $str = join("\n", [
            '- var users = ["tobi", "loki", "jane"]",
            'unless users.length",
            '  p no users",
        ]);

        $this->assertEquals("", $this->jade->render($str));

        $str = join("\n", [
            '- var users = []",
            'unless users.length",
            '  p no users",
        ]);

        $this->assertEquals("<p>no users</p>", $this->jade->render($str));
    }
/*
    public function testElse()
    {
        $str = join("\n", [
            '- var users = []",
            'if users.length",
            '  p users: #{users.length}",
            'else",
            '  p users: none",
        ]);

        $this->assertEquals("<p>users: none</p>", $this->jade->render($str));
    }

    public function testElseIf()
    {
        $str = join("\n", [
            '- var users = ["tobi", "jane", "loki"]",
            'for user in users",
            '  if user == "tobi"",
            '    p awesome #{user}",
            '  else if user == "jane"",
            '    p lame #{user}",
            '  else",
            '    p #{user}",
        ]);

        $this->assertEquals("<p>awesome tobi</p><p>lame jane</p><p>loki</p>", $this->jade->render($str));
    }

    public function testIncludeBlock()
    {
        $str = join("\n", [
            'html",
            '  head",
            '    include fixtures/scripts",
            '      scripts(src="/app.js")",
        ]);

        $this->assertEquals("<html><head><script src=\"/jquery.js\"></script><script src=\"/caustic.js\"></script><scripts src=\"/app.js\"></scripts></head></html>'
            , $this->jade->render($str, "{ filename: " . __DIR__ . DIRECTORY_SEPARATOR . 'jade.test.js' . "}"));
    }*/
}
