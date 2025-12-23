<?php

require_once __DIR__ . '/vendor/autoload.php';

use RegexParser\Lexer;

$lexer = new Lexer();
$pattern = '(?';

echo "Testing pattern: $pattern\n";

// Check what the compiled regex looks like
$reflection = new ReflectionClass($lexer);
$method = $reflection->getMethod('getRegexOutside');
$method->setAccessible(true);
$regex = $method->invoke($lexer);

echo "Compiled regex: $regex\n";

if (preg_match($regex, $pattern, $matches, PREG_UNMATCHED_AS_NULL)) {
    echo "Matches:\n";
    foreach ($matches as $key => $value) {
        if ($key !== 0) {
            echo "  $key: '$value'\n";
        }
    }
} else {
    echo "No match!\n";
}