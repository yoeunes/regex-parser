<?php

$pattern = "/QUICK_CHECK = .*;/m";
var_dump(preg_match('/^([\'"{\/\#~%])(.*?)([\'"{\/\#~%])([a-zA-Z]*)$/', $pattern, $matches));
var_dump($matches);