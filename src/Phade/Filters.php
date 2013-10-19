<?php
namespace Phade;
class Filters {
    private $filter;
    private $transformers;
    function filter($name, $str, $options) {
        if (is_callable($this->filter[$name])) {
            $res = $filter[$name]($str, $options);
        } else if ($this->transformers[$name]) {
            $res = $this->transformers[$name]->renderSync($str, $options);
            if ($this->transformers[$name]->outputFormat === 'js') {
                $res = "<script type=\"text/javascript\">\n" . $res .'</script>';
            } else if ($this->transformers[$name]->outputFormat === 'css') {
                $res = '<style type="text/css">' . $res . '</style>';
            } else if ($this->transformers[$name]->outputFormat === 'xml') {
                $res = preg_replace("/'/", '&#39;', $res);
            }
        } else {
            throw new \Exception('unknown filter ":' . $name . '"');
        }
        return $res;
    }
    function exists($name, $str, $options) {
        return is_callable($this->filter[$name]) || $this->transformers[$name];
    }
}