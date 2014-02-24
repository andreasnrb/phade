<?php

function phade_merge($a, $b) {
    $ac = $a['class'];
  $bc = $b['class'];

  if ($ac || $bc) {
      $ac = $ac || [];
      $bc = $bc || [];
      if (!is_array($ac)) $ac = [$ac];
      if (!is_array($bc)) $bc = [$bc];
      $a['class'] = array_filter(array_merge($ac, $bc), 'strlen');
  }

  foreach($b as $key => $val) {
        if ($key != 'class') {
            $a[$key] = $b[$key];
    }
    }

  return $a;
};

function phade_attrs($obj, $escaped){
    $buf = [];
    $terse = isset($obj[0]['terse']) ? $obj[0]['terse']: false;
    if ($terse) {
       unset($obj[0]);
        $obj = array_values($obj);
    }
    echo "phade_attrs:\n";
    var_dump($obj);
    $keys = array_keys($obj);
    $len = sizeof($keys);

  if ($len) {
      array_push($buf,'');
      for ($i = 0; $i < $len; ++$i) {
          $attr = $obj[$i];
        $key = $attr->name;
        $val = $attr->val;
      if (is_bool($val) || null == $val || $val == 'true' || $val == 'false') {
              if ($val == 'true' || ($val && $val != 'false')) {
                  $terse
                      ? array_push($buf, $key)
                      : array_push($buf, $key . '=\"\"');
              }
          } else if (0 === strpos($key,'data') && !is_string($val)) {
              array_push($buf, $key . "='" . preg_replace("/'/", '&apos;', json_encode($val)) . "'");
      } else if ('class' == $key) {
        if ($escaped && $escaped[$key]){
          if ($val = phade_escape(phade_join_classes($val))) {
            array_push($buf, $key . '=\"' . $val . '\"');
          }
        } else {
          if ($val = phade_join_classes($val)) {
            array_push($buf, $key . '=\"' . $val . '\"');
          }
        }
      } else if ($escaped && $escaped[$key]) {
        array_push($buf, $key . '=\"' . phade_escape($val) . '\"');
      } else {
        array_push($buf, $key . '=\"' . $val . '\"');
      }
    }
  }

  return join($buf, ' ');
};

/**
 * @param $html
 * @return string
 */
function phade_escape($html = ''){
    return htmlspecialchars($html);
};

/**
 * join array as classes.
 *
 * @param mixed $val
 * @return string
 * @api private
 */
function phade_join_classes($val) {
    return is_array($val) ? join(array_filter(array_map($val, 'phade_join_classes'), 'strlen'),' ') : $val;
}
