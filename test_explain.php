<?php

require_once 'vendor/autoload.php';

use RegexParser\NodeVisitor\ExplainNodeVisitor;
use RegexParser\Node\LiteralNode;

$visitor = new ExplainNodeVisitor();

// Test the explainLiteral method via reflection
$reflection = new ReflectionClass($visitor);
$method = $reflection->getMethod('explainLiteral');
$method->setAccessible(true);

echo "Testing explainLiteral with extended ASCII:\n";

$result7f = $method->invoke($visitor, "\x7f");
echo "0x7f: $result7f\n";

$character = "\xff";
$ord = ord($character);
echo "Character: [" . $character . "], ord: $ord\n";

$method->setAccessible(true);
$resultff = $method->invoke($visitor, $character);
echo "0xff: $resultff\n";

// Test via visitLiteral
$node7f = new LiteralNode("\x7f", 0, 1);
$resultNode7f = $visitor->visitLiteral($node7f);
echo "Node 0x7f: $resultNode7f\n";

$nodeff = new LiteralNode("\xff", 0, 1);
$resultNodeff = $visitor->visitLiteral($nodeff);
echo "Node 0xff: $resultNodeff\n";