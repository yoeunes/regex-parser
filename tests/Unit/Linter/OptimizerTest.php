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

namespace RegexParser\Tests\Unit\Linter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\OptimizerNodeVisitor;
use RegexParser\Regex;

/**
 * Tests for the regex linter/optimizer to verify correct optimization suggestions.
 *
 * These tests specifically verify that the optimizer does not double-escape backslashes
 * and produces correctly formatted optimization suggestions.
 */
final class OptimizerTest extends TestCase
{
    private Regex $regex;

    private OptimizerNodeVisitor $optimizer;

    private CompilerNodeVisitor $compiler;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
        $this->optimizer = new OptimizerNodeVisitor();
        $this->compiler = new CompilerNodeVisitor();
    }

    #[DataProvider('optimizationProvider')]
    public function test_optimizations(string $pattern, string $expected): void
    {
        $result = $this->lint($pattern);

        $this->assertSame($expected, $result);
    }

    /**
     * Data provider for optimization test cases.
     *
     * These test cases are derived from Symfony logs and specifically test
     * for the double-escaping bug where backslashes were incorrectly escaped.
     *
     * Bad Output:  /^(?<version>\\d\\.\\d|\\d\{2,\})/
     * Expected:    /^(?<version>\d\.\d|\d{2,})/
     */
    public static function optimizationProvider(): \Iterator
    {
        // Case 1: The escaping bug - tests that backslashes are not double-escaped
        // Input uses (?P<name>) syntax which should be converted to (?<name>)
        // Also tests digit class optimization [0-9] -> \d
        // Note: Factorization is not applied here because it would produce invalid regex
        // (e.g., {2,} without a preceding element)
        yield 'escaping bug - version pattern' => [
            '/^(?P<version>[0-9]\.[0-9]|[0-9]{2,})/',
            '/^(?<version>\d\.\d|\d{2,})/',
        ];

        // Case 2: Character class simplification [0-9] -> \d
        yield 'character class simplification' => [
            '/^[1-9][0-9]*$/',
            '/^[1-9]\d*$/',
        ];

        // Case 3: Useless flag removal - 's' flag is useless when no dots present
        yield 'useless flag removal' => [
            '#<esi:comment[^>]+>#s',
            '#<esi:com{2}ent[^>]+>#',
        ];

        // Case 4: Structure optimization - (?P<name>) -> (?<name>)
        yield 'structure optimization - named groups' => [
            '/^(?P<year>\d{4})-W(?P<week>\d{2})$/',
            '/^(?<year>\d{4})-W(?<week>\d{2})$/',
        ];
    }

    /**
     * Simulates the lint method that returns the optimized pattern suggestion.
     */
    private function lint(string $pattern): string
    {
        $ast = $this->regex->parse($pattern);
        $optimized = $ast->accept($this->optimizer);

        return $optimized->accept($this->compiler);
    }
}
