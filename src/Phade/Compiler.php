<?php

namespace Phade;

use Phade\Exceptions\PhadeException;
use Phade\Nodes\Block;
use Phade\Nodes\Code;
use Phade\Nodes\Comment;
use Phade\Nodes\Doctype;
use Phade\Nodes\Each;
use Phade\Nodes\Filter;
use Phade\Nodes\MixinBlock;
use Phade\Nodes\Node;
use Phade\Nodes\Tag;
use Phade\Nodes\Text;

include('RunTime.php');
class Compiler {
    public $withinCase;
    public $runtime;
    private $selfClosing = [
        'meta'
        , 'img'
        , 'link'
        , 'input'
        , 'source'
        , 'area'
        , 'base'
        , 'col'
        , 'br'
        , 'hr'
    ];

    private $options;
    private $node;
    private $hasCompiledDoctype;
    private $hasCompiledTag;
    private $pp;
    private $debug;
    private $inMixin;
    private $indents;
    private $parentIndents;
    private $buf = [];
    private $lastBufferedIdx;
    private $doctypes = [
        '5' =>'<!DOCTYPE html>'
        , 'default' => '<!DOCTYPE html>'
        , 'xml' => '<?xml version=\"1.0\" encoding=\"utf-8\" ?>'
        , 'transitional' => '<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">'
        , 'strict' => '<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">'
        , 'frameset' => '<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Frameset//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd\">'
        , '1.1' => '<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\" \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">'
        , 'basic' => '<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML Basic 1.1//EN\" \"http://www.w3.org/TR/xhtml-basic/xhtml-basic11.dtd\">'
        , 'mobile' => '<!DOCTYPE html PUBLIC \"-//WAPFORUM//DTD XHTML Mobile 1.2//EN\" \"http://www.openmobilealliance.org/tech/DTD/xhtml-mobile12.dtd\">'
    ];
    private $doctype;
    private $xml;
    private $terse = false;
    /**
     * @var CharacterParser
     */
    private $characterParser;
    private $lastBufferedType;
    private $lastBuffered;
    private $bufferStartChar;
    private $escape;

    /**
     * Initialize `Compiler` with the given `node`.
     *
     * @param $node
     * @param string $filename
     * @param null $options
     */
    public function __construct($node, $filename = '', $options = null) {

        if (is_null($options))
            $options = new \stdClass();
        $this->filename = $filename;
        $this->options = $options;
        $this->node = $node;
        $this->hasCompiledDoctype = false;
        $this->hasCompiledTag = false;
        $this->pp = $options->prettyprint;
        $this->debug = $options->compileDebug;
        $this->inMixin = false;
        $this->indents = 0;
        $this->parentIndents = 0;
        if (isset($options->doctype) && !empty($options->doctype))
           $this->setDoctype($options->doctype);
    }

    /**
     * Compile parse tree to PHP.
     *
     * @return string
     */
    public function compile(){
        $this->characterParser = new CharacterParser();
        $this->buf = [];
        if ($this->pp) array_push($this->buf, '$phade_indent = [];');
        $this->lastBufferedIdx = -1;
        $this->visit($this->node);
        return join("\n", $this->buf);
    }

    /**
     * Sets the default doctype `name`. Sets terse mode to `true` when
     * html 5 is used, causing self-closing tags to end with ">" vs "/>",
     * and boolean attributes are not mirrored.
     * @param $name
     */
    public function setDoctype($name = 'default') {
        $this->doctype = isset($this->doctypes[strtolower($name)])? $this->doctypes[strtolower($name)] : "<!DOCTYPE $name>";
        $this->terse = strtolower($this->doctype) == '<!doctype html>';
        $this->xml = 0 === strpos($this->doctype, '<?xml');
    }

