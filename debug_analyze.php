<?php

require_once 'vendor/autoload.php';

use RegexParser\Regex;

$regex = Regex::create();
$pattern = '/[\x7f-\xff]/';

// Let's directly create a visitor to test
$visitor = new ExplainNodeVisitor();

// Test the explainLiteral method directly via reflection
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

echo "Full explanation:\n$explain\n";

// Let's check the AST structure
$ast = $regex->parse($pattern);
echo "AST type: " . get_class($ast) . "\n";

// Let's manually traverse to see what we get
use RegexParser\NodeVisitor\ExplainNodeVisitor;
$visitor = new ExplainNodeVisitor();
$ast = $regex->parse($pattern);

// Check if this is a RegexNode with a pattern that's a CharClassNode
if (method_exists($ast, 'pattern')) {
    $patternNode = $ast->pattern;
    echo "Pattern node type: " . get_class($patternNode) . "\n";
    
    if (method_exists($patternNode, 'expression')) {
        $expression = $patternNode->expression;
        echo "Expression type: " . get_class($expression) . "\n";
        
        if ($expression instanceof \RegexParser\Node\RangeNode) {
            echo "Found range node!\n";
            echo "Start type: " . get_class($expression->start) . "\n";
            echo "End type: " . get_class($expression->end) . "\n";
            
            if ($expression->start instanceof \RegexParser\Node\LiteralNode) {
                echo "Start value: [" . $expression->start->value . "] ord: " . ord($expression->start->value) . "\n";
            }
            
            if ($expression->end instanceof \RegexParser\Node\LiteralNode) {
                echo "End value: [" . $expression->end->value . "] ord: " . ord($expression->end->value) . "\n";
            }
        }
    }
}