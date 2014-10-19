<?php

namespace Phade;


class Lexer
{
    public $pipeless = false;

    /**
     * @var array
     */
    private $deferredTokens = [];
    /**
     * @var int
     */
    private $lineno = 1;
    private $stash = [];
    private $indentStack = [];
    private $indentRe = null;


    public function __construct($str, $options)
    {
        $this->input = preg_replace('/\r\n|\r/', "\n", $str);
        $this->colons = isset($options->colons) ? $options->colons : '';
    }


    public function token($type, $val = null)
    {
        $tok = new Token;
        $tok->type = $type;
        $tok->val = $val;
        $tok->line = $this->getLineno();
        return $tok;
    }

    /**
     * Consume the given `len` of input.
     *
     * @param int $len
     */

    private function consume($len)
    {
        $this->input = mb_substr($this->input, $len);

    }

    /**
     * Scan for `type` with the given `regexp`.
     *
     * @param string $type
     * @param string $regexp
     * @return Token
     */

    private function scan($regexp, $type)
    {

        if (preg_match($regexp, $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $token = $this->token($type, isset($captures[1]) && mb_strlen($captures[1]) > 0 ? $captures[1] : '');
            return $token;
        }
        return false;
    }

    public function defer($token)
    {
        array_push($this->deferredTokens, $token);
    }

    /**
     * @param $n
     * @return Token
     */
    public function lookahead($n = 1)
    {
        $fetch = $n - sizeof($this->stash);
        while ($fetch-- > 0) $this->stash[] = $this->next();
        return $this->stash[--$n];
    }

    /**
     * Return the indexOf `(` or `{` or `[` / `)` or `}` or `]` delimiters.
     *
     * @param int $skip
     * @return null|\stdClass
     * @throws \Exception
     */
    public function bracketExpression($skip = 0)
    {
        $start = $this->input[$skip];
        if ($start != '(' && $start != '{' && $start != '[') throw new \Exception('unrecognized start character');
        $end = array('(' => ')', '{' => '}', '[' => ']');
        $range = (new CharacterParser())->parseMax($this->input, $skip + 1);
        if (is_null($range)) throw new \Exception('source does not have an end character bar starts with ' . $start);
        if ($this->input[$range->end] != $end[$start]) throw new \Exception('start character ' . $start .
            ' does not match end character ' . $this->input[$range->end]);

        return $range;
    }

    /**
     * Stashed token.
     */

    private function stashed()
    {

        $stashed = sizeof($this->stash) ? array_shift($this->stash) : null;
        return $stashed;
    }

    /**
     * Deferred token.
     */

    private function deferred()
    {
        return sizeof($this->deferredTokens) ? array_shift($this->deferredTokens) : null;
    }

    /**
     * end-of-source.
     */

    private function eos()
    {

        if (mb_strlen($this->input)) return null;
        if (sizeof($this->indentStack)) {
            array_shift($this->indentStack);
            return $this->token('outdent');
        }

        return $this->token('eos');
    }

    /**
     * Blank line.
     */

    private function blank()
    {

        if (preg_match('/^\n *\n/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]) - 1); // do not consume the last \r
            $this->lineno++;
            if ($this->pipeless) return $this->token('text');
            return $this->next();
        }
        return false;
    }

    /**
     * Comment.
     */
    private function comment()
    {


        if (preg_match('/^ *\/\/(-)?([^\n]*)/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $token = $this->token('comment', isset($captures[2]) ? $captures[2] : '');
            $token->buffer = '-' !== $captures[1];

            return $token;
        }
    }

    private function interpolation()
    {

        if (preg_match('/^#\{/', $this->input)) {
            $match = $this->bracketExpression(1);
            $this->consume($match->end + 1);
            return $this->token('interpolation', $match->src);
        }
    }


    private function tag()
    {

        if (preg_match('/^(\w[-:\w]*)(\/?)/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $name = $captures[1];
            if (':' == mb_substr($name, -1)) {

                $name = mb_substr($name, 0, -1);
                $token = $this->token('tag', $name);
                $this->defer($this->token(':'));

                while (' ' == $this->input[0]) $this->input = mb_substr($this->input, 1);
            } else {
                $token = $this->token('tag', $name);
            }
            $token->selfClosing = $captures[2] == '/';
            return $token;
        }
    }

    private function filter()
    {
        return $this->scan('/^:(\w+)/', 'filter');
    }

    private function doctype()
    {
        return $this->scan('/^(?:!!!|doctype) *([^\n]+)?/', 'doctype');
    }

    private function id()
    {
        return $this->scan('/^#([\w-]+)/', 'id');
    }

    /**
     * Class.
     */

    private function className()
    {
        return $this->scan('/^\.([\w-]+)/', 'class');
    }

    /**
     * Text.
     */
    private function text()
    {

        /*if (preg_match('/^([^\.\<][^\n]+)/', $this->input)
            && !preg_match('/^(?:\| ?| )([^\n]+)/', $this->input)
        ) {
            throw new \Exception('Warning: missing space before text for line ' . $this->getLineno() . ' of jade file.');
        }*/
/*        return this.scan(/^(?:\| ?| )([^\n]+)/, 'text') ||
      this.scan(/^\|?( )/, 'text') ||
      this.scan(/^(<[^\n]*)/, 'text');*/
        $text = $this->scan('/^(?:\| ?| )([^\n]+)/', 'text')
        or $text = $this->scan('/^\|?( )/', 'text') // '/^([^\.][^\n]+)/';
        or $text = $this->scan('/^(<[^\n]*)/', 'text');
        return $text;
    }

    /**
     * Dot.
     */
    private function dot()
    {
        return $this->scan('/^\./', 'dot');
    }

    /**
     * @return int
     */
    public function getLineno()
    {
        return $this->lineno;
    }

    /**
     * Extends.
     */
    private function _extends()
    {
        return $this->scan('/^extends? +([^\n]+)/', 'extends');
    }

    /**
     * Block prepend.
     */
    private function prepend()
    {

        if (preg_match('/^prepend +([^\n]+)/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $token = $this->token('block', $captures[1]);
            $token->mode = 'prepend';
            return $token;
        }
    }

    /**
     * Block append.
     */
    private function append()
    {

        if (preg_match('/^append +([^\n]+)/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $token = $this->token('block', $captures[1]);
            $token->mode = 'append';
            return $token;
        }
    }

    /**
     * Block.
     */
    private function block()
    {

        if (preg_match("/^block\b *(?:(prepend|append) +)?([^\n]+)/", $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $token = $this->token('block', $captures[2]);
            $token->mode = (mb_strlen($captures[1]) == 0) ? 'replace' : $captures[1];
            return $token;
        }
    }

    /**
     * Mixin Block.
     */
    private function mixinBlock()
    {

        if (preg_match('/^block\s*\n/', $this->input, $matches)) {
            $this->consume(mb_strlen($matches[0]) - 1);
            return $this->token('mixin-block');
        }
    }

    /**
     * Yield.
     */
    private function _yield()
    {

        return $this->scan('/^yield */', 'yield');
    }

    /**
     * Include.
     */
    private function _include()
    {

        return $this->scan('/^include +([^\n]+)/', 'include');
    }

    /**
     * Case.
     */
    private function _case()
    {

        return $this->scan('/^case +([^\n]+)/', 'case');
    }

    /**
     * When.
     */
    private function when()
    {

        return $this->scan('/^when +([^:\n]+)/', 'when');
    }

    /**
     * Default.
     */
    private function _default()
    {

        return $this->scan('/^default */', 'default');
    }

    /**
     * Assignment.
     */
    private function assignment()
    {

        if (preg_match('/^([\w_]+) += *([^\n]+)/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $name = $captures[1];
            $val = trim($captures[2]);
            if ($val[mb_strlen($val) - 1] === ';') {
                $val = mb_substr($val, 0, -1);
            }
            $this->assertExpression($val);
            return $this->token('code', '$' . $name . ' = (' . $val . ');');
        }
    }

    private function call()
    {

        if (preg_match('/^\+([-\w]+)/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $tok = $this->token('call', $captures[1]);

            // Check for args (not attributes)
            if (preg_match('/^ *\(/', $this->input, $captures)) {
                $range = $this->bracketExpression(mb_strlen($captures[0]) - 1);
                if (0 == preg_match('/^ *[-\w]+ *=/', $range->src)) { // not attributes
                    $this->consume($range->end + 1);
                    $tok->args = $range->src;
                }
            }

            return $tok;
        }
    }

    private function mixin()
    {

        if (preg_match('/^mixin +([-\w]+)(?: *\((.*)\))? */', $this->input, $captures)) {
            $this->consume($captures[0]);
            $token = $this->token('mixin', $captures[1]);
            $token->args = isset($captures[2]) ? $captures[2] : null;
            return $token;
        }
    }

    /**
     * Conditional.
     */

    private function conditional()
    {

        if (preg_match('/^(if|unless|else if|else)\b([^\n]*)/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $type = $captures[1];
            $php = $captures[2];

            switch ($type) {
                case 'if':
                    $this->assertExpression($php);
                    $php = 'if (' . $php . '):';
                    break;
                case 'unless':
                    $this->assertExpression($php);
                    $php = 'if (!(' . $php . ')):';
                    break;
                case 'else if':
                    $this->assertExpression($php);
                    $php = 'else if (' . $php . '):';
                    break;
                case 'else':
                    if ($php && trim($php)) {
                        throw new \Exception('`else` cannot have a condition, perhaps you meant `else if`');
                    }
                    $php = 'else:';
                    break;
            }

            return $this->token('code', $php);
        }
    }

    /**
     * While.
     */
    private function _while()
    {

        if (preg_match('/^while +([^\n]+)/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $this->assertExpression($captures[1]);
            $this->token('code', 'while (' . $captures[1] . '):');
        }
        return false;
    }

    /**
     * Each.
     * @return Token
     */
    private function _each()
    {

        if (preg_match('/^(?:- *)?(?:each|for) +([a-zA-Z_$][\w$]*)(?: *, *([a-zA-Z_$][\w$]*))? * in *([^\n]+)/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $token = $this->token('each', $captures[1]);
            $token->key = $captures[2];
            $this->assertExpression($captures[3]);
            $token->code = $captures[3];
            return $token;
        }
    }

    /**
     * Code.
     */
    private function code()
    {

        if (preg_match('/^(!?=|-)[ \t]*([^\n]+)/', $this->input, $captures)) {
            $this->consume(mb_strlen($captures[0]));
            $flags = $captures[1];
            $captures[1] = $captures[2];
            $captures[1] = preg_replace('/\(+(.*?)\)+/','$1',$captures[1] );
            $token = $this->token('code', $captures[1]);
            $token->escape = $flags[0] === '=';
            $token->buffer = '=' === $flags[0] || (isset($flags[1]) && '=' === $flags[1]);
            if ($token->buffer) $this->assertExpression($captures[1]);
            return $token;
        }
    }

    /**
     * Attributes.
     */
    protected function attrs()
    {

        if ($this->input[0] === '(') {
            $index = $this->bracketExpression()->end;
            $str = mb_substr($this->input, 1, $index - 1);
            //$str = preg_replace('/(\s*=\s*)/', '=', $str);
            $equals = '=';
            $this->assertNestingCorrect($str);
            $this->consume($index + 1);
            $token = $this->token('attributes');
            $token->attrs = array();
            $token->escaped = array();
            $token->selfClosing = false;

            $key = '';
            $val = '';
            $quote = '';
            $escapedAttr = true;
            $interpolatable = '';
            $loc = 'key';
            $characterParser = new CharacterParser();
            $state = $characterParser->defaultState();

            $isEndOfAttribute = function ($i) use (&$key, &$str, &$loc, &$state, &$val, &$quote) {
                if (trim($key) === '') return false;
                if (($i) === mb_strlen($str)) return true;
                if ('key' == $loc) {
                    if ($str[$i] == ' ' || $str[$i] == "\n" || $str[$i] == "\r\n") {
                        for ($x = $i; $x < mb_strlen($str); $x++) {
                            if ($str[$x] != ' ' && $str[$x] != "\n" && $str[$x] != "\r\n") {
                                if ($str[$x] == '=' || $str[$x] == '!' || $str[$x] == ',') return false;
                                else return true;
                            }
                        }
                    }
                    return $str[$i] === ',';
                } else if ($loc == 'value' && !$state->isNesting()) {
                    try {
                        $this->assertExpression($val);
                        if ($str[$i] == ' ' || $str[$i] == "\n" || $str[$i] == "\r\n") {
                            echo "\ntest>".$str[$i]."<";
                            for ($x = $i; $x < mb_strlen($str); $x++) {
                                echo "\ntest2>".$str[$x]."<";
                                if ($quote && $str[$x] != ' ' && $str[$x] != "\n" && $str[$x] != "\r\n") {
                                    if (CharacterParser::isPunctuator($str[$x]) && $str[$x] != '"' && $str[$x] != "'")
                                        return false;
                                    else
                                        return true;
                                }
                            }
                        }
                        return $str[$i] === ',';
                    } catch (\Exception $ex) {
                        return false;
                    }
                }
            };

            $this->lineno += sizeof(mb_split("\n", $str)) - 1;

            for ($i = 0; $i <= mb_strlen($str); ++$i) {
                if ($isEndOfAttribute($i)) {
                    echo "\n endofattr";
                    $val = trim($val);
                    if (isset($val[0]) && $val[0] == '"')
                        $val = trim($val,'"');
                    elseif (isset($val[0]) && $val[0] == '\'' && $val[strlen($val)-1] == '\'')
                        $val = trim($val,'\'');
                    elseif ('false' == $val)
                        $val = false;
                    elseif ('true' == $val)
                        $val = true;
                    if (isset($val[strlen($val)-1]) && $val[strlen($val)-1] == '.')
                        $val .= '"';
                    if ($val) $this->assertExpression($val);
                    //Only clean up if attribute value is a string
                    /*if(is_string($val))
                        $val = preg_replace('/\(+(.*?)\)+/','$1',$val)*/
                    $key = preg_replace('/^[\'"]|[\'"]$/', '', $key);
                    $token->attrs[] = ['name' => trim($key),'val' => is_string($val)?htmlspecialchars($val):$val, 'escaped' => $escapedAttr];
                    $quote = $key = $val = '';
                    $loc = 'key';
                    $escapedAttr = false;
                } else {
                    switch ($loc) {
                        case 'key-char':
                            if ($str[$i] === $quote) {
                                $loc = 'key';
                                if ($i + 1 < mb_strlen($str) && !in_array($str[$i + 1], [' ', ',', '!', '=', "\n", "\r\n"]))
                                    throw new \Exception('Unexpected character ' . $str[$i + 1] . ' expected ` `, `\\n`, `,`, `!` or `=`');
                            } else if ($loc === 'key-char') {
                                $key .= $str[$i];
                            }
                            break;
                        case 'key':
                            if ($key == '' && (isset($str[$i]) && ($str[$i] == '"' || $str[$i] == "'"))) {
                                $loc = 'key-char';
                                $quote = $str[$i];
                            } else if (isset($str[$i]) && ($str[$i] === '!' || $str[$i] === '=')) {
                                $escapedAttr = $str[$i] !== '!';
                                if ($str[$i] === '!') $i++;
                                if ($str[$i] !== '=')
                                    throw new \Exception('Unexpected character ' . $str[$i] . ' expected `=`');
                                $loc = 'value';
                                $quote = '';
                                $state = $characterParser->defaultState();
                            } elseif (isset($str[$i]) && $str[$i]!=' ') {
                                $key .= $str[$i];
                            }

                            break;
                        case 'value':
                            $state = $characterParser->parseChar($str[$i], $state);
                            if ($state->isString()) {
                                $loc = 'string';
                                $quote = $str[$i];
                                $interpolatable = $str[$i];
                            } else {
                                $val .= $str[$i];
                            }
                            break;
                        case 'string':
                            $state = $characterParser->parseChar($str[$i], $state);
                            $interpolatable .= $str[$i];
                            if (!$state->isString()) {
                                $loc = 'value';
                                //$interpolatable = trim($interpolatable,"'");
                                $val .= $this->interpolate($interpolatable, $quote);
                            }
                            break;
                    }
                    echo "\nattr: key:$key , val:$val, loc:$loc, quote:$quote;";
                }
            }
            if (isset($this->input[0]) && '/' == $this->input[0]) {
                $this->consume(1);
                $token->selfClosing = true;
            }
            return $token;
        }
    }

    private function interpolate($attr, $quote)
    {
        return str_replace('\#{', '#{', preg_replace_callback('/(\\\\)?#{(.+)/', function ($_) use (&$quote) {
            $escape = $_[1];
            $expr = $_[2];
            $_ = $_[0];
            if ($escape) return $_;
            try {
                $range = (new CharacterParser())->parseMax($expr);
                if ($expr[$range->end] !== '}') return mb_substr($_, 0, 2) . $this->interpolate(mb_substr($_, 2), $quote);
                self::assertExpression($range->src);
                $data = $this->interpolate(mb_substr($expr, $range->end + 1), $quote);
                $data =  '" . phade_escape($' . preg_replace('/\(+(.*?)\)+/','$1',$range->src ) .') . "' . $data;
                return  str_replace('""','"', $data);
            } catch (\Exception $ex) {
                return mb_substr($_, 0, 2) . $this->interpolate(mb_substr($_, 2), $quote);
            }
        }, $attr));
    }

    /**
     * Indent | Outdent | Newline.
     */

    private function indent()
    {

        // established regexp
        if ($this->indentRe) {
            preg_match($this->indentRe, $this->input, $captures);
            // determine regexp
        } else {
            // tabs
            $re = '/^\n(\t*) */';
            preg_match($re, $this->input, $captures);

            // spaces
            if ($captures && mb_strlen($captures[1]) == 0) {
                $re = '/^\n( *)/';
                preg_match($re, $this->input, $captures);
            }

            // established
            if ($captures && mb_strlen($captures[1])) $this->indentRe = $re;
        }
        if ($captures) {
            $indents = mb_strlen($captures[1]);

            ++$this->lineno;
            $this->consume($indents + 1);

            if (' ' == $this->input[0] || "\t" == $this->input[0]) {
                throw new \Exception('Invalid indentation, you can use tabs or spaces but not both');
            }
            // blank line
            if ("\n" == $this->input[0]) return $this->token('newline');
            // outdent
            if (isset($this->indentStack[0]) && $indents < $this->indentStack[0]) {
                while (sizeof($this->indentStack) && $this->indentStack[0] > $indents) {
                    array_push($this->stash, $this->token('outdent'));
                    array_shift($this->indentStack);
                }
                $tok = array_pop($this->stash);
                // indent
            } else if ($indents && (!isset($this->indentStack[0]) || $indents != $this->indentStack[0])) {
                array_unshift($this->indentStack, $indents);
                $tok = $this->token('indent', $indents);
                // newline
            } else {
                $tok = $this->token('newline');
            }

            return $tok;
        }
    }

    private function pipelessText()
    {

        if ($this->pipeless && "\n" != $this->input[0]) {
            $i = mb_strpos($this->input, "\n");
            if ($i === false) {
                $i = mb_strlen($this->input);
            }
            $str = mb_substr($this->input, 0, $i); // do not include the \n char
            $this->consume(mb_strlen($str));
            return $this->token('text', $str);
        }
    }

    /**
     * ':'
     * @return Token
     */
    private function colon()
    {


        return $this->scan('/^: */', ':');
    }

    /**
     * @throws \Exception
     */
    private function fail()
    {


        throw new \Exception('unexpected text ' . mb_substr($this->input, 0, 6));
    }

    /**
     * Return the next token object, or those
     * previously stashed by lookahead.
     *
     * @return Token
     */
    public function advance()
    {

        $advance = $this->stashed() or $advance = $this->next();
        return $advance;
    }

    /**
     * Return the next token object.
     *
     * @return Token
     */
    public function next()
    {
        static $count;
        if (!isset($count))
            $count = 0;
        $count++;


        $next = $this->deferred()
        or $next = $this->blank()
        or $next = $this->eos()
        or $next = $this->pipelessText()
        or $next = $this->_yield()
        or $next = $this->doctype()
        or $next = $this->interpolation()
        or $next = $this->_case()
        or $next = $this->when()
        or $next = $this->_default()
        or $next = $this->_extends()
        or $next = $this->append()
        or $next = $this->prepend()
        or $next = $this->block()
        or $next = $this->mixinBlock()
        or $next = $this->_include()
        or $next = $this->mixin()
        or $next = $this->call()
        or $next = $this->conditional()
        or $next = $this->_each()
        or $next = $this->_while()
        or $next = $this->assignment()
        or $next = $this->tag()
        or $next = $this->filter()
        or $next = $this->code()
        or $next = $this->id()
        or $next = $this->className()
        or $next = $this->attrs()
        or $next = $this->text()
        or $next = $this->indent()
        or $next = $this->comment()
        or $next = $this->colon()
        or $next = $this->dot()
        or $next = $this->fail();

        return $next;
    }

    private function assertExpression($val)
    {
        if (@eval($val)) {
            throw new \Exception(sprintf('Not correct expression, expressions need to return true: %s', $val));
        }
    }

    private function assertNestingCorrect($str)
    {
    }
} 
