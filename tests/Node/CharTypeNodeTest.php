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

use PHPUnit\Framework\TestCase;
use RegexParser\Node\CharTypeNode;
use RegexParser\NodeVisitor\NodeVisitorInterface;

class CharTypeNodeTest extends TestCase
{
    public static function data_provider_char_types(): array
    {
        return [
            'digit' => ['d', 0, 2],
            'not_digit' => ['D', 3, 5],
            'whitespace' => ['s', 6, 8],
            'not_word' => ['W', 9, 11],
            'horizontal_whitespace' => ['h', 12, 14],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('data_provider_char_types')]
    public function test_constructor_and_getters(string $value, int $start, int $end): void
    {
        $node = new CharTypeNode($value, $start, $end);

        $this->assertSame($value, $node->value);
        $this->assertSame($start, $node->getStartPosition());
        // L'end position inclut le backslash (\d -> longueur 2)
        $this->assertSame($end, $node->getEndPosition());
    }

    public function test_accept_visitor_calls_visit_char_type(): void
    {
        $node = new CharTypeNode('d', 0, 2);
        $visitor = $this->createMock(NodeVisitorInterface::class);

        $visitor->expects($this->once())
            ->method('visitCharType')
            ->with($this->identicalTo($node))
            ->willReturn('visited');

        $this->assertSame('visited', $node->accept($visitor));
    }
}
