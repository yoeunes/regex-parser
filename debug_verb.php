<?php

require_once __DIR__ . '/vendor/autoload.php';

use RegexParser\Regex;

$regex = Regex::create();

echo "Debugging: /(?(*CR)a)/\n";

try {
    $tokens = $regex->tokenize('/(?(*CR)a)/');
    echo "Tokens:\n";
    foreach ($tokens as $token) {
        echo "  " . $token->type->value . ": '" . $token->value . "' at pos " . $token->position . "\n";
    }
} catch (Exception $e) {
    echo "Tokenization failed: " . $e->getMessage() . "\n";
}

echo "\nParsing:\n";
try {
    $ast = $regex->parse('/(?(*CR)a)/');
    echo "Parse successful!\n";
} catch (Exception $e) {
    echo "Parse failed: " . $e->getMessage() . "\n";
}