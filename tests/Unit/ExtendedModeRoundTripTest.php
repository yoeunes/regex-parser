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

namespace RegexParser\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\Regex;

/**
 * Whitespace that /x makes ignorable carries no meaning but it carries the
 * layout of the pattern, so recompiling an AST must give the text back
 * unchanged instead of collapsing a documented pattern onto one line.
 */
final class ExtendedModeRoundTripTest extends TestCase
{
    #[Test]
    #[DataProvider('provideExtendedPatterns')]
    public function test_extended_patterns_recompile_unchanged(string $pattern): void
    {
        $recompiled = Regex::create()->parse($pattern)->accept(new CompilerNodeVisitor());

        $this->assertSame($pattern, $recompiled);
    }

    #[Test]
    public function test_pretty_printing_still_reflows_the_pattern(): void
    {
        $ast = Regex::create()->parse('/a  b/x');

        $this->assertNotSame('/a  b/x', $ast->accept(new CompilerNodeVisitor(pretty: true)));
    }

    #[Test]
    public function test_an_ast_built_without_a_source_still_compiles(): void
    {
        $ast = Regex::create()->parse('/a b/x');
        $rebuilt = new \RegexParser\Node\RegexNode($ast->pattern, $ast->flags, $ast->delimiter, 0, 3);

        $this->assertSame('/ab/x', $rebuilt->accept(new CompilerNodeVisitor()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideExtendedPatterns(): iterable
    {
        yield 'single space' => ['/a b/x'];
        yield 'leading and trailing spaces' => ['/  a  b  /x'];
        yield 'inline x' => ['/(?x)  a b  /'];
        yield 'scoped x' => ['/(?x:  a b  )c d/'];
        yield 'around alternation' => ['/a  |  b/x'];
        yield 'inside a group' => ['/(  a b  )c/x'];
        yield 'inside a non capturing group' => ['/(?: a | b )/x'];
        yield 'inside a branch reset' => ['/(?| a | b )/x'];
        yield 'named group' => ['/(?<n> a )/x'];
        yield 'lookahead' => ['/(?= a )/x'];
        yield 'lookbehind' => ['/(?<= a )/x'];
        yield 'atomic group' => ['/(?> a )/x'];
        yield 'comment line' => ["/a # comment\nb/x"];
        yield 'comment after inline x' => ["/(?x)a # comment\nb/"];
        yield 'documented multi line pattern' => ["/\n  (\\w+)   # word\n  \\s*      # spacing\n  (\\d+)    # number\n/x"];
        yield 'space inside a character class' => ['/[ a b ]/x'];
        yield 'escaped space' => ['/a\\ b/x'];
        yield 'no extended mode' => ['/a b/'];
        yield 'empty pattern' => ['//x'];
        yield 'only whitespace' => ['/ /x'];
    }
}
