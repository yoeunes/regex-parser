<?php
// preg_quote takes a raw string, NOT a regex pattern - should NOT be detected
$escaped = preg_quote("foo.bar*baz", '/');
$escaped2 = preg_quote('special+chars?here');