    /**
     * Buffer the given `str` exactly as is or with interpolation
     *
     * @param string $str
     * @param bool $interpolate
     * @throws
     */
    public function buffer($str, $interpolate = false) {
        if ($interpolate) {
            preg_match('/(\\\\)?([#!]){((?:.|\n)*)/sim', $str, $match, PREG_OFFSET_CAPTURE);
            echo "############### BUFFER ###################\n".$str."\n";
            if ($match) {
                /** match.index */
                $this->buffer(mb_substr($str,0, $match[0][1]), false);
                if ($match[1][0]) { // escape
                    $this->buffer($match[2][0] . '{', false);
                    $this->buffer($match[3][0], true);
                    return;
                } else {
                    try {
                        $rest = $match[3][0];
                        $range = $this->parseJSExpression($rest);
                        $code = $this->convertJStoPHP($range->src);
                        if (strlen($code))
                            $code = (('!' == $match[2][0] ? '' : 'phade_escape') . '( ' . $code .')');
                        else
                            $code = "''";
                    } catch (\Exception $ex) {
                        //didn't $match, just as if escaped
                        $this->buffer($match[2][0] . '{', false);
                        $this->buffer($match[3][0], true);
                        return;
                    }
                    $this->bufferExpression($code);
                    $this->buffer(mb_substr($rest, $range->end + 1), true);
                    return;
                }
            }
        }

        if ($this->lastBufferedIdx == sizeof($this->buf)) {
            if ($this->lastBufferedType === 'code') $this->lastBuffered .= ' . "';
            $this->lastBufferedType = 'text';
            $this->lastBuffered .= $str;
            $this->buf[$this->lastBufferedIdx - 1] = 'array_push($buf, ' . $this->bufferStartChar . $this->lastBuffered . '");';
        } else {
            array_push($this->buf, 'array_push($buf, "' . $str . '");');
            $this->lastBufferedType = 'text';
            $this->bufferStartChar = '"';
            $this->lastBuffered = $str;
            $this->lastBufferedIdx = sizeof($this->buf);
        }
    }

    /**
     * Buffer the given `src` so it is evaluated at run time
     *
     * @param $src
     */
    public function bufferExpression($src) {
        if ($this->isConstant($src)) {
            $this->buffer('" . ' .$this->parseCode($src). ' . "', false);
            return;
        }
        if ($this->lastBufferedIdx == sizeof($this->buf)) {
            if ($this->lastBufferedType === 'text') $this->lastBuffered .= '" . ';
            $this->lastBufferedType = 'code';
            $this->lastBuffered .= $this->parseCode($src) . '';
            $this->buf[$this->lastBufferedIdx - 1] = 'array_push($buf, ' . $this->bufferStartChar . $this->lastBuffered . ');';
        } else {
            array_push($this->buf, 'array_push($buf, ' . $this->parseCode($src) . ');');
            $this->lastBufferedType = 'code';
            $this->bufferStartChar = '';
            $this->lastBuffered = '(' . $src . ')';
            $this->lastBufferedIdx = mb_strlen($this->buf);
        }
    }

    /**
     * Buffer an indent based on the current `indent`
     * property and an additional `offset`.
     *
     * @param int $offset
     * @param Bool $newline
     * @api public
     */

    public function prettyIndent($offset = 0, $newline = false){
        $newline = $newline ? "\n" : "";
        $this->buffer($newline . join(array_fill (0,$this->indents + $offset,""),"  "));
        if ($this->parentIndents)
            array_push($this->buf, 'array_push($buf, array_map($buf, \'phade_indent\');');
    }

    /**
     * Visit `node`.
     *
     * @param Node $node
     */
    public function visit($node){

        $debug = $this->debug;
        if (is_array($node))
            $node = array_shift($node);
            if ($debug) {
            array_push($this->buf,'array_unshift($phade_debug, [ "lineno" => \'' . $node->getLine()
                . '\', "filename" => ' . ($node->filename
                    ? '\'' . json_encode($node->filename) . '\''
                    : '$phade_debug[0]["filename"]')
                . ']);');
        }

        // Massive hack to fix our context
        // stack for - else[ if] etc
        if (false === $node->debug && $this->debug) {
            array_pop($this->buf);
            array_pop($this->buf);
        }

        $this->visitNode($node);

        if ($debug) array_push($this->buf, 'array_shift($phade_debug);');
    }

    /**
     * Visit `node`.
     *
     * @param {Node} node
     * @api public
     */

    public function visitNode($node){
        $name = basename(get_class($node));
        return $this->{'visit' . $name}($node);
    }

    /**
     * Visit case `node`.
     *
     * @param Node node
     * @api public
     */

