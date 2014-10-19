<?php

namespace Phade;


use Phade\Exceptions\PhadeException;
use Phade\Nodes\Block;
use Phade\Nodes\BlockComment;
use Phade\Nodes\CaseWhen;
use Phade\Nodes\Code;
use Phade\Nodes\Comment;
use Phade\Nodes\Doctype;
use Phade\Nodes\Each;
use Phade\Nodes\Filter;
use Phade\Nodes\Literal;
use Phade\Nodes\Mixin;
use Phade\Nodes\MixinBlock;
use Phade\Nodes\Node;
use Phade\Nodes\SwitchCase;
use Phade\Nodes\Tag;
use Phade\Nodes\Text;

class Parser
{
    public $_spaces;

    /**
     * @var string mixed
     */
    private $input;
    /**
     * @var Lexer
     */
    private $lexer;
    /**
     * @var string
     */
    private $filename;
    /**
     * @var array
     */
    private $blocks = [];
    /**
     * @var array
     */
    private $mixins = [];
    /**
     * @var object
     */
    private $options;
    /**
     * @var Parser[]
     */
    private $contexts;
    private $textOnly = ['script', 'style'];
    /**
     * @var Parser
     */
    private $extending = null;

    /**
     * Initialize parser with the given params
     * @param string $str
     * @param string $filename
     * @param object|[] $options
     */
    public function __construct($str, $filename, $options)
    {
        $this->input = $str; //preg_replace('/^\uFEFF/', '', $str);
        $this->lexer = new Lexer($this->input, $options);
        $this->filename = $filename;
        $this->options = $options;
        $this->contexts = [$this];
    }

    /**
     * @return Node[]
     */
    public function parse()
    {

        $block = new Block();
        $block->setLine($this->line());
        while ('eos' !== $this->peek()->getType()) {
            if ('newline' == $this->peek()->getType())
                $this->advance();
            else {
                $block->push($this->parseExpr());
            }
        }
        if ($parser = $this->extending) {
            $this->context($parser);
            $ast = (array)$parser->parse();
            $this->context();
            foreach ($this->mixins as $mixin) {
                array_unshift($ast, $mixin);
            }
            return $ast;
        }
        return $block;
    }

    /**
     * @param Parser|null $parser
     * @return Parser
     */
    public function context($parser = null)
    {
        if ($parser) {
            $this->contexts[] = $parser;
        } else {
            return array_pop($this->contexts);
        }
        return $parser;
    }

    /**
     * @return Token
     */
    public function advance()
    {

        $token = $this->lexer->advance();
        return $token;
    }

    /**
     * @param int $n
     */
    public function skip($n)
    {
        while ($n--) $this->advance();
    }

    /**
     * @return Token
     */
    public function peek()
    {

        $token = $this->lookahead(1);
        return $token;
    }

    /**
     * @return int
     */
    public function line()
    {
        return $this->lexer->getLineno();
    }

    /**
     * Lookahead $n tokens
     * @param int $n
     * @return Token
     */
    private function lookahead($n)
    {
        return $this->lexer->lookahead($n);
    }

    /**
     * @param $type
     * @return Token
     * @throws Exceptions\PhadeException
     */
    public function expect($type)
    {
        if ($this->peek()->getType() === $type) {
            return $this->advance();
        } else {
            throw new PhadeException(sprintf('Expected "%s" but got "%s"', $type, $this->peek()->getType()));
        }
    }

    /**
     * @param $type
     * @return Token
     */
    public function accept($type)
    {
        if ($this->peek()->getType() == $type) {
            return $this->advance();
        }
    }

