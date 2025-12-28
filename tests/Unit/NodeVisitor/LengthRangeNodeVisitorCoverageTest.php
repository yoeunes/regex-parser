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
use RegexParser\Node\LiteralNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\NodeVisitor\LengthRangeNodeVisitor;

final class LengthRangeNodeVisitorCoverageTest extends TestCase
{
    public function test_alternation_with_infinite_branch_returns_null_max(): void
    {
        $visitor = new LengthRangeNodeVisitor();
        $literal = new LiteralNode('a', 0, 0);
        $infinite = new QuantifierNode(new LiteralNode('b', 0, 0), '*', QuantifierType::T_GREEDY, 0, 0);
        $alternation = new AlternationNode([$literal, $infinite], 0, 0);

        $range = $alternation->accept($visitor);

        $this->assertSame([0, null], $range);
    }

    public function test_visit_range_unicode_and_posix_class_are_fixed_length(): void
    {
        $visitor = new LengthRangeNodeVisitor();

        $rangeNode = new RangeNode(new LiteralNode('a', 0, 0), new LiteralNode('z', 0, 0), 0, 0);
        $this->assertSame([1, 1], $rangeNode->accept($visitor));

        $unicode = new UnicodeNode('\\x{41}', 0, 0);
        $this->assertSame([1, 1], $unicode->accept($visitor));

        $posix = new PosixClassNode('alpha', 0, 0);
        $this->assertSame([1, 1], $posix->accept($visitor));
    }
}
