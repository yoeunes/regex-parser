<?php

declare(strict_types=1);

namespace RegexParser\Tests\Node;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\CommentNode;
use RegexParser\NodeVisitor\NodeVisitorInterface;

class CommentNodeTest extends TestCase
{
    public function test_constructor_and_getters(): void
    {
        $comment_text = 'This is a test comment';
        $node = new CommentNode($comment_text, 10, 30);

        $this->assertSame($comment_text, $node->comment);
        $this->assertSame(10, $node->getStartPosition());
        $this->assertSame(30, $node->getEndPosition());
    }

    public function test_accept_visitor_calls_visitComment(): void
    {
        $node = new CommentNode('test', 0, 10);
        $visitor = $this->createMock(NodeVisitorInterface::class);

        $visitor->expects($this->once())
            ->method('visitComment')
            ->with($this->identicalTo($node))
            ->willReturn('visited');

        $this->assertSame('visited', $node->accept($visitor));
    }
}
