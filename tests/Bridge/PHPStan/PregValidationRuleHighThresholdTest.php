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
 * @extends RuleTestCase<PregValidationRule>
 */
final class PregValidationRuleHighThresholdTest extends RuleTestCase
{
    public function test_rule(): void
    {
        $this->analyse([__DIR__.'/Fixtures/MyClass.php'], [
            [
                'Regex syntax error: No closing delimiter "/" found.',
                21,
            ],
            [
                'Regex syntax error: Invalid quantifier range "{2,1}": min > max at position 0.',
                22,
            ],
            [
                'Regex syntax error: Potential catastrophic backtracking (ReDoS): nested unbounded quantifier "+" at position 1.',
                23,
            ],
            // Note: MEDIUM ReDoS on line 24 is filtered out by 'high' threshold
            [
                'Regex syntax error: No closing delimiter "/" found.',
                34,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found.',
                34,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found.',
                34,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found.',
                34,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found.',
                34,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found.',
                34,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found.',
                34,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found.',
                34,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found.',
                34,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found.',
                34,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new PregValidationRule(
            ignoreParseErrors: false,
            reportRedos: true,
            redosThreshold: 'high',
        );
    }
}