    public function visitCase($node){
        $_ = $this->withinCase;
        $this->withinCase = true;
        array_push($this->buf,'switch (' . $node->expr . '){');
        $this->visit($node->block);
        array_push($this->buf, '}');
        $this->withinCase = $_;
    }

    /**
     * Visit when `node`.
     *
     * @param Node node
     * @api public
     */

    public function visitWhen($node){
        if ('default' == $node->expr) {
            array_push($this->buf, 'default:');
        } else {
            array_push($this->buf, 'case ' . $node->expr . ':');
        }
        $this->visit($node->block);
        array_push($this->buf,'  break;');
    }

    /**
     * Visit literal `node`.
     *
     * @param  Node node
     */

    public function visitLiteral($node){
        $this->buffer($node->str);
    }

    /**
     * Visit all nodes in `block`.
     *
     * @param Block $block
     * @api public
     */

    public function visitBlock($block) {
        $len = sizeof($block->getNodes());
        $escape = $this->escape;
        $pp = $this->pp;

        // Pretty print multi-line text
        if ($pp && $len > 1 && !$escape && $block->getNodes(0)->isText() && $block->getNodes(1)->isText())
            $this->prettyIndent(1, true);
        for ($i = 0; $i < $len; ++$i) {
            // Pretty print text
            if ($pp && $i > 0 && !$escape && $block->getNodes($i)->isText() && $block->getNodes($i - 1)->isText())
                $this->prettyIndent(1, false);

            $this->visit($block->getNodes($i));
            // Multiple text nodes are separated by newlines
            if ($block->getNodes($i + 1) && $block->getNodes($i)->isText() && $block->getNodes($i + 1)->isText())
                $this->buffer("\n");
        }
    }

    /**
     * Visit a mixin's `block` keyword.
     *
     * @param MixinBlock $block
     * @throws \Exception
     */
    public function visitMixinBlock($block) {
        if (!$this->inMixin) {
            throw new \Exception('Anonymous blocks are not allowed unless they are part of a mixin.');
        }
        if ($this->pp) array_push($this->buf, 'array_push($phade_indent, \'' . join(array_fill(0, $this->indents + 1, '', ''), '  ') . "');");
        array_push($this->buf, 'block && block();');
        if ($this->pp) array_push($this->buf, 'array_pop($phade_indent);');
    }

    /**
     * Visit `doctype`. Sets terse mode to `true` when html 5
     * is used, causing self-closing tags to end with ">" vs "/>",
     * and boolean attributes are not mirrored.
     *
     * @param Doctype $doctype
     * @api public
     */
    public function visitDoctype($doctype = null) {
        if ($doctype && ($doctype->val || !$this->doctype)) {
            $this->setDoctype($doctype->val);
        }

        if ($this->doctype) $this->buffer($this->doctype);
        $this->hasCompiledDoctype = true;
    }

    /**
     * Visit `mixin`, generating a function that
     * may be called within the template.
     *
     * @param {Mixin} mixin
     * @api public
     */
    public function visitMixin($mixin) {
        $name = preg_replace('/-/', '_', $mixin->name) . '_mixin';
        $args = $mixin->arguments || '';
        $block = $mixin->block;
        $attrs = $mixin->attributes;
        $pp = $this->pp;

        if ($mixin->call) {
            if ($pp) array_push($this->buf, 'array_push($phade_indent, \'' . join(array_fill(0, $this->indents + 1, '', ''), '  ') . "');");
            if ($block || mb_strlen($attrs)) {

                array_push($this->buf, $name . '.call({');

                if ($block) {
                    array_push($this->buf, 'block: function(){');

                    // Render block with no indents, dynamically added when rendered
                    $this->parentIndents++;
                    $_indents = $this->indents;
                    $this->indents = 0;
                    $this->visit($mixin->block);
                    $this->indents = $_indents;
                    $this->parentIndents--;

                    if (mb_strlen($attrs)) {
                        array_push($this->buf, '},');
                    } else {
                        array_push($this->buf, '}');
                    }
                }

                if (mb_strlen($attrs)) {
                    $val = $this->attrs($attrs);
                    if ($val->inherits) {
                        array_push($this->buf, 'attributes: phade_merge({' . $val->buf
                            . '}, attributes), escaped: phade_merge(' . $val->escaped . ', escaped, true)');
                    } else {
                        array_push($this->buf, 'attributes: {' . $val->buf . '}, escaped: ' . $val->escaped);
                    }
                }

                if ($args) {
                    array_push($this->buf, '}, ' . $args . ');');
                } else {
                    array_push($this->buf, '});');
                }

            } else {
                array_push($this->buf, $name . '(' . $args . ');');
            }
            if ($pp) array_push($this->buf, 'array_pop($phade_indent);');
        } else {
            array_push($this->buf, '$' . $name . ' = function(' . $args . '){');
            array_push($this->buf, '$block = $this->block; $attributes = $this->attributes || {}; $escaped = $this->escaped || {};');
            $this->parentIndents++;
            $this->inMixin = true;
            $this->visit($block);
            $this->inMixin = false;
            $this->parentIndents--;
            array_push($this->buf, '};');
        }
    }

