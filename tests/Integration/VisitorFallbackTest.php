<?php

declare(strict_types=1);

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\Node\OctalNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\NodeVisitor\SampleGeneratorNodeVisitor;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Regex;

class VisitorFallbackTest extends TestCase
{
    public function test_sample_generator_backref_not_found(): void
    {
        // Access a backreference that hasn't been captured yet
        // e.g. \1 when group 1 hasn't matched
        $regex = Regex::create();
        $ast = $regex->parse('/\1/');

        $generator = new SampleGeneratorNodeVisitor();
        // Should return empty string (fallback)
        $this->assertSame('', $ast->accept($generator));
    }

    public function test_sample_generator_bad_unicode_node(): void
    {
        // Inject a UnicodeNode with a value that doesn't match the regex pattern expected
        $node = new UnicodeNode('BAD', 0, 0);
        $generator = new SampleGeneratorNodeVisitor();

        // Should hit the '?' fallback
        $this->assertSame('?', $node->accept($generator));
    }

    public function test_sample_generator_bad_octal_node(): void
    {
        // Inject OctalNode with bad value
        $node = new OctalNode('BAD', 0, 0);
        $generator = new SampleGeneratorNodeVisitor();

        // Should hit the '?' fallback
        $this->assertSame('?', $node->accept($generator));
    }

    public function test_validator_bad_backref_syntax(): void
    {
        // Inject BackrefNode with value that fails internal validator regex
        // Use a value with invalid characters that won't match any valid pattern
        $node = new BackrefNode('BAD-REF', 0, 0);
        $validator = new ValidatorNodeVisitor();

        $this->expectException(\RegexParser\Exception\ParserException::class);
        $this->expectExceptionMessage('Invalid backreference syntax');

        $node->accept($validator);
    }

    public function test_optimizer_char_class_parts_change(): void
    {
        // The OptimizerNodeVisitor::visitCharClass has logic: if ($optimizedPart !== $part) { $hasChanged = true; }
        // But currently, parts (Literals/Ranges) are never optimized, so this block is dead code.
        // We force it by mocking a NodeInterface that returns a DIFFERENT instance when visited.

        $mockPart = $this->createMock(NodeInterface::class);
        $mockPart
            ->method('accept')
            ->willReturn(new LiteralNode('changed', 0, 0)); // Return different instance

        $node = new CharClassNode([$mockPart], false, 0, 0);
        $optimizer = new OptimizerNodeVisitor();

        $result = $node->accept($optimizer);

        // Assert that we got a NEW CharClassNode (meaning $hasChanged was true)
        $this->assertNotSame($node, $result);
        $this->assertInstanceOf(CharClassNode::class, $result);
        $this->assertInstanceOf(LiteralNode::class, $result->parts[0]);
        $this->assertSame('changed', $result->parts[0]->value);
    }
}
