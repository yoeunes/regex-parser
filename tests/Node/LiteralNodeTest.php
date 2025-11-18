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

namespace RegexParser\Tests\Node;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\Node\LiteralNode;
use RegexParser\NodeVisitor\NodeVisitorInterface;

class LiteralNodeTest extends TestCase
{
    public static function data_provider_literals(): \Iterator
    {
        // Littéraux simples
        yield 'simple_char' => ['a', 0, 1];
        yield 'number' => ['1', 5, 6];
        // Métacaractères échappés (la valeur stockée est le caractère lui-même)
        yield 'escaped_star' => ['*', 2, 4];
        // stocké comme '*', la position couvre '\*'
        yield 'escaped_backslash' => ['\\', 10, 12];
        // stocké comme '\', la position couvre '\\\\' dans la regex source
        yield 'long_string' => ['http', 0, 4];
    }

    #[DataProvider('data_provider_literals')]
    public function test_constructor_and_getters(string $value, int $start, int $end): void
    {
        $node = new LiteralNode($value, $start, $end);

        $this->assertSame($value, $node->value);
        $this->assertSame($start, $node->getStartPosition());
        $this->assertSame($end, $node->getEndPosition());
    }

    public function test_accept_visitor_calls_visit_literal(): void
    {
        $node = new LiteralNode('x', 0, 1);
        $visitor = $this->createMock(NodeVisitorInterface::class);

        $visitor->expects($this->once())
            ->method('visitLiteral')
            ->with($this->identicalTo($node))
            ->willReturn('visited');

        $this->assertSame('visited', $node->accept($visitor));
    }
}