    /**
     * Visit `tag` buffering tag markup, generating
     * attributes, visiting the `tag`'s code and block.
     *
     * @param Tag $tag
     * @throws Exceptions\PhadeException
     * @api public
     */
    public function visitTag($tag) {
        $this->indents++;
        $name = $tag->name;
        $pp = $this->pp;
        $bufferName = function () use (&$tag, &$name) {
            if ($tag->buffer) $this->bufferExpression($name);
            else $this->buffer($name);
        };
        if ('pre' == $tag->name) $this->escape = true;

        if (!$this->hasCompiledTag) {
            if (!$this->hasCompiledDoctype && 'html' == $name) {
                $this->visitDoctype();
            }
            $this->hasCompiledTag = true;
        }

        // pretty print
        if ($pp && !$tag->isInline())
            $this->prettyIndent(0, true);

        if ((in_array(strtolower($name), $this->selfClosing) || $tag->selfClosing) && !$this->xml) {
            $this->buffer('<');
            $bufferName();
            $this->visitAttributes($tag->getAttributes());
            $this->terse
                ? $this->buffer('>')
                : $this->buffer('/>');
            // if it is non-empty throw an error
            if ($tag->block && !($tag->block->isBlock() && sizeof($tag->block->getNodes()) === 0)
                &&  __()->some($tag->block->getNodes(),function ($tag) { return $tag->type !== 'Text' || !(mb_ereg_match('/^\s*$/', $tag->val));})) {
                throw new PhadeException($name . ' is self closing and should not have content.');
            }
        } else {
            $this->buffer('<');
            $bufferName();
            if (sizeof($tag->getAttributes())) $this->visitAttributes($tag->getAttributes());
            $this->buffer('>');
            if ($tag->code) $this->visitCode($tag->code);
            $this->visit($tag->block);

            if ($pp && !$tag->isInline() && 'pre' != $tag->name && !$tag->canInline())
                $this->prettyIndent(0, true);
            $this->buffer('</');
            $bufferName();
            $this->buffer('>');
        }
        if ('pre' == $tag->name) $this->escape = false;
        $this->indents--;
    }

    /**
     * Visit `filter`, throwing when the filter does not exist.
     *
     * @param Filter $filter
     */
    public function visitFilter($filter){
        $text = join(array_map(function($node){ return $node->value; }
            ,$filter->block->getNodes()),"\n");
        $filter->attrs = ($filter->attrs ? $filter->attrs : (object)[]);
        $filter->attrs->filename = $this->options->filename;
        $f = new Filters();
        $this->buffer($f->filter($filter->name, $text, $filter->attrs), true);
    }

    /**
     * Visit `text` node.
     *
     * @param Text $text
     * @api public
     */
    public function visitText($text){
        $this->buffer($text->val, true);
    }

    /**
     * Visit a `comment`, only buffering when the buffer flag is set.
     *
     * @param Comment $comment
     * @api public
     */
    public function visitComment($comment){
        if (!$comment->buffer) return;
        if ($this->pp) $this->prettyIndent(1, true);
        $this->buffer('<!--' . $comment->val . '-->');
    }

