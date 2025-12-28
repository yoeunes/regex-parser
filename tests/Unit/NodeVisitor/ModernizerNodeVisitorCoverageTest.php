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
use RegexParser\Node\AlternationNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RegexNode;
use RegexParser\NodeVisitor\ModernizerNodeVisitor;

final class ModernizerNodeVisitorCoverageTest extends TestCase
{
    public function test_char_class_multiple_literals_builds_alternation(): void
    {
        $visitor = new ModernizerNodeVisitor();
        $expression = new AlternationNode([
            new LiteralNode('a', 0, 0),
            new LiteralNode('b', 0, 0),
        ], 0, 0);
        $charClass = new CharClassNode($expression, false, 0, 0);

        $modernized = $charClass->accept($visitor);

        $this->assertInstanceOf(CharClassNode::class, $modernized);
        $this->assertInstanceOf(AlternationNode::class, $modernized->expression);
    }

    public function test_literal_unescape_respects_custom_delimiter(): void
    {
        $visitor = new ModernizerNodeVisitor();
        $regex = new RegexNode(new LiteralNode('\\a', 0, 0), '', '#', 0, 0);

        $modernized = $regex->accept($visitor);

        $this->assertInstanceOf(RegexNode::class, $modernized);
        $this->assertInstanceOf(LiteralNode::class, $modernized->pattern);
        $this->assertSame('a', $modernized->pattern->value);
    }
}
