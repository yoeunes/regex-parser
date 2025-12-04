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
use RegexParser\NodeVisitor\MetricsNodeVisitor;
use RegexParser\Regex;

final class MetricsNodeVisitorTest extends TestCase
{
    public function test_it_collects_counts_and_depth(): void
    {
        $ast = Regex::create()->parse('/(a|b)c/');
        $metrics = $ast->accept(new MetricsNodeVisitor());

        $this->assertSame(7, $metrics['total']);
        $this->assertSame(3, $metrics['counts']['LiteralNode'] ?? null);
        $this->assertSame(1, $metrics['counts']['AlternationNode'] ?? null);
        $this->assertGreaterThanOrEqual(4, $metrics['maxDepth']);
    }
}
