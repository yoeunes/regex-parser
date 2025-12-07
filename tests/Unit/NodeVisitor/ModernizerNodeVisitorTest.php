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

namespace RegexParser\Tests\Unit\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RangeNode;
use RegexParser\NodeVisitor\ModernizerNodeVisitor;

final class ModernizerNodeVisitorTest extends TestCase
{
    private ModernizerNodeVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new ModernizerNodeVisitor();
    }

    public function test_modernizes_digit_range_to_char_type(): void
    {
        $range = new RangeNode(new LiteralNode('0', 0, 1), new LiteralNode('9', 2, 3), 0, 3);
        $charClass = new CharClassNode($range, false, 0, 4);

        $result = $charClass->accept($this->visitor);

        $this->assertInstanceOf(CharTypeNode::class, $result);
        $this->assertSame('d', $result->value);
    }

    public function test_removes_unnecessary_escaping(): void
    {
        $literal = new LiteralNode('\\@', 0, 2);

        $result = $literal->accept($this->visitor);

        $this->assertInstanceOf(LiteralNode::class, $result);
        /** @var LiteralNode $result */
        $this->assertSame('@', $result->value);
    }

    public function test_modernizes_numeric_backref(): void
    {
        $backref = new BackrefNode('1', 0, 2);

        $result = $backref->accept($this->visitor);

        $this->assertInstanceOf(BackrefNode::class, $result);
        /** @var BackrefNode $result */
        $this->assertSame('\g{1}', $result->ref);
    }

    public function test_preserves_named_backref(): void
    {
        $backref = new BackrefNode('name', 0, 6);

        $result = $backref->accept($this->visitor);

        $this->assertSame($backref, $result);
    }
}