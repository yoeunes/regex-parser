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

namespace RegexParser\Tests\Bridge\PHPStan;

use PHPUnit\Framework\TestCase;
use RegexParser\Bridge\PHPStan\PregValidationRule;

/**
 * Unit tests for PregValidationRule's optimization safety checks.
 */
final class PregValidationRuleOptimizationSafetyTest extends TestCase
{
    private PregValidationRule $rule;

    protected function setUp(): void
    {
        $this->rule = new PregValidationRule(
            ignoreParseErrors: false,
            reportRedos: false,
            redosThreshold: 'high',
            suggestOptimizations: true,
        );
    }

    public function test_rejects_effectively_empty_patterns(): void
    {
        $this->assertFalse($this->rule->isOptimizationSafe('/abc/', '##'));
        $this->assertFalse($this->rule->isOptimizationSafe('/abc/i', '//i'));
        $this->assertFalse($this->rule->isOptimizationSafe('#test#', '##'));
    }

    public function test_rejects_broken_anchors(): void
    {
        $this->assertFalse($this->rule->isOptimizationSafe('/abc$/', '$/'));
        $this->assertFalse($this->rule->isOptimizationSafe('/^abc/', '/^/'));
    }

    public function test_rejects_patterns_removing_newlines(): void
    {
        $this->assertFalse($this->rule->isOptimizationSafe('/\r?\n/', '/\r?/'));
        $this->assertFalse($this->rule->isOptimizationSafe('/-- (.+)\n/', '/-- (.+) /'));
    }

    public function test_rejects_drastic_length_reduction(): void
    {
        $this->assertFalse($this->rule->isOptimizationSafe('/abcd/', '/a/'));
        $this->assertFalse($this->rule->isOptimizationSafe('#longpattern#', '#x#'));
    }

    public function test_accepts_valid_optimizations(): void
    {
        $this->assertTrue($this->rule->isOptimizationSafe('/[0-9]+/', '/\d+/'));
        $this->assertTrue($this->rule->isOptimizationSafe('/abc/', '/abc/')); // Same
        $this->assertTrue($this->rule->isOptimizationSafe('/a+/', '/a+/')); // Not shorter
    }

    public function test_accepts_short_patterns_if_original_is_short(): void
    {
        $this->assertFalse($this->rule->isOptimizationSafe('/ab/', '/a/'));
    }
}
