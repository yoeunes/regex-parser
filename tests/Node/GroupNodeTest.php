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
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\NodeInterface;
use RegexParser\NodeVisitor\NodeVisitorInterface;

class GroupNodeTest extends TestCase
{
    /**
     * @return \Iterator<string, array{GroupType, ?string, ?string}>
     */
    public static function data_provider_group_types(): \Iterator
    {
        yield 'capturing' => [GroupType::T_GROUP_CAPTURING, null, null];
        yield 'non_capturing' => [GroupType::T_GROUP_NON_CAPTURING, null, null];
        yield 'named' => [GroupType::T_GROUP_NAMED, 'id', null];
        yield 'lookahead_positive' => [GroupType::T_GROUP_LOOKAHEAD_POSITIVE, null, null];
        yield 'lookbehind_negative' => [GroupType::T_GROUP_LOOKBEHIND_NEGATIVE, null, null];
        yield 'atomic' => [GroupType::T_GROUP_ATOMIC, null, null];
        yield 'inline_flags' => [GroupType::T_GROUP_INLINE_FLAGS, null, 'ims'];
    }

    #[DataProvider('data_provider_group_types')]
    public function test_constructor_and_getters(GroupType $type, ?string $name, ?string $flags): void
    {
        $child = new LiteralNode('a', 1, 2);
        $node = new GroupNode($child, $type, $name, $flags, 0, 5);

        $this->assertSame($child, $node->child);
        $this->assertSame($type, $node->type);
        $this->assertSame($name, $node->name);
        $this->assertSame($flags, $node->flags);
        $this->assertSame(0, $node->getStartPosition());
        $this->assertSame(5, $node->getEndPosition());
    }

    public function test_accept_visitor_calls_visit_group(): void
    {
        $child = $this->createMock(NodeInterface::class);
        $node = new GroupNode($child, GroupType::T_GROUP_CAPTURING, null, null, 0, 5);
        $visitor = $this->createMock(NodeVisitorInterface::class);

        $visitor->expects($this->once())
            ->method('visitGroup')
            ->with($this->identicalTo($node))
            ->willReturn('visited');

        $this->assertSame('visited', $node->accept($visitor));
    }
}
