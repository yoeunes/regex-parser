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

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use RegexParser\Bridge\PHPStan\PregValidationRule;

/**
 * Tests the safeguard in PregValidationRule that prevents suggesting invalid optimizations.
 *
 * @extends RuleTestCase<PregValidationRule>
 */
final class PregValidationRuleSafeguardTest extends RuleTestCase
{
    public function test_no_optimization_suggested_for_invalid_optimized_patterns(): void
    {
        $this->analyse([__DIR__.'/Fixtures/SafeguardFixture.php'], [
            // No errors expected, because optimizations are invalid and should not be suggested
        ]);
    }

    protected function getRule(): Rule
    {
        return new PregValidationRule(
            ignoreParseErrors: false,
            reportRedos: false,
            redosThreshold: 'high',
            suggestOptimizations: true, // Enable optimizations to test the safeguard
        );
    }
}
