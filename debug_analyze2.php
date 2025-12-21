<?php

require_once 'vendor/autoload.php';

use RegexParser\Regex;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

$regex = Regex::create();
$pattern = '/[\x7f-\xff]/';
$ast = $regex->parse($pattern);

// Create visitor and manually call visitRange to see what happens
$visitor = new ExplainNodeVisitor();

// Find the range node
if (method_exists($ast, 'pattern') && method_exists($ast->pattern, 'expression')) {
    $expression = $ast->pattern->expression;
    
    if ($expression instanceof \RegexParser\Node\RangeNode) {
        echo "Found range node, calling visitRange directly:\n";
        $rangeResult = $visitor->visitRange($expression);
        echo "visitRange result: $rangeResult\n";
        
        // Now let's test the individual parts
        echo "\nTesting individual literal explanations:\n";
        $reflection = new ReflectionClass($visitor);
        $method = $reflection->getMethod('explainLiteral');
        $method->setAccessible(true);
        
        if ($expression->start instanceof \RegexParser\Node\LiteralNode) {
            $startVal = $expression->start->value;
            echo "Start raw: [" . $startVal . "] ord: " . ord($startVal) . "\n";
            echo "Start explain: " . $method->invoke($visitor, $startVal) . "\n";
        }
        
        if ($expression->end instanceof \RegexParser\Node\LiteralNode) {
            $endVal = $expression->end->value;
            echo "End raw: [" . $endVal . "] ord: " . ord($endVal) . "\n";
            echo "End explain: " . $method->invoke($visitor, $endVal) . "\n";
        }
        
        // Let's also check what happens if we call visitLiteral directly on the start and end nodes
        echo "\nTesting visitLiteral calls:\n";
        if ($expression->start instanceof \RegexParser\Node\LiteralNode) {
            $startResult = $visitor->visitLiteral($expression->start);
            echo "visitLiteral start: $startResult\n";
        }
        
        if ($expression->end instanceof \RegexParser\Node\LiteralNode) {
            $endResult = $visitor->visitLiteral($expression->end);
            echo "visitLiteral end: $endResult\n";
        }
    }
}

// Now test the full explanation
echo "\nFull explanation:\n";
$explain = $regex->explain($pattern);
echo "$explain\n";