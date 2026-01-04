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

final class LinterLogicRulesTest extends TestCase
{
    #[DataProvider('provideEmptyAlternativePatterns')]
    public function test_empty_alternative_rule(string $pattern, bool $shouldWarn): void
    {
        $issues = $this->lint($pattern);

        if ($shouldWarn) {
            $this->assertContains('regex.lint.alternation.empty', $issues);
        } else {
            $this->assertNotContains('regex.lint.alternation.empty', $issues);
        }
    }

    #[DataProvider('provideDuplicateDisjunctionPatterns')]
    public function test_duplicate_disjunction_rule(string $pattern, bool $shouldWarn): void
    {
        $issues = $this->lint($pattern);

        if ($shouldWarn) {
            $this->assertContains('regex.lint.alternation.duplicate_disjunction', $issues);
        } else {
            $this->assertNotContains('regex.lint.alternation.duplicate_disjunction', $issues);
        }
    }

    #[DataProvider('provideOptimalQuantifierConcatenationPatterns')]
    public function test_optimal_quantifier_concatenation_rule(string $pattern, bool $shouldWarn): void
    {
        $issues = $this->lint($pattern);

        if ($shouldWarn) {
            $this->assertContains('regex.lint.quantifier.concatenation', $issues);
        } else {
            $this->assertNotContains('regex.lint.quantifier.concatenation', $issues);
        }
    }

    #[DataProvider('provideUselessBackreferencePatterns')]
    public function test_useless_backreference_rule(string $pattern, bool $shouldWarn): void
    {
        $issues = $this->lint($pattern);

        if ($shouldWarn) {
            $this->assertContains('regex.lint.backref.useless', $issues);
        } else {
            $this->assertNotContains('regex.lint.backref.useless', $issues);
        }
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideEmptyAlternativePatterns(): array
    {
        return [
            'trailing empty' => ['/a|/', true],
            'leading empty' => ['/|a/', true],
            'double pipe' => ['/a||b/', true],
            'explicit empty group' => ['/(?:)|a/', false],
            'no alternation' => ['/a/', false],
        ];
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideDuplicateDisjunctionPatterns(): array
    {
        return [
            'duplicate literal' => ['/a|a/', true],
            'duplicate class' => ['/[ab]|[ab]/', true],
            'duplicate non-capturing' => ['/(?:a)|(?:a)/', true],
            'capturing groups skipped' => ['/(a)|(a)/', false],
            'no duplicates' => ['/a|b/', false],
        ];
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideOptimalQuantifierConcatenationPatterns(): array
    {
        return [
            'left subset right' => ['/\d+\w+/', true],
            'right subset left' => ['/\w+\d+/', true],
            'equal sets' => ['/a+a+/', true],
            'bounded left' => ['/\w{3,5}\d*/', false],
            'fixed right' => ['/\w+\d{4}/', false],
            'capturing group' => ['/(a)+a+/', false],
        ];
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideUselessBackreferencePatterns(): array
    {
        return [
            'forward reference' => ['/\1(a)/', true],
            'nested backreference' => ['/(a\1)/', true],
            'different alternative' => ['/(a)|\1/', true],
            'empty group' => ['/(\\b)a\\1/', true],
            'valid backreference' => ['/(a)\\1/', false],
            'optional group' => ['/(a)?\\1/', false],
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
