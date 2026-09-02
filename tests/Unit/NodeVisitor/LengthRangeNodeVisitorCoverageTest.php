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
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
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
}
