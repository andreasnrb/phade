<?php

use Phade\CharacterParser;

function phade_merge($a, $b, $scope)
{
    if (!isset($a['class']) && !isset($b['class']))
        return array_merge($a, $b);
    if (!isset($a['class']))
        $a['class'] = $a;
    if (!isset($b['class']))
        $b['class'] = $b;
    $ac = $a['class'];
    $bc = $b['class'];
    if (!is_array($ac)) $ac = [$ac];
    if (!is_array($bc)) $bc = [$bc];
    $a['class'] = array_merge($ac, $bc);
    $a['class'] = array_filter($a['class'], 'phade_nulls');
    $a['class'] = array_values($a['class']);
    return $a;
    if ($ac || $bc) {
        $ac = $ac || [];
        $bc = $bc || [];
        if (!is_array($ac)) $ac = [$ac];
        if (!is_array($bc)) $bc = [$bc];
        $a['class'] = array_filter(array_merge($ac, $bc), 'strlen');
    }

    foreach ($b as $key => $val) {
        if ($key != 'class') {
            $a[$key] = $b[$key];
        }
    }

    return $a;
}

;

function phade_nulls($val)
{
    return $val != '' && !in_array($val, [null, 'null', '', 'undefined']);
}

function phade_attrs($obj, $escaped, $scope=[])
{
    $buf = [];
    $terse = isset($obj[0]['terse']) ? $obj[0]['terse'] : false;
    if ($terse) {
        unset($obj[0]);
        $obj = array_values($obj);
    }
    $keys = array_keys($obj);
    $len = sizeof($keys);
    if ($len) {
        array_push($buf, '');
        for ($i = 0; $i < $len; ++$i) {
            $attr = $obj[$i];
            $key = $attr['name'];
            $val = $attr['val'];
            if(isset($attr['code']) && $attr['code']) {
                if(in_array($val, ['false', 'null', 'undefined'],true)) {
                    $val = false;
                } elseif(in_array($val,['true'], true)) {
                    $val = true;
                } elseif ($scope && array_key_exists($val,$scope)) {
                    $val = $scope[$val];
                    if(is_null($val))
                        $val = false;
                } elseif (!in_array($val, [true, false, 'false', 'true'], true)) {
                    $val = preg_replace('/"\ ?\.(.*)\ \.\ ?"/','$1', $val);
                    $val = '".(' . phade_convertJStoPHP($val) . ')."';
                }
            }
            if (   $val == 1
                || null === $val
                || $val === true
                || in_array($val, ['false', 'null', 'undefined'])
                || in_array($key, ['checked', 'disabled'])
                ) {
                if (   $val == 1
                    || $val === true
                    || ($val && !in_array($val, ['false', 'null', 'undefined'])
                    && ($val !==false && in_array($key, ['checked', 'disabled']))
                    || ($val !==false && !in_array($key, ['checked', 'disabled'])))
                ) {
                    if (substr($key, 0, 5) == 'data-')
                        array_push($buf, $key . '=\"' . $val . '\"');
                    else
                        $terse
                            ? array_push($buf, $key)
                            : array_push($buf, $key . '=\"' . $key . '\"');
                } elseif($val !== false && !in_array($key, ['checked', 'disabled'])) {
                    array_push($buf, $key . '=\"' . $val . '\"');
                } elseif(!$terse && $val === '' && in_array($key, ['checked', 'disabled'])) {
                    array_push($buf, $key . '=\"' . $key . '\"');
                } elseif ($val !== false && !in_array($val, ['false', 'null', 'undefined'])) {
                    array_push($buf, $key);
                }
            } else if (0 === strpos($key, 'data') && !is_string($val)) {
                array_push($buf, $key . "='" . preg_replace("/'/", '&apos;', json_encode($val)) . "'");
            } else if ('class' == $key) {
                if ($escaped && $escaped[$key]) {
                    if ($val = phade_escape(phade_join_classes($val))) {
                        array_push($buf, $key . '=\"' . $val . '\"');
                    }
                } else {
                    if ($val = phade_join_classes($val)) {
                        array_push($buf, $key . '=\"' . $val . '\"');
                    }
                }
            } else if ($escaped && $escaped[$key]) {
                if($val!==false)
                    array_push($buf, $key . '=\"' . $val . '\"');
            } elseif (false !== $val) {
                $val = $val == '' ? $key : $val;
                array_push($buf, $key . '=\"' . $val . '\"');
            }
        }
    }

    return join($buf, ' ');
}

;

/**
 * @param $html
 * @return string
 */
function phade_escape($html = '')
{
    return htmlspecialchars($html);
}

;

/**
 * join array as classes.
 *
 * @param mixed $val
 * @return string
 * @api private
 */
function phade_join_classes($val)
{
    return is_array($val) ? join(array_filter(array_map($val, 'phade_join_classes'), 'strlen'), ' ') : $val;
}
function phade_convertJStoPHP($src, $type ='') {
    $characterParser = new CharacterParser();

    /**
     * (\bvar\s+\b|.+\+\s)([\w\d]+).*? captures var (var), 'asd' + (var)
     */
    if (defined('PHADE_TEST_DEBUG') && PHADE_TEST_DEBUG)
        echo __METHOD__,': ', $src,"\n";
    $isVar = $newVar = true;
    $phpSrc='';
    if ($characterParser->isNull($src))
        return '';
    if ('var' == $type)
        return '$' . $src;

    if (ctype_digit($src) || __()->isNumber($src)) {
        return $src;
    }

    if ($src != ($code = preg_replace('/var\s+([\w\d]+)/', '\$$1',$src)))
        return $code;

    if ( $characterParser->isType($src))
        return "'$src'";
    if ($characterParser->isNull($src))
        return '';

    for($i = 0,$len = strlen($src);$i<$len;++$i) {
        if ($isVar && $newVar && " " != $src[$i] && !$characterParser->isNonChar($src[$i])) {
            $phpSrc .= '$';
            $isVar = $newVar = false;
        } elseif ($characterParser->isNonChar($src[$i])) {
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
        /**
         * @var int $i
         */
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
        $phpSrc = preg_replace('/(\".+\")\ *(\+\ *(\d+))/','$1."$3"',$phpSrc);
        return $phpSrc = '($phade_interp = (' . $phpSrc . ')) == null ? \'\' : $phade_interp';
    }
    return $phpSrc;
}