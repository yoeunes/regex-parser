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

namespace RegexParser\Tests\Builder;

use PHPUnit\Framework\TestCase;
use RegexParser\Builder\CharClass;
use RegexParser\Builder\RegexBuilder;

class FluentBuilderTest extends TestCase
{
    public function test_simple_literal(): void
    {
        $regex = RegexBuilder::create()
            ->literal('hello')
            ->build();

        $this->assertSame('/hello/', $regex);
    }

    public function test_url_pattern(): void
    {
        $regex = RegexBuilder::create()
            ->startOfLine()
            ->literal('http')
            ->literal('s')->optional()
            ->literal('://')
            ->capture(function (RegexBuilder $b): void {
                // Utilisation correcte de union() au lieu de add() pour combiner des CharClass
                $b->charClass(CharClass::word()->union(CharClass::literal('.')))
                  ->oneOrMore();
            }, 'domain')
            ->group(function (RegexBuilder $b): void {
                $b->literal(':')
                  ->digit()->between(1, 5);
            })->optional()
            ->literal('/')
            ->anyChar()->zeroOrMore()
            ->endOfLine()
            ->caseInsensitive()
            ->build();

        $this->assertStringStartsWith('/', $regex);
        $this->assertStringEndsWith('/i', $regex);
        $this->assertStringContainsString('(?<domain>', $regex);
        $this->assertStringContainsString('https?', $regex);
    }

    public function test_alternation_fluent(): void
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

    public function test_lookarounds(): void
    {
        $regex = RegexBuilder::create()
            ->lookbehind(fn (RegexBuilder $b) => $b->literal('$'))
            ->digit()->oneOrMore()
            ->lookahead(fn (RegexBuilder $b) => $b->literal('.'))
            ->build();

        // Note: le compilateur échappe le $ dans un littéral
        $this->assertStringContainsString('(?<=\$)', $regex);
        $this->assertStringContainsString('(?=\.)', $regex);
    }

    public function test_char_class_combination(): void
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

    public function test_flags(): void
    {
        $regex = RegexBuilder::create()
            ->literal('test')
            ->multiline()
            ->dotAll()
            ->unicode()
            ->build();

        // L'ordre des flags peut varier, on vérifie leur présence
        $this->assertStringMatchesFormat('/%s/msu', $regex);
    }

    public function test_quantifier_exception(): void
    {
        $this->expectException(\LogicException::class);
        RegexBuilder::create()->zeroOrMore();
    }
}
