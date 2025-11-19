<?php

declare(strict_types=1);

namespace RegexParser\Tests\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\LiteralSet;
use RegexParser\NodeVisitor\LiteralExtractorVisitor;
use RegexParser\Parser;

class LiteralExtractorVisitorTest extends TestCase
{
    private Parser $parser;
    private LiteralExtractorVisitor $visitor;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->visitor = new LiteralExtractorVisitor();
    }

    private function extract(string $regex): LiteralSet
    {
        $ast = $this->parser->parse($regex);
        return $ast->accept($this->visitor);
    }

    public function testSimpleLiteral(): void
    {
        $set = $this->extract('/hello/');

        $this->assertTrue($set->complete);
        $this->assertSame(['hello'], $set->prefixes);
        $this->assertSame(['hello'], $set->suffixes);
    }

    public function testSequenceConcat(): void
    {
        $set = $this->extract('/abc/'); // Sequence(Literal(a), Literal(b), Literal(c))

        $this->assertTrue($set->complete);
        $this->assertSame(['abc'], $set->prefixes);
    }

    public function testAlternation(): void
    {
        $set = $this->extract('/(foo|bar)/');

        $this->assertTrue($set->complete);
        $this->assertSame(['foo', 'bar'], $set->prefixes);
        $this->assertSame(['foo', 'bar'], $set->suffixes);
    }

    public function testPrefixSuffixLogic(): void
    {
        $set = $this->extract('/root(a|b)tail/');

        $this->assertTrue($set->complete);
        $this->assertSame(['roota', 'rootb'], $set->prefixes); // Longest known prefixes
        $this->assertSame(['tail'], $set->suffixes); // Suffix is common
    }

    public function testQuantifierFixed(): void
    {
        $set = $this->extract('/a{3}/');
        $this->assertSame(['aaa'], $set->prefixes);
        $this->assertTrue($set->complete);
    }

    public function testQuantifierPlus(): void
    {
        $set = $this->extract('/a+/');
        // Should contain 'a' as prefix, but incomplete
        $this->assertSame(['a'], $set->prefixes);
        $this->assertFalse($set->complete);
        $this->assertEmpty($set->suffixes);
    }

    public function testQuantifierStar(): void
    {
        $set = $this->extract('/a*/');
        $this->assertEmpty($set->prefixes);
        $this->assertFalse($set->complete);
    }

    public function testAnchorDoesNotBreakPrefix(): void
    {
        $set = $this->extract('/^root/');
        $this->assertSame(['root'], $set->prefixes);
    }

    public function testDotBreaksChain(): void
    {
        $set = $this->extract('/pre.fix/');
        // Should have prefix "pre" and suffix "fix" but incomplete
        $this->assertSame(['pre'], $set->prefixes);
        $this->assertSame(['fix'], $set->suffixes);
        $this->assertFalse($set->complete);
    }

    public function testCaseInsensitive(): void
    {
        $set = $this->extract('/ab/i');
        // Expect: ab, aB, Ab, AB
        $this->assertCount(4, $set->prefixes);
        $this->assertContains('ab', $set->prefixes);
        $this->assertContains('AB', $set->prefixes);
    }

    public function testOptimizationUsage(): void
    {
        $pattern = '/user_(\d+)/';
        $set = $this->extract($pattern);

        $this->assertSame(['user_'], $set->prefixes);
        $this->assertFalse($set->complete);

        $prefix = $set->getLongestPrefix();
        $this->assertSame('user_', $prefix);
    }
}