    /**
     * Visit a `BlockComment`.
     *
     * @param Comment $comment
     * @api public
     */
    public function visitBlockComment($comment) {
        if (!$comment->buffer) return;
        if ($this->pp) $this->prettyIndent(1, true);
        if (0 === strpos(trim($comment->val), 'if')) {
            $this->buffer('<!--[' . trim($comment->val) . ']>');
            $this->visit($comment->block);
            if ($this->pp) $this->prettyIndent(1, true);
            $this->buffer('<![endif]-->');
        } else {
            $this->buffer('<!--' . $comment->val);
            $this->visit($comment->block);
            if ($this->pp) $this->prettyIndent(1, true);
            $this->buffer('-->');
        }
    }

    /**
     * Visit `code`, respecting buffer / escape flags.
     * If the code is followed by a block, wrap it in
     * a self-calling function.
     *
     * @param Code $code
     * @api public
     */
    public function visitCode($code){
        // Wrap code blocks with {}.
        // we only wrap unbuffered code blocks ATM
        // since they are usually flow control
        // Buffer code
        if ($code->buffer) {
            $val = ltrim($code->val);
            $val = $this->parseCode($val);
            if(strpos($val, '$') !== false)
                $val = '(null == ($phade_interp = ' . $val . ') ? "" : $phade_interp)';
            if ($code->escape) $val = 'phade_escape(' . $val . ')';
            $this->bufferExpression($val);
        } else {
            array_push($this->buf, $this->parseCode($code->val));
        }

        // Block support
        if ($code->block) {
            if (!$code->buffer) array_push($this->buf, '{');
            $this->visit($code->block);
            if (!$code->buffer) array_push($this->buf, '}');
        }
    }

    /**
     *
     * @param $code
     * @return mixed
     */
    private function parseCode($code) {
        if ($this->isConstant($code))
            return $code;
        if (preg_match('/.*\((?<!\$)([a-zA-Z]+)\).*/', $code, $captures)) {
            $inner_code = $captures[1];
            $val = "\$".$inner_code;
            $code = str_replace($inner_code, $val, $code);
        }
        return $code;
    }

    /**
     * Visit `each` block.
     *
     * @param Each $each
     * @api public
     */
    public function visitEach($each){
        array_push($this->buf, ''
            . '// iterate ' . $each->obj ."\n"
            . ';(function(){'."\n"
            . '  $obj = ' . $each->obj . ';'."\n"
            . '  if (\'number\' == typeof sizeof($obj)) {'."\n");

        if ($each->alternative) {
            array_push($this->buf, '  if (sizeof($obj)) {');
        }

        array_push($this->buf, ''
            . '    for ($' . $each->key . ' = 0;$l = sizeof($obj); $' . $each->key . ' < $l; $' . $each->key . '++) {'."\n"
            . '      $' . $each->val . ' = $obj[' . $each->key . '];'."\n");

        $this->visit($each->block);

        array_push($this->buf, '    }'."\n");

        if ($each->alternative) {
            array_push($this->buf, '  } else {');
            $this->visit($each->alternative);
            array_push($this->buf, '  }');
        }

        array_push($this->buf, ''
            . '  } else {'."\n"
            . '    $l = 0;'."\n"
            . '    for ($' . $each->key . ' in $obj) {'."\n"
            . '      $l++;'
            . '      $' . $each->val . ' = $obj[' . $each->key . '];'."\n");

        $this->visit($each->block);

        array_push($this->buf, '    }'."\n");
        if ($each->alternative) {
            array_push($this->buf, '    if ($l === 0) {');
            $this->visit($each->alternative);
            array_push($this->buf, '    }');
        }
        array_push($this->buf, '  }'."\n".'}).call(this);'."\n");
    }

    /**
     * Visit `attrs`.
     *
     * @param Array $attrs
     * @api public
     */
    public function visitAttributes($attrs) {

        if (!$attrs)
            return;
        $val = $this->attrs($attrs);
        if ($val->inherits) {
            $this->bufferExpression("jade.attrs(jade.merge({ " . $val->buf .
                " }, attributes), jade.merge(" . $val->escaped . ", escaped, true))");
        } else if ($val->constant) {
            $this->buffer(phade_attrs($val->buf, $val->escaped));
        } else {
//            $this->bufferExpression('$phade_attrs({ ' . $val->buf . " }, " . $val->escaped . ")");
        }
    }

