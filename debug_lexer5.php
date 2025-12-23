<?php

require_once __DIR__ . '/vendor/autoload.php';

use RegexParser\Lexer;

$lexer = new Lexer();
$pattern = '(?(*CR)';

echo "Testing pattern: $pattern\n";

$reflection = new ReflectionClass($lexer);
$method = $reflection->getMethod('getRegexOutside');
$method->setAccessible(true);
$regex = $method->invoke($lexer);

if (preg_match($regex, $pattern, $matches, PREG_UNMATCHED_AS_NULL)) {
    echo "Matches: " . $matches[0] . "\n";
    foreach ($matches as $key => $value) {
        if ($key !== 0 && !is_null($value)) {
            echo "  $key: '$value'\n";
        }
    }
} else {
    echo "No match!\n";
}