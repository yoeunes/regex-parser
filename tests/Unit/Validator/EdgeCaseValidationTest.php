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

namespace RegexParser\Tests\Unit\Validator;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Regex;

final class EdgeCaseValidationTest extends TestCase
{
    private Regex $regexService;

    protected function setUp(): void
    {
        $this->regexService = Regex::create();
    }

    public function test_back_reference_to_non_existent_group(): void
    {
        $result = $this->regexService->validate('/(a)\10/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Backreference to non-existent group: \10', (string) $result->error);
    }

    public function test_back_reference_to_non_existent_named_group(): void
    {
        $result = $this->regexService->validate('/(?<n>a)\k<missing>/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Backreference to non-existent named group: "missing"', (string) $result->error);
    }

    public function test_variable_length_lookbehind(): void
    {
        $result = $this->regexService->validate('/(?<=a+)/');
        $this->assertFalse($result->isValid, 'Unbounded lookbehind should be rejected.');
    }

    public function test_variable_length_lookbehind_with_range(): void
    {
        // PCRE2 (PHP 7.3+) supports variable-length lookbehinds
        $result = $this->regexService->validate('/(?<=a{1,3})/');
        $this->assertTrue($result->isValid, 'Variable-length lookbehind with range should be valid in PCRE2');
    }

    public function test_invalid_range_start_greater_than_end(): void
    {
        $result = $this->regexService->validate('/[z-a]/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Invalid range "z-a"', (string) $result->error);
    }

    public function test_duplicate_group_name(): void
    {
        $result = $this->regexService->validate('/(?<n>a)(?<n>b)/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Duplicate group name "n"', (string) $result->error);
    }

    public function test_unconsumed_tokens(): void
    {
        // Test that invalid flags are properly rejected.
        // The pattern '/foo/z' has an invalid flag 'z' which should trigger validation.
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "z"');

        $this->regexService->parse('/foo/z');
    }

    public function test_valid_octal_is_accepted(): void
    {
        // \10 should be valid if there are 10 groups
        $pattern = '/(a)(a)(a)(a)(a)(a)(a)(a)(a)(a)\10/';
        $result = $this->regexService->validate($pattern);
        $this->assertTrue($result->isValid, 'Should be valid');
    }
}