    /**
     * Compile attributes.
     */
    public function attrs($attrs){
        $buf = [];
        $classes = [];
        $escaped = [];
        $constant = array_walk($attrs, function($attr){ return $this->isConstant($attr['val']);});
        $inherits = false;

        if ($this->terse) array_push($buf, ['terse' => 'true']);

        foreach($attrs as $attr) {
            if ($attr['name'] == 'attributes') return $inherits = true;
            $escaped[$attr['name']] = $attr['escaped'];
            if ($attr['name'] == 'class') {
                array_push($classes, $attr['val']);
            } else {
                if (preg_match('/(\[.*\])(\[\d+\])/', $attr['val'])) {
                    $attr['val'] = '" . ('.$this->convertJStoPHP($attr['val'], 'array') . ') ."';
                    $escaped[$attr['name']] = false;
                } elseif(preg_match('/(\{(.*)\})(\[.+\])/', $attr['val'])) {
                    $attr['val'] = '" . ('.$this->convertJStoPHP($attr['val'], 'keyvaluearray') . ') ."';
                    $escaped[$attr['name']] = false;

                }
                array_push($buf, $attr);
            }
        }

        if (sizeof($classes)) {
            $attr = [];
            $attr['name'] = 'class';
            $attr['val'] = join($classes,' ');
            array_push($buf, $attr);
        }

        return (object)[
            "buf" => $buf,
            "escaped" => $escaped,
            "inherits" => $inherits,
            "constant" => $constant
        ];
    }

    /**
     * @param $rest
     * @return \stdClass
     */
    private function parseJSExpression($rest) {
        return $this->characterParser->parseMax($rest);
    }

    /**
     * @param $src
     * @return bool
     */
    private function isConstant($src) {
        if (strpos($src, '$') !== false)
            return false;
        if (@eval($src)) {
            return false;
        }

        if (preg_match('/\d+/', $src))
            return false;
        return true;
    }

    private function filters($name, $text, $attrs) {
    }

    private function toConstant($string) {
        if ($this->isConstant($string))
            return $string;
        throw new PhadeException(sprintf('Not a constant %s',  $string));
    }

    private function convertJStoPHP($src, $type ='') {
        $isVar = $newVar = true;
        $phpSrc='';
        var_dump($src);
        if (ctype_digit($src) || __()->isNumber($src)) {
            return $src;
        }
        if ( $this->characterParser->isType($src))
            return "'$src'";
        if ($this->characterParser->isNull($src))
            return '';

        for($i = 0,$len = strlen($src);$i<$len;++$i) {
            if ($isVar && $newVar && " " != $src[$i] && !$this->characterParser->isNonChar($src[$i])) {
                $phpSrc .= '$';
                $isVar = $newVar = false;
            } elseif ($this->characterParser->isNonChar($src[$i])) {
                $isVar = false;
                $newVar = false;
            } elseif (" " == $src[$i]) {
                $newVar = $isVar = true;
            }
            $phpSrc .= $src[$i];
        }
        if (strpos($phpSrc,'||') !== false) {
            $array = explode('||', $phpSrc);
            array_walk($array, function(&$value, $key) { $value = trim($value); });
            var_dump($array);
            if (($i = __()->indexOf($array,'$undefined'))>=0) {
                unset($array[$i]);
                return array_pop($array);
            }
            $phpSrc =  $array[0] .'?'. $array[0] .':'.$array[1];
        } else {
            if ($type == 'array')
                return $src;
            elseif ($type == 'keyvaluearray') {
                $temp = array();
                $strtuples = explode(', ', $src);
                for($i = 0; $i < sizeof($strtuples);$i++) {
                    $tuples = explode(':', $strtuples[$i]);
                    if ($i==0)
                        $tuples[0] = "{'" . trim(substr($tuples[0],1)) . "'";
                    else
                        $tuples[0] = "'".trim($tuples[0])."'";
                    $temp[] = implode(' => ', $tuples);
                }
                $src = implode(',', $temp);
                return str_replace('}',']',str_replace('{','[', $src));
            }
            return $phpSrc = '($phade_interp = (' . $phpSrc . ')) == null ? \'\' : $phade_interp';
        }
        return $phpSrc;
    }
} 
