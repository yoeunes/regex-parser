<?php

require_once 'vendor/autoload.php';

use RegexParser\Node\RegexNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\SequenceNode;

// Test the pattern compilation
$patternNode = new LiteralNode("QUICK_CHECK = .*;", 0, 20);
$regexNode = new RegexNode($patternNode, '/m', '/', 1, 21);

echo "Original pattern: " . $patternNode->value . "\n";

// Try to compile with our visitor method
class TestVisitor implements RegexParser\NodeVisitor\NodeVisitorInterface {
    public function visitLiteral(\RegexParser\Node\LiteralNode $node): string {
        return $node->value;
    }
    
    public function visitSequence(\RegexParser\Node\SequenceNode $node): string {
        return implode('', array_map(fn($child) => $child->accept($this), $node->children));
    }
    
    public function visitAlternation(\RegexParser\Node\AlternationNode $node): string {
        return implode('|', array_map(fn($alt) => $alt->accept($this), $node->alternatives));
    }
    
    public function visitCharClass(\RegexParser\Node\CharClassNode $node): string {
        return '[' . $node->expression->accept($this) . ']';
    }
    
    public function visitGroup(\RegexParser\Node\GroupNode $node): string {
        return '(' . $node->type->value . $node->child->accept($this) . ')';
    }
}

$testVisitor = new TestVisitor();
$result = $patternNode->accept($testVisitor);

echo "Compiled pattern: [" . $result . "]\n";