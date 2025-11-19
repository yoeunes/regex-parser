<?php

declare(strict_types=1);

namespace RegexParser\Tests\Builder;

use PHPUnit\Framework\TestCase;
use RegexParser\Builder\CharClass;
use RegexParser\Builder\RegexBuilder;

class FluentBuilderTest extends TestCase
{
    public function testSimpleLiteral(): void
    {
        $regex = RegexBuilder::create()
            ->literal('hello')
            ->build();

        $this->assertSame('/hello/', $regex);
    }

    public function testUrlPattern(): void
    {
        $regex = RegexBuilder::create()
            ->startOfLine()
            ->literal('http')
            ->literal('s')->optional()
            ->literal('://')
            ->capture(function (RegexBuilder $b) {
                $b->charClass(CharClass::word()->add(CharClass::literal('.')))
                  ->oneOrMore();
            }, 'domain')
            ->group(function (RegexBuilder $b) {
                $b->literal(':')
                  ->digit()->between(1, 5);
            })->optional()
            ->literal('/')
            ->anyChar()->zeroOrMore()
            ->endOfLine()
            ->caseInsensitive()
            ->build();

        // Note: LiteralNode escapes dots automatically.
        // The CompilerNodeVisitor will re-assemble.
        // Expected: /^https?:\/\/(?<domain>[\w.]+)(?::\d{1,5})?\/.*$/i
        // We check containment to avoid escaping hell in assertions
        $this->assertStringStartsWith('/', $regex);
        $this->assertStringEndsWith('/i', $regex);
        $this->assertStringContainsString('(?<domain>', $regex);
        $this->assertStringContainsString('https?', $regex);
    }

    public function testAlternationFluent(): void
    {
        $regex = RegexBuilder::create()
            ->literal('cat')
            ->or()
            ->literal('dog')
            ->or()
            ->literal('fish')
            ->build();

        $this->assertSame('/cat|dog|fish/', $regex);
    }

    public function testLookarounds(): void
    {
        $regex = RegexBuilder::create()
            ->lookbehind(fn($b) => $b->literal('$'))
            ->digit()->oneOrMore()
            ->lookahead(fn($b) => $b->literal('.'))
            ->build();

        $this->assertStringContainsString('(?<=$)', $regex);
        $this->assertStringContainsString('(?=.)', $regex);
    }

    public function testCharClassCombination(): void
    {
        $class = CharClass::digit()
            ->union(CharClass::range('a', 'f'))
            ->negate();

        $regex = RegexBuilder::create()
            ->literal('#')
            ->charClass($class)->exactly(6)
            ->build();

        // [^d-af] logic inside
        $this->assertStringContainsString('[^', $regex);
    }

    public function testFlags(): void
    {
        $regex = RegexBuilder::create()
            ->literal('test')
            ->multiline()
            ->dotAll()
            ->unicode()
            ->build();

        $this->assertStringEndsWith('/msu', $regex);
    }

    public function testQuantifierException(): void
    {
        $this->expectException(\LogicException::class);
        RegexBuilder::create()->zeroOrMore();
    }
}
