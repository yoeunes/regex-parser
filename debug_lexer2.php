<?php

require_once __DIR__ . '/vendor/autoload.php';

use RegexParser\Lexer;

$lexer = new Lexer();
$pattern = '(?(*CR)a)';

echo "Testing pattern: $pattern\n";

// Check what the compiled regex looks like
$reflection = new ReflectionClass($lexer);
$method = $reflection->getMethod('getRegexOutside');
$method->setAccessible(true);
$regex = $method->invoke($lexer);

echo "Compiled regex length: " . strlen($regex) . "\n";

// Let's test step by step
$pos = 0;
$subpatterns = [
    '(?',
    '(*CR)a)'
];

foreach ($subpatterns as $subpattern) {
    echo "\nTesting substring: '$subpattern' at position $pos\n";
    
    $fullPattern = substr($pattern, 0, $pos) . $subpattern;
    
    if (preg_match($regex, $fullPattern, $matches, PREG_UNMATCHED_AS_NULL)) {
        echo "Matches: " . $matches[0] . "\n";
        foreach ($matches as $key => $value) {
            if ($key !== 0 && !is_null($value)) {
                echo "  $key: '$value'\n";
            }
        }
    } else {
        echo "No match!\n";
    }
    
    $pos += strlen($subpattern);
}