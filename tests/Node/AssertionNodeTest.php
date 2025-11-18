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
use RegexParser\Node\AssertionNode;
use RegexParser\NodeVisitor\NodeVisitorInterface;

class AssertionNodeTest extends TestCase
{
    public static function data_provider_assertions(): array
    {
        return [
            // Assertions de position
            'start_of_subject' => ['A', 0, 2],
            'end_of_subject' => ['z', 5, 7],
            'end_of_subject_or_newline' => ['Z', 5, 7],
            'last_match_end' => ['G', 5, 7],
            // Assertions de mot
            'word_boundary' => ['b', 1, 3],
            'non_word_boundary' => ['B', 1, 3],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('data_provider_assertions')]
    public function test_constructor_and_getters(string $value, int $start, int $end): void
    {
        $node = new AssertionNode($value, $start, $end);

        $this->assertSame($value, $node->value);
        $this->assertSame($start, $node->getStartPosition());
        $this->assertSame($end, $node->getEndPosition());
    }

    public function test_accept_visitor_calls_visit_assertion(): void
    {
        $node = new AssertionNode('b', 0, 2);
        $visitor = $this->createMock(NodeVisitorInterface::class);

        $visitor->expects($this->once())
            ->method('visitAssertion')
            ->with($this->identicalTo($node))
            ->willReturn('visited');

        $this->assertSame('visited', $node->accept($visitor));
    }
}
