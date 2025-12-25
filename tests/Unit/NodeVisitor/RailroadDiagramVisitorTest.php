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
use RegexParser\NodeVisitor\RailroadDiagramVisitor;
use RegexParser\Regex;

final class RailroadDiagramVisitorTest extends TestCase
{
    public function test_diagram_renders_basic_tree(): void
    {
        $ast = Regex::create()->parse('/^a+$/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $expected = <<<'TEXT'
            Regex
            \-- Sequence
                |-- Anchor (^)
                |-- Quantifier (+, greedy)
                |   \-- Literal ('a')
                \-- Anchor ($)
            TEXT;

        $this->assertSame($expected, $diagram);
    }
}
