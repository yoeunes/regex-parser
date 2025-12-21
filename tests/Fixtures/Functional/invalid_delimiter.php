<?php
// Strings starting with '?' are likely URL query strings, NOT regex patterns - should NOT be detected
preg_match("?entryPoint=", $str);
preg_match('?foo=bar&baz=qux', $str);

// Single character patterns should NOT be detected (no closing delimiter)
preg_match("^", $str);
preg_match("[", $str);
