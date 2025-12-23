<?php

require_once __DIR__ . '/vendor/autoload.php';

use RegexParser\Lexer;

$lexer = new Lexer();
$pattern = '(?(*CR)a)';

echo "Tokenizing pattern: $pattern\n";

try {
    $tokenStream = $lexer->tokenize($pattern);
    echo "Tokens:\n";
    foreach ($tokenStream as $token) {
        echo "  " . $token->type->value . ": '" . $token->value . "' at pos " . $token->position . "\n";
    }
} catch (Exception $e) {
    echo "Tokenization failed: " . $e->getMessage() . "\n";
}