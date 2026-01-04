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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\Regex;

final class LinterPortedRulesTest extends TestCase
{
    #[DataProvider('provideUselessRangePatterns')]
    public function test_useless_range_rule(string $pattern, bool $shouldWarn): void
    {
        $issues = $this->lint($pattern);

        if ($shouldWarn) {
            $this->assertContains('regex.lint.range.useless', $issues);
        } else {
            $this->assertNotContains('regex.lint.range.useless', $issues);
        }
    }

    #[DataProvider('provideDuplicateCharClassPatterns')]
    public function test_duplicate_char_class_rule(string $pattern, bool $shouldWarn): void
    {
        $issues = $this->lint($pattern);

        if ($shouldWarn) {
            $this->assertContains('regex.lint.charclass.duplicate_chars', $issues);
        } else {
            $this->assertNotContains('regex.lint.charclass.duplicate_chars', $issues);
        }
    }

    #[DataProvider('provideZeroQuantifierPatterns')]
    public function test_zero_quantifier_rule(string $pattern, bool $shouldWarn): void
    {
        $issues = $this->lint($pattern);

        if ($shouldWarn) {
            $this->assertContains('regex.lint.quantifier.zero', $issues);
        } else {
            $this->assertNotContains('regex.lint.quantifier.zero', $issues);
        }
    }

    #[DataProvider('provideUselessQuantifierPatterns')]
    public function test_useless_quantifier_rule(string $pattern, bool $shouldWarn): void
    {
        $issues = $this->lint($pattern);

        if ($shouldWarn) {
            $this->assertContains('regex.lint.quantifier.useless', $issues);
        } else {
            $this->assertNotContains('regex.lint.quantifier.useless', $issues);
        }
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideUselessRangePatterns(): array
    {
        return [
            'single char range' => ['/[\x61-\x61]/', true],
            'adjacent range' => ['/[\x61-\x62]/', true],
            'three chars range' => ['/[\x61-\x63]/', false],
            'digit range' => ['/[\x30-\x39]/', false],
        ];
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideDuplicateCharClassPatterns(): array
    {
        return [
            'digit class with range' => ['/[\d0-9]/', true],
            'range with digit class' => ['/[0-9\d]/', true],
            'word class with range' => ['/[\wA-Z]/', true],
            'escaped literal duplicate' => ['/[\x41A]/', true],
            'basic duplicate is handled elsewhere' => ['/[aa]/', false],
            'no duplicates' => ['/[A-Z0-9_]/', false],
            'unicode digit class is skipped' => ['/[\d0-9]/u', false],
        ];
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideZeroQuantifierPatterns(): array
    {
        return [
            'zero quantifier' => ['/a{0}/', true],
            'zero range quantifier' => ['/(?:a){0,0}/', true],
            'zero quantifier in class' => ['/\\d{0}/', true],
            'optional quantifier' => ['/a{0,1}/', false],
            'unbounded quantifier' => ['/a{0,}/', false],
        ];
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideUselessQuantifierPatterns(): array
    {
        return [
            'constant one quantifier' => ['/a{1}/', true],
            'explicit one range quantifier' => ['/(?:a){1,1}/', true],
            'literal quantifier' => ['/a{2}/', false],
            'plus quantifier' => ['/a+/', false],
        ];
    }

    /**
     * @return array<string>
     */
    private function lint(string $pattern): array
    {
        $regex = Regex::create()->parse($pattern);
        $linter = new LinterNodeVisitor();
        $regex->accept($linter);

        return array_map(static fn ($issue): string => $issue->id, $linter->getIssues());
    }
}
