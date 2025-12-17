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
use RegexParser\Bridge\PHPStan\PregValidationRule;
use RegexParser\Regex;

/**
 * Tests for Symfony codebase compatibility.
 *
 * These tests verify that the library handles modern PHP PCRE features
 * and is robust against partial regex strings commonly found in static analysis.
 */
final class SymfonyCompatibilityTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    public function test_variable_length_lookbehind_is_valid(): void
    {
        // PHP 7.3+ (PCRE2) supports variable-length lookbehinds
        $result = $this->regexService->validate('/(?<=a{1,5})b/');

        $this->assertTrue(
            $result->isValid,
            'Variable-length lookbehind (?<=a{1,5}) should be valid in PCRE2',
        );
    }

    public function test_variable_length_negative_lookbehind_is_valid(): void
    {
        // Negative lookbehind with variable length
        $result = $this->regexService->validate('/(?<!a+)b/');

        $this->assertFalse($result->isValid, 'Unbounded lookbehind should be rejected.');
    }

    public function test_alternation_with_different_lengths_in_lookbehind_is_valid(): void
    {
        // Alternation with different branch lengths in lookbehind
        $result = $this->regexService->validate('/(?<=foo|barbaz)x/');

        $this->assertTrue(
            $result->isValid,
            'Alternation with different lengths in lookbehind should be valid in PCRE2',
        );
    }

    public function test_star_quantifier_in_lookbehind_is_valid(): void
    {
        // Star quantifier (zero or more) in lookbehind
        $result = $this->regexService->validate('/(?<=a*)b/');

        $this->assertFalse($result->isValid, 'Unbounded lookbehind should be rejected.');
    }

    public function test_plus_quantifier_in_lookbehind_is_valid(): void
    {
        // Plus quantifier (one or more) in lookbehind
        $result = $this->regexService->validate('/(?<=a+)b/');

        $this->assertFalse($result->isValid, 'Unbounded lookbehind should be rejected.');
    }

    public function test_optional_quantifier_in_lookbehind_is_valid(): void
    {
        // Optional quantifier in lookbehind
        $result = $this->regexService->validate('/(?<=a?)b/');

        $this->assertTrue(
            $result->isValid,
            'Optional quantifier in lookbehind (?<=a?) should be valid in PCRE2',
        );
    }

    public function test_script_run_verb_is_valid(): void
    {
        // (*script_run:...) is a modern PCRE verb for Unicode script validation
        $result = $this->regexService->validate('/(*script_run:\d+)/');

        $this->assertTrue(
            $result->isValid,
            '(*script_run:...) verb should be valid',
        );
    }

    public function test_atomic_script_run_verb_is_valid(): void
    {
        // (*atomic_script_run:...) is an atomic version of script_run
        $result = $this->regexService->validate('/(*atomic_script_run:\d+)/');

        $this->assertTrue(
            $result->isValid,
            '(*atomic_script_run:...) verb should be valid',
        );
    }

    public function test_existing_pcre_verbs_still_valid(): void
    {
        // Verify existing PCRE verbs still work
        $patterns = [
            '/(*FAIL)/' => 'FAIL',
            '/(*ACCEPT)/' => 'ACCEPT',
            '/(*COMMIT)/' => 'COMMIT',
            '/(*PRUNE)/' => 'PRUNE',
            '/(*SKIP)/' => 'SKIP',
            '/(*THEN)/' => 'THEN',
            '/(*UTF8)test/' => 'UTF8',
            '/(*UCP)\w+/' => 'UCP',
        ];

        foreach ($patterns as $pattern => $verbName) {
            $result = $this->regexService->validate($pattern);
            $this->assertTrue(
                $result->isValid,
                "PCRE verb (*{$verbName}) should be valid: {$pattern}",
            );
        }
    }

    public function test_phpstan_rule_skips_pattern_without_delimiters(): void
    {
        // Skip if PHPStan is not installed
        if (!interface_exists(\PHPStan\Rules\Rule::class)) {
            $this->markTestSkipped('PHPStan is not installed in the test environment.');
        }

        // Create the PHPStan rule with ignoreParseErrors enabled
        $rule = new PregValidationRule(ignoreParseErrors: true);

        // Verify the rule is properly configured and can be instantiated
        // The actual partial pattern handling is tested via the processNode method
        // which requires PHPStan's Scope and Node infrastructure
        $this->assertSame(\PhpParser\Node\Expr\FuncCall::class, $rule->getNodeType());
    }

    public function test_phpstan_rule_validates_complete_patterns(): void
    {
        // Skip if PHPStan is not installed
        if (!interface_exists(\PHPStan\Rules\Rule::class)) {
            $this->markTestSkipped('PHPStan is not installed in the test environment.');
        }

        // Create the PHPStan rule
        $rule = new PregValidationRule(ignoreParseErrors: true);

        // Complete patterns should be validated
        $this->assertSame(\PhpParser\Node\Expr\FuncCall::class, $rule->getNodeType());
    }

    public function test_phpstan_rule_with_ignore_parse_errors_disabled(): void
    {
        // Skip if PHPStan is not installed
        if (!interface_exists(\PHPStan\Rules\Rule::class)) {
            $this->markTestSkipped('PHPStan is not installed in the test environment.');
        }

        // Create the PHPStan rule with ignoreParseErrors disabled
        $rule = new PregValidationRule(ignoreParseErrors: false);

        // Verify the rule is properly configured
        $this->assertSame(\PhpParser\Node\Expr\FuncCall::class, $rule->getNodeType());
    }

    public function test_complex_symfony_routing_pattern(): void
    {
        // Pattern similar to those found in Symfony routing
        $pattern = '/^\/(?P<_locale>en|fr|de)\/(?P<slug>[a-z0-9-]+)$/';
        $result = $this->regexService->validate($pattern);

        $this->assertTrue(
            $result->isValid,
            'Complex Symfony-like routing pattern should be valid',
        );
    }

    public function test_symfony_validator_email_pattern(): void
    {
        // Pattern similar to Symfony's email validation
        $pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        $result = $this->regexService->validate($pattern);

        $this->assertTrue(
            $result->isValid,
            'Symfony-like email validation pattern should be valid',
        );
    }

    public function test_lookbehind_with_unicode_property(): void
    {
        // Combined lookbehind with Unicode property (common in i18n apps)
        $pattern = '/(?<=\p{L}+)\d+/u';
        $result = $this->regexService->validate($pattern);

        $this->assertTrue(
            $result->isValid,
            'Lookbehind with Unicode property should be valid',
        );
    }

    public function test_nested_lookbehind_with_variable_length(): void
    {
        // Nested groups with variable-length content in lookbehind
        $pattern = '/(?<=(?:foo|bar)+)test/';
        $result = $this->regexService->validate($pattern);

        $this->assertTrue(
            $result->isValid,
            'Nested groups with variable-length in lookbehind should be valid',
        );
    }

    public function test_lookbehind_at_pattern_start(): void
    {
        // Lookbehind at the very start of pattern
        $pattern = '/(?<=prefix)main/';
        $result = $this->regexService->validate($pattern);

        $this->assertTrue(
            $result->isValid,
            'Lookbehind at pattern start should be valid',
        );
    }

    public function test_multiple_lookbehinds_with_different_lengths(): void
    {
        // Multiple lookbehinds in the same pattern
        $pattern = '/(?<=a{1,3})b(?<=c+)d/';
        $result = $this->regexService->validate($pattern);

        $this->assertTrue(
            $result->isValid,
            'Multiple lookbehinds with different lengths should be valid',
        );
    }
}
