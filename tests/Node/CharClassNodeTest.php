<?php

declare(strict_types=1);

namespace RegexParser\Tests\Node;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\NodeVisitor\NodeVisitorInterface;

class CharClassNodeTest extends TestCase
{
    public function test_constructor_and_getters_positive(): void
    {
        $part1 = new LiteralNode('a', 2, 3);
        $part2 = new CharTypeNode('d', 4, 6);

        $node = new CharClassNode([$part1, $part2], false, 1, 7);

        $this->assertFalse($node->isNegated);
        $this->assertCount(2, $node->parts);
        $this->assertSame($part1, $node->parts[0]);
        $this->assertSame(1, $node->getStartPosition());
        $this->assertSame(7, $node->getEndPosition());
    }

    public function test_constructor_and_getters_negated(): void
    {
        $part1 = new LiteralNode('a', 3, 4);
        $node = new CharClassNode([$part1], true, 1, 5);

        $this->assertTrue($node->isNegated);
        $this->assertCount(1, $node->parts);
    }

    public function test_accept_visitor_calls_visitCharClass(): void
    {
        $part1 = $this->createMock(NodeInterface::class);
        $node = new CharClassNode([$part1], false, 0, 5);
        $visitor = $this->createMock(NodeVisitorInterface::class);

        $visitor->expects($this->once())
            ->method('visitCharClass')
            ->with($this->identicalTo($node))
            ->willReturn('visited');

        $this->assertSame('visited', $node->accept($visitor));
    }
}
