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

use PHPUnit\Framework\TestCase;
use RegexParser\Regex;

final class OptimizerSafetyTest extends TestCase
{
    public function test_unicode_digit_semantics(): void
    {
        // This helps us understand the current PCRE2 environment
        $arabicDigit = '١';
        $matchD = preg_match('/^\d$/u', $arabicDigit);
        $matchRange = preg_match('/^[0-9]$/u', $arabicDigit);

        // Assert that \d with u matches Unicode digits
        $this->assertSame(1, $matchD);
        // Assert that [0-9] with u does not match Unicode digits
        $this->assertSame(0, $matchRange);
    }

    public function test_no_flag_optimizes(): void
    {
        $regex = Regex::create();
        $optimized = $regex->optimize('/[0-9]/')->optimized;

        $this->assertSame('/\d/', $optimized);
    }

    public function test_case_insensitive_optimizes(): void
    {
        $regex = Regex::create();
        $optimized = $regex->optimize('/[0-9]/i')->optimized;

        $this->assertSame('/\d/i', $optimized);
    }

    public function test_unicode_flag_no_optimize(): void
    {
        $regex = Regex::create();
        $optimized = $regex->optimize('/[0-9]/u')->optimized;

        $this->assertSame('/[0-9]/u', $optimized);
    }

    public function test_unicode_flag_complex_no_optimize(): void
    {
        $regex = Regex::create();
        $optimized = $regex->optimize('/[0-9]+/u')->optimized;

        $this->assertSame('/[0-9]+/u', $optimized);
    }
}