    /**
     * @return Node
     * @throws Exceptions\PhadeException
     */
    private function parseExpr()
    {


        switch ($this->peek()->getType()) {
            case 'tag':
                return $this->parseTag();
            case 'mixin':
                return $this->parseMixin();
            case 'block':
                return $this->parseBlock();
            case 'mixin-block':
                return $this->parseMixinBlock();
            case 'case':
                return $this->parseCase();
            case 'when':
                return $this->parseWhen();
            case 'default':
                return $this->parseDefault();
            case 'extends':
                return $this->parseExtends();
            case 'include':
                return $this->parseInclude();
            case 'doctype':
                return $this->parseDoctype();
            case 'filter':
                return $this->parseFilter();
            case 'comment':
                return $this->parseComment();
            case 'text':
                return $this->parseText();
            case 'each':
                return $this->parseEach();
            case 'code':
                return $this->parseCode();
            case 'call':
                return $this->parseCall();
            case 'interpolation':
                return $this->parseInterpolation();
            case 'yield':
                $this->advance();
                $block = new Block();
                $block->setYield(true);
                return $block;
            case 'id':
            case 'class':
                $tok = $this->advance();
                $this->lexer->defer($this->lexer->token('tag', 'div'));
                $this->lexer->defer($tok);
                return $this->parseExpr();
            default:
                throw new PhadeException('unexpected token "' . $this->peek()->getType() . '"');
        }
    }

    /**
     * Text
     */

    private function parseText()
    {

        $tok = $this->expect('text');
        $node = new Text($tok->val);
        $node->line = $this->line();
        return $node;
    }

    /**
     *   ':' expr
     * |$block
     */
    private function parseBlockExpansion()
    {
        if (':' == $this->peek()->getType()) {
            $this->advance();
            return new Block($this->parseExpr());
        } else {
            return $this->block();
        }
    }

    /**
     * case
     */

    private function parseCase()
    {
        $val = $this->expect('case')->val;
        $node = new SwitchCase($val);
        $node->line = $this->line();
        $node->block = $this->block();
        return $node;
    }

    /**
     * when
     */

    private function parseWhen()
    {
        $val = $this->expect('when')->val;
        return new CaseWhen($val, $this->parseBlockExpansion());
    }

    /**
     * default
     */

    private function parseDefault()
    {
        $this->expect('default');
        return new CaseWhen('default', $this->parseBlockExpansion());
    }

    /**
     * code
     */

    private function parseCode()
    {
        $tok = $this->expect('code');
        $node = new Code($tok->val, $tok->buffer, $tok->escape);
        $i = 1;
        $node->line = $this->line();
        while ($this->lookahead($i) && 'newline' == $this->lookahead($i)->getType()) ++$i;
        $block = 'indent' == $this->lookahead($i)->getType();
        if ($block) {
            $this->skip($i - 1);
            $node->block = $this->block();
        }
        return $node;
    }

    /**
     * comment
     */

    private function parseComment()
    {
        $tok = $this->expect('comment');


        if ('indent' == $this->peek()->getType()) {
            $node = new BlockComment($tok->val, $this->block(), $tok->buffer);
        } else {
            $node = new Comment($tok->val, $tok->buffer);
        }

        $node->line = $this->line();
        return $node;
    }

    /**
     * doctype
     */

    private function parseDoctype()
    {
        $tok = $this->expect('doctype');
        $node = new Doctype($tok->val);
        $node->line = $this->line();
        return $node;
    }

    /**
     * filter attrs? text-block
     */

    private function parseFilter()
    {
        $tok = $this->expect('filter');
        $attrs = $this->accept('attrs');

        if ('indent' == $this->peek()->getType()) {
            $this->lexer->pipeless = true;
            $block = $this->parseTextBlock();
            $this->lexer->pipeless = false;
        } else $block = new Block;

        $node = new Filter($tok->val, $block, $attrs && $attrs->attrs);
        $node->line = $this->line();
        return $node;
    }

    /**
     * each$block
     */

