<?php
namespace Phade;
//include "vendor/cyonite/underscore.php/lib/skillshare/underscore.php";

use Phade\Exceptions\PhadeParseException;

class Jade
{
    private $cache;

    /**
     * Takes a Jade string and converts it to PHP using the provided sc
     * @param $str
     * @param array $scope
     * @param array $options
     * @param null $callback
     * @throws \Exception
     * @return mixed
     */
    public function render($str, $scope = [], $options = [], $callback = null)
    {
        $default = ['filename' => '', 'compileDebug' => false, 'client' => false, 'debug' => false, 'prettyprint' => false, 'cache' => null];
        $str = trim($str, "\n");
        $fn = null;
        // support callback API
        if (is_callable($callback)) {
            $fn = $callback;
        }
        $options = array_merge($default, $options);

        if (is_callable($fn)) {
            try {
                $res = $this->render($str, $scope, $options);
            } catch (\Exception $ex) {
                return $fn($ex, null);
            }
            return $fn(null, $res);
        }
        // cache requires .filename
        if ($options['cache'] && empty($options['filename'])) {
            throw new \Exception('the "filename" option is required for caching');
        }

        $path = $options['filename'];
        if ($options['cache']) {
            $tmpl = $this->cache[$path] or $tmpl = ($this->cache[$path] = $this->compile($str, $scope, $options));
        } else {
            $tmpl = $this->compile($str, $scope, $options);
        }
        return $tmpl($options);
    }

    public function compile($str, $scope = [], $options = [])
    {

        $default = ['filename' => '', 'compileDebug' => false, 'client' => false, 'debug' => false, 'prettyprint' => false, 'scope'=>$scope];
        $options = array_merge($default, $options);
        if (is_array($options))
            $options = (object)$options;
        $filename = isset($options->filename) ? json_encode($options->filename) : '';
	    $options->scope = $scope;
        if ($options->compileDebug) {
            $fn = join("\n", [
                '$phade_debug = [["lineno" => 1, "filename" => ' . $filename . ' ]];'
                , 'try {'
                , $this->parse($str, $options)
                , '} catch (\Exception $ex) {'
                , '     throw \Exception($ex->getMessage(), 0, $phade_debug[0]["filename"], $phade_debug[0]["lineno"]);'
                , '}'
            ]);
        } else {
            $fn = $this->parse($str, $options);
        }
        if ($options->client) return function ($locals = null) use (&$fn, &$scope) {
            return $fn($locals);
        };
        //$fn = function($locals, $jade = null) use (&$fn) { return $fn($locals);};
        return function ($locals = []) use (&$fn, &$scope) {
            if (defined('PHADE_TEST_DEBUG') && PHADE_TEST_DEBUG)
                echo print_r($fn, true),"\n";
            ob_start();
            extract($locals);
            extract($scope);
	        file_put_contents(dirname(__FILE__).'/test.php',$fn);
            $result = eval($fn);
            eval('?>' . $result);
            return ob_get_flush();
        };
    }

    /**
     * Parse the given `str` of jade and return a function body.
     *
     * @param string $str
     * @param array $options
     * @throws \Exception
     * @return callable
     */
    private function parse($str, $options = [])
    {
        if (is_array($options))
            $options = (object)$options;
        $parserClass = (isset($options->parser)) ? $options->parser : 'Phade\\Parser';
        $compilerClass = (isset($options->compiler)) ? $options->compiler : 'Phade\\Compiler';
        /**
         * @var Parser $parser
         */
        $parser = new $parserClass($str, $options->filename, $options);
        /**
         * @var Compiler $compiler
         */
        $compiler = new $compilerClass($parser->parse(), $options->filename, $options);

        try {
            $php = $compiler->compile();
            // Debug compiler
            if ($options->debug) {
                trigger_error("\nCompiled Function:\n\n\033[90m%s\033[0m", preg_replace('/^/', '  ', $php));
            }
            global $globals;
            $globals = isset($options->globals) && is_array($options->globals) ? $options->globals : [];
            array_push($globals, 'jade');
            array_push($globals, 'buf');

            return join("\n", [''
                , '$buf = [];'
                /*            , (isset($options->self)
                                ?*/
                //, '$self = $locals;' ."\n"
                , $php
                //    : $this->addWith('$locals', $php, $globals)) . ';'
                , 'return join(' .($options->prettyprint?"\"\n\"":'""').', $buf);']);
        } catch (\Exception $ex) {
            /**
             * @var Parser $context
             */
            $context = $parser->context();

            return new PhadeParseException('Parser error', $context->getFilename(), $context->getLexer()->getLineno(), $context->getInput(), $ex);
        }
    }
}
