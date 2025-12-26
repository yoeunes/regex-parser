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

namespace RegexParser\Tests\Integration\Bridge\PHPStan;

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
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo#. (Pattern: "/foo")',
                21,
            ],
            [
                'Regex syntax error: Invalid quantifier range "{2,1}": min > max. (Pattern: "/a{2,1}/")',
                22,
            ],
            [
                'ReDoS vulnerability detected (CRITICAL): /(a+)+$/',
                23,
                "Unbounded quantifier detected. May cause backtracking on non-matching input. Consider making it possessive (*+) or using atomic groups (?>...). Suggested: Consider using possessive quantifiers or atomic groups to limit backtracking.\n".
                "Nested unbounded quantifiers detected. This allows exponential backtracking. Consider using atomic groups (?>...) or possessive quantifiers (*+, ++). Suggested: Replace inner quantifiers with possessive variants or wrap them in (?>...).\n".
                "\n".
                "Read more about possessive quantifiers: https://github.com/yoeunes/regex-parser/blob/main/docs/reference.md#possessive-quantifiers\n".
                "Read more about atomic groups: https://github.com/yoeunes/regex-parser/blob/main/docs/reference.md#atomic-groups\n".
                'Read more about catastrophic backtracking: https://github.com/yoeunes/regex-parser/blob/main/docs/reference.md#catastrophic-backtracking',
            ],
            [
                'Nested quantifiers can cause catastrophic backtracking.',
                23,
                "Consider using atomic groups (?>...) or possessive quantifiers.\nRead more: https://github.com/yoeunes/regex-parser/blob/main/docs/reference.md#nested-quantifiers",
            ],
            // Note: MEDIUM ReDoS on line 24 is filtered out by 'high' threshold
            [
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo1#. (Pattern: "/foo1")',
                35,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo2#. (Pattern: "/foo2")',
                35,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo3#. (Pattern: "/foo3")',
                35,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo4#. (Pattern: "/foo4")',
                35,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo5#. (Pattern: "/foo5")',
                35,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo6#. (Pattern: "/foo6")',
                35,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo7#. (Pattern: "/foo7")',
                35,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo8#. (Pattern: "/foo8")',
                35,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo9#. (Pattern: "/foo9")',
                35,
            ],
            [
                'Regex syntax error: No closing delimiter "/" found. You opened with "/"; expected closing "/". Tip: escape "/" inside the pattern (\\/) or use a different delimiter, e.g. #foo10#. (Pattern: "/foo10")',
                35,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new PregValidationRule(
            ignoreParseErrors: false,
            reportRedos: true,
            redosThreshold: 'high',
            suggestOptimizations: false,
        );
    }
}
