<?php

require_once 'vendor/autoload.php';

use RegexParser\Regex;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

$regex = Regex::create();
$pattern = '/[\x7f-\xff]/';
$ast = $regex->parse($pattern);

// Let's check the CharClassNode directly
if (method_exists($ast, 'pattern')) {
    $patternNode = $ast->pattern;
    
    if (method_exists($patternNode, 'expression')) {
        $expression = $patternNode->expression;
        echo "Expression class: " . get_class($expression) . "\n";
        
        if ($expression instanceof \RegexParser\Node\AlternationNode) {
            echo "It's an AlternationNode with " . count($expression->alternatives) . " alternatives\n";
            foreach ($expression->alternatives as $i => $alt) {
                echo "Alternative $i class: " . get_class($alt) . "\n";
                if ($alt instanceof \RegexParser\Node\RangeNode) {
                    echo "  Found RangeNode in alternative $i!\n";
                    
                    if ($alt->start instanceof \RegexParser\Node\LiteralNode) {
                        $startVal = $alt->start->value;
                        echo "  Start raw value: [" . $startVal . "] ord: " . ord($startVal) . "\n";
                    }
                    
                    if ($alt->end instanceof \RegexParser\Node\LiteralNode) {
                        $endVal = $alt->end->value;
                        echo "  End raw value: [" . $endVal . "] ord: " . ord($endVal) . "\n";
                    }
                    
                    // Now let's call visitRange manually on this range
                    $visitor = new ExplainNodeVisitor();
                    $rangeResult = $visitor->visitRange($alt);
                    echo "  visitRange result: $rangeResult\n";
                }
            }
        }
    }
}

// Test final explanation
$explain = $regex->explain($pattern);
echo "Final explanation:\n$explain\n";