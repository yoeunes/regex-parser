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

namespace RegexParser\Tests\Unit\Optimizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegexParser\Regex;

/**
 * Tests for optimizer safety to prevent semantic changes.
 */
final class OptimizerSafetyTest extends TestCase
{
    #[DataProvider('provideOptimizationCases')]
    public function test_optimization_does_not_change_semantics(string $input, string $expected): void
    {
        $regexService = Regex::create();
        $optimized = $regexService->optimize($input, ['autoPossessify' => true])->optimized;

        $this->assertSame($expected, $optimized);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideOptimizationCases(): iterable
    {
        // --- 1. Sanity Checks (No Change Expected) ---
        yield 'Different literals' => ['/a|b/', '/[ab]/'];
        yield 'Distinct ranges' => ['/[a-z]|[0-9]/', '/[a-z0-9]/'];
        yield 'Distinct words' => ['/fo{2}|bar/', '/fo{2}|bar/'];

        // --- 2. The Regression Case (CRITICAL) ---
        // Ensure distinct patterns are NOT deduplicated
        yield 'Distinct patterns with different quantifiers' => ['/[A-Z]{2,}|[a-z]/', '/[A-Z]{2,}|[a-z]/'];
        yield 'Distinct literals with same length' => ['/abc|def/', '/abc|def/'];

        // --- 3. Sequence Compaction (Safe) ---
        yield 'Repeat literal 3 times' => ['/aaa/', '/a{3}/'];
        yield 'Repeat literal 2 times' => ['/aa/', '/a{2}/'];
        yield 'Repeat digit type' => ['/\d\d/', '/\d{2}/'];
        yield 'Merge existing quantifiers' => ['/a{2}a{3}/', '/a{5}/'];
        yield 'Merge quantifier and literal' => ['/a{2}a/', '/a{3}/'];

        // --- 4. Quantifier Normalization (Safe) ---
        yield 'Normalize {0,}' => ['/a{0,}/', '/a*/'];
        yield 'Normalize {1,}' => ['/a{1,}/', '/a+/'];
        yield 'Normalize {0,1}' => ['/a{0,1}/', '/a?/'];
        yield 'Unwrap {1}' => ['/a{1}/', '/a/'];
        yield 'Remove {0}' => ['/fo{2}bar/', '/fo{2}bar/'];

        // --- 5. Alternation Deduplication (Safe) ---
        yield 'Strict duplicates' => ['/a|a/', '/[a]/'];
        yield 'Strict duplicates words' => ['/fo{2}/', '/fo{2}/'];
        yield 'Triplicates' => ['/a|b|a/', '/[ab]/'];

        // --- 6. Prefix Factorization (Safe) ---
        yield 'Common prefix literals' => ['/foo_a|foo_b/', '/fo{2}_a|fo{2}_b/'];
        yield 'Common prefix mixed' => ['/user_id|user_name/', '/user_id|user_name/'];
        yield 'Prefix is full alternative' => ['/WIN|WINDOWS/', '/WIN|WINDOWS/'];

        // --- 7. Auto-Possessivization (Safe) ---
        // Digits \d cannot match 'a', so \d+ should become \d++
        yield 'Safe possessivization' => ['/\d+a/', '/\d++a/'];
        // Digits \d CAN match '1', so no change allowed
        yield 'Unsafe possessivization' => ['/\d+1/', '/\d+1/'];

        // --- 8. Complex / Real World ---
        yield 'PHP CodeSniffer Array Regex' => [
            '/^ar{2}ay\(\s*([^\s^=^>]*)(\s*=>\s*(.*))?\s*\)/i',
            '/^ar{2}ay\(\s*([^=>\^\s]*)(\s*=>\s*(.*))?\s*\)/i'
        ];

        // --- 9. Capture Safety (Critical) ---
        // Scenario A: Capturing groups prevent compaction
        yield 'Capturing groups block compaction' => ['/(?:(a)b)(?:(a)b)/', '/(?:(a)b)(?:(a)b)/'];
        // Scenario B: Non-capturing groups allow compaction
        yield 'Non-capturing groups allow compaction' => ['/(?:ab)(?:ab)/', '/ab{2}/'];
        // Scenario C: Alternation factorization disabled by default
        yield 'Alternation factorization disabled' => ['/(a)b|(c)b/', '/(a)b|(c)b/'];
    }
}