    private function parseEach()
    {
        $tok = $this->expect('each');
        $node = new Each($tok->code, $tok->val, $tok->key);
        $node->line = $this->line();
        $node->block = $this->block();
        if ($this->peek()->getType() == 'code' && $this->peek()->val == 'else:') {
            $this->advance();
            $node->alternative = $this->block();
        }
        return $node;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return \Phade\Lexer
     */
    public function getLexer()
    {
        return $this->lexer;
    }

    /**
     * @return string
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Resolves a path relative to the template for use in
     * includes and extends
     *
     * @param string $path
     * @param string $purpose Used in error messages.
     * @return string
     */
    private function resolvePath($path, $purpose)
    {
        return $path;
        /*
        var p = require('path');
        var dirname = p.dirname;
        var basename = p.basename;
        var join = p.join;

        if (path[0] !== '/' && !$this->filename)
          throw new Error('the "filename" option is required to use "' + purpose + '" with "relative" paths');

        if (path[0] === '/' && !$this->options.basedir)
          throw new Error('the "basedir" option is required to use "' + purpose + '" with "absolute" paths');

        path = join(path[0] === '/' ? $this->options.basedir : dirname($this->filename), path);

        if (basename(path).indexOf('.') === -1) path += '.jade';

        return path;*/
    }

    /**
     * 'extends' name
     */

    private function parseExtends()
    { /*
    $path = $this->resolvePath($this->expect('extends')->val.trim(), 'extends');
    if ('.jade' != path.substr(-5)) path += '.jade';

    var str = fs.readFileSync(path, 'utf8');
    var parser = new $this->constructor(str, path, $this->options);

    parser.blocks = $this->blocks;
    parser.contexts = $this->contexts;
    $this->extending = parser;

    // TODO: null node*/
        return new Literal('');
    }

    /**
     * 'block' name$block
     */

    private function parseBlock()
    {
        $block = $this->expect('block');
        $mode = $block->mode;
        $name = trim($block->val);

        $block = 'indent' == $this->peek()->getType()
            ? $this->block()
            : new Block(new Literal(''));

        $prev = isset($this->blocks[$name]) ? $this->blocks[$name] : (object)["prepended" => [], "appended" => []];
        if ($prev->mode === 'replace') return $this->blocks[$name] = $prev;

        $allNodes = array_merge($prev->prepended, $block->getNodes(), $prev->appended);

        switch ($mode) {
            case 'append':
                $prev->appended = $prev->parser === $this ?
                    array_merge($prev->appended, $block->getNodes()) :
                    array_merge($block->getNodes(), $prev->appended);
                break;
            case 'prepend':
                $prev->prepended = $prev->parser === $this ?
                    array_merge($block->getNodes(), $prev->prepended) :
                    array_merge($prev->prepended, $block->getNodes());
                break;
        }
        $block->setNodes($allNodes);
        $block->appended = $prev->appended;
        $block->prepended = $prev->prepended;
        $block->mode = $mode;
        $block->parser = $this;

        return $this->blocks[$name] = $block;
    }

    private function parseMixinBlock()
    {
        $this->expect('mixin-block');
        return new MixinBlock();
    }

    /**
     * include$block?
     */
    private function parseInclude()
    {
        return new Node();
        /*
        var path = $this->resolvePath($this->expect('include')->val.trim(), 'include');

        // non-jade
        if ('.jade' != path.substr(-5)) {
            var str = fs.readFileSync(path, 'utf8').replace(/\r/g, '');
          var ext = extname(path).slice(1);
          if (filters.exists(ext)) str = filters(ext, str, { filename: path });
          return new Literal(str);
        }

        var str = fs.readFileSync(path, 'utf8');
        var parser = new $this->constructor(str, path, $this->options);
        parser.blocks = utils.merge({}, $this->blocks);

        parser.mixins = $this->mixins;

        $this->context(parser);
        var ast = parser.parse();
        $this->context();
        ast.filename = path;

        if ('indent' == $this->peek()->getType()) {
            ast.includeBlock().push($this->block());
        }

        return ast;/*/
    }

    /**
     * call ident$block
     */

    private function parseCall()
    {
        $tok = $this->expect('call');
        $name = $tok->val;
        $args = $tok->args;
        $mixin = new Mixin($name, $args, new Block, true);

        $this->tag($mixin);
        if ($mixin->code) {
            $mixin->block->push($mixin->code);
            $mixin->code = null;
        }
        if ($mixin->block->isEmpty()) $mixin->block = null;
        return $mixin;
    }

    /**
     * mixin$block
     */

    private function parseMixin()
    {
        $tok = $this->expect('mixin');
        $name = $tok->val;
        $args = $tok->args;
        // definition
        if ('indent' == $this->peek()->getType()) {
            $mixin = new Mixin($name, $args, $this->block(), false);
            $this->mixins[$name] = $mixin;
            return $mixin;
            // call
        } else {
            return new Mixin($name, $args, null, true);
        }
    }

    /**
     * indent (text | newline)* outdent
     */

    private function parseTextBlock()
    {
        $block = new Block;
        $block->setLine($this->line());
        $spaces = $this->expect('indent')->val;
        if (null == $this->_spaces) $this->_spaces = $spaces;
        $indent = join(' ', array_fill(0, $spaces - $this->_spaces + 1, ''));
        while ('outdent' != $this->peek()->getType()) {
            switch ($this->peek()->getType()) {
                case 'newline':
                    $this->advance();
                    break;
                case 'indent':
                    foreach ($this->parseTextBlock()->getNodes() as $node) {
                        $block->push($node);
                    };
                    break;
                default:
                    $text = new Text($indent . $this->advance()->val);
                    $text->line = $this->line();
                    $block->push($text);
            }
        }

        if ($spaces == $this->_spaces) $this->_spaces = null;
        $this->expect('outdent');
        return $block;
    }

    /**
     * indent expr* outdent
     */

    private function block()
    {
        $block = new Block;
        $block->setLine($this->line());
        $this->expect('indent');
        while ('outdent' != $this->peek()->getType()) {
            if ('newline' == $this->peek()->getType()) {
                $this->advance();
            } else {
                $block->push($this->parseExpr());
            }
        }
        $this->expect('outdent');
        return $block;
    }

    /**
     * interpolation (attrs | class | id)* (text | code | ':')? newline*$block?
     */

    private function parseInterpolation()
    {
        $tok = $this->advance();
        $tag = new Tag($tok->val);
        $tag->buffer = true;
        return $this->tag($tag);
    }

    /**
     * tag (attrs | class | id)* (text | code | ':')? newline*$block?
     */

    private function parseTag()
    {
        $tok = $this->advance();
        $tag = new Tag($tok->val);
        $tag->selfClosing = $tok->selfClosing;

        return $this->tag($tag);
    }

    /**
     * Parse tag.
     * @param Tag $tag
     * @return mixed
     * @throws Exceptions\PhadeException
     */
    private function tag($tag)
    {
        $dot = true;
        $tag->line = $this->line();

        $seenAttrs = false;
        // (attrs | class | id)*
        while (true) {
            switch ($this->peek()->getType()) {
                case 'id':
                case 'class':
                    $tok = $this->advance();
                    $tag->setAttribute($tok->getType(), $tok->val);
                    continue;
                case 'attrs':
                case 'attributes':
                    if ($seenAttrs) {
                        throw new PhadeException('You should not have jade tags with multiple attributes.');
                    }
                    $seenAttrs = true;
                    $tok = $this->advance();
                    $obj = $tok->attrs;
                    if ($tok->selfClosing) $tag->selfClosing = true;
                    foreach($obj as $attr) {
                        $tag->setAttribute($attr['name'], $attr['val'], $attr['escaped'], $attr['code']);
                    }
                    continue;
                default:
                    break 2;
            }
        }

        // check immediate '.'
        if ('dot' == $this->peek()->getType()) {
            $tag->textOnly = true;
            $this->advance();
        }

        if ($tag->selfClosing
            && __(['newline', 'outdent', 'eos'])->indexOf($this->peek()->getType()) === -1
            && ($this->peek()->getType() !== 'text' || preg_match('/^\s*$/',$this->peek()->val))) {
            throw new PhadeException($tag->name . ' is self closing and should not have content.');
        }

        // (text | code | ':')?
        switch ($this->peek()->getType()) {
            case 'text':
                $tag->block->push($this->parseText());
                break;
            case 'code':
                $tag->code = $this->parseCode();
                break;
            case ':':
                $this->advance();
                $tag->block = new Block;
                $tag->block->push($this->parseExpr());
                break;
            case 'newline':
            case 'indent':
            case 'outdent':
            case 'eos':
            break;
            default:
                throw new PhadeException('Unexpected token `' . $this->peek()->getType() . '` expected `text`, `code`, `:`, `newline` or `eos`');
        }

        // newline*
        while ('newline' == $this->peek()->getType()) $this->advance();

        //$block?
        if ('indent' == $this->peek()->getType()) {
            if ($tag->textOnly) {
                $this->lexer->pipeless = true;
                $tag->block = $this->parseTextBlock();
                $this->lexer->pipeless = false;
            } else {
                $block = $this->block();
                if ($tag->block) {
                    foreach ($block->getNodes() as $node) {
                        $tag->block->push($node);
                    }
                } else {
                    $tag->block = $block;
                }
            }
        }

        return $tag;
    }
}
