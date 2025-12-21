<?php

require_once 'vendor/autoload.php';

use RegexParser\Regex;
use RegexParser\NodeVisitor\ExplainNodeVisitor;

$regex = Regex::create();
$pattern = '/[\x7f-\xff]/';
$ast = $regex->parse($pattern);

echo "Debugging pattern: $pattern\n";

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
                    echo "  Start class: " . get_class($alt->start) . "\n";
                    echo "  End class: " . get_class($alt->end) . "\n";
                    
                    if ($alt->start instanceof \RegexParser\Node\LiteralNode) {
                        $startVal = $alt->start->value;
                        echo "  Start value: [" . $startVal . "] ord: " . ord($startVal) . "\n";
                    }
                    
                    if ($alt->end instanceof \RegexParser\Node\LiteralNode) {
                        $endVal = $alt->end->value;
                        echo "  End value: [" . $endVal . "] ord: " . ord($endVal) . "\n";
                    }
                }
            }
        }
    }
}

// Now let's test visitor with a simple custom visitor
class DebugVisitor extends ExplainNodeVisitor {
    public function visitRange(\RegexParser\Node\RangeNode $node): string {
        echo "DEBUG: In visitRange!\n";
        $result = parent::visitRange($node);
        echo "DEBUG: visitRange result: $result\n";
        return $result;
    }
}

$debugVisitor = new DebugVisitor();
$explain = $ast->accept($debugVisitor);
echo "Final explanation:\n$explain\n";