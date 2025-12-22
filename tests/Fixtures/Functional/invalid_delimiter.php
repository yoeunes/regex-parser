<?php
// These are intentionally invalid or URL-like patterns passed to preg_match.
preg_match("?entryPoint=", (string) $str);
preg_match('?foo=bar&baz=qux', (string) $str);

// Single character patterns with missing delimiters.
preg_match("^", (string) $str);
preg_match("[", (string) $str);
