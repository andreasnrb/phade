<?php

function phade_merge($a, $b)
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

function phade_attrs($obj, $escaped)
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
