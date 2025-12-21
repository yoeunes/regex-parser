<?php

require_once 'vendor/autoload.php';

use RegexParser\Regex;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

$regex = Regex::create();
$pattern = '/[\x7f-\xff]/';
$ast = $regex->parse($pattern);

echo "AST class: " . get_class($ast) . "\n";

if (method_exists($ast, 'pattern')) {
    $patternNode = $ast->pattern;
    echo "Pattern class: " . get_class($patternNode) . "\n";
    
    if (method_exists($patternNode, 'expression')) {
        $expression = $patternNode->expression;
        echo "Expression class: " . get_class($expression) . "\n";
        
        if ($expression instanceof \RegexParser\Node\RangeNode) {
            echo "It's a RangeNode!\n";
            echo "Start class: " . get_class($expression->start) . "\n";
            echo "End class: " . get_class($expression->end) . "\n";
        } else {
            echo "Not a RangeNode!\n";
        }
    }
}

// Test the full explanation
$visitor = new ExplainNodeVisitor();
$explain = $ast->accept($visitor);
echo "Full explanation:\n$explain\n";