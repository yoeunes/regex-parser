<?php
// These are intentionally invalid or URL-like patterns passed to preg_match.
preg_match("?entryPoint=", $str);
preg_match('?foo=bar&baz=qux', $str);

// Single character patterns with missing delimiters.
preg_match("^", $str);
preg_match("[", $str);
