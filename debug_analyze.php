<?php

require_once 'vendor/autoload.php';

use RegexParser\Regex;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

$regex = Regex::create();
$pattern = '/[\x7f-\xff]/';

// Test explainLiteral method directly via reflection
$visitor = new ExplainNodeVisitor();
$reflection = new ReflectionClass($visitor);
$method = $reflection->getMethod('explainLiteral');
$method->setAccessible(true);

$char7f = "\x7f";
$charff = "\xff";

echo "Direct explainLiteral test:\n";
echo "0x7f: " . $method->invoke($visitor, $char7f) . "\n";
echo "0xff: " . $method->invoke($visitor, $charff) . "\n";

// Now test the full explanation
$explain = $regex->explain($pattern);

echo "Pattern: $pattern\n";
echo "Full explanation:\n$explain\n";

// Let's check AST structure
$ast = $regex->parse($pattern);

// Check if this is a RegexNode with a pattern that's a CharClassNode
if (method_exists($ast, 'pattern')) {
    $patternNode = $ast->pattern;
    
    if (method_exists($patternNode, 'expression')) {
        $expression = $patternNode->expression;
        
        if ($expression instanceof \RegexParser\Node\RangeNode) {
            echo "\nFound range node!\n";
            echo "Start type: " . get_class($expression->start) . "\n";
            echo "End type: " . get_class($expression->end) . "\n";
            
            if ($expression->start instanceof \RegexParser\Node\LiteralNode) {
                $startVal = $expression->start->value;
                echo "Start raw value: [" . $startVal . "] ord: " . ord($startVal) . "\n";
                echo "Start explainLiteral: " . $method->invoke($visitor, $startVal) . "\n";
            }
            
            if ($expression->end instanceof \RegexParser\Node\LiteralNode) {
                $endVal = $expression->end->value;
                echo "End raw value: [" . $endVal . "] ord: " . ord($endVal) . "\n";
                echo "End explainLiteral: " . $method->invoke($visitor, $endVal) . "\n";
            }
        }
    }
}