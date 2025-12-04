<?php

declare(strict_types=1);

namespace RegexParser\Tests\Unit\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\MetricsNodeVisitor;
use RegexParser\Regex;

final class MetricsNodeVisitorTest extends TestCase
{
    public function testItCollectsCountsAndDepth(): void
    {
        $ast = Regex::create()->parse('/(a|b)c/');
        $metrics = $ast->accept(new MetricsNodeVisitor());

        self::assertSame(7, $metrics['total']);
        self::assertSame(3, $metrics['counts']['LiteralNode'] ?? null);
        self::assertSame(1, $metrics['counts']['AlternationNode'] ?? null);
        self::assertGreaterThanOrEqual(4, $metrics['maxDepth']);
    }
}
