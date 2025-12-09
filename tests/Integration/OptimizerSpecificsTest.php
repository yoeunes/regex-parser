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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Regex;

final class OptimizerSpecificsTest extends TestCase
{
    public function test_noise_reordering_same_length(): void
    {
        $original = '/[a-zA-Z0-9]/';
        $optimized = Regex::create()->optimize($original);
        $this->assertSame(\strlen($original), \strlen($optimized));
    }

    public function test_range_efficiency_no_range_for_two_chars(): void
    {
        $regex = Regex::create()->optimize('/[=>]/');
        // Should not create [=->] since it covers only 2 chars
        $this->assertSame('/[=>]/', $regex);
    }

    public function test_valid_range_created(): void
    {
        $regex = Regex::create()->optimize('/[abc]/');
        $this->assertSame('/[a-c]/', $regex);
    }

    public function test_array_case_no_invalid_range(): void
    {
        $pattern = "/^array\(\s*([^\s^=^>]*)(\s*=>\s*(.*))?\s*\)/i";
        $optimized = Regex::create()->optimize($pattern);
        // Ensure no range like =-> is created
        $this->assertStringNotContainsString('->', $optimized);
        // And length should be less or equal, but since it's complex, just check no invalid range
    }
}