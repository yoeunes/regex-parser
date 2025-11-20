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
use RegexParser\Exception\ParserException;
use RegexParser\Regex;

class EdgeCaseValidationTest extends TestCase
{
    public function test_back_reference_to_non_existent_group(): void
    {
        $result = Regex::create()->validate('/(a)\10/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Backreference to non-existent group: \10', (string) $result->error);
    }

    public function test_back_reference_to_non_existent_named_group(): void
    {
        $result = Regex::create()->validate('/(?<n>a)\k<missing>/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Backreference to non-existent named group: "missing"', (string) $result->error);
    }

    public function test_variable_length_lookbehind(): void
    {
        $result = Regex::create()->validate('/(?<=a+)/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Variable-length quantifiers (+) are not allowed in lookbehinds', (string) $result->error);
    }

    public function test_variable_length_lookbehind_with_range(): void
    {
        $result = Regex::create()->validate('/(?<=a{1,3})/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Variable-length quantifiers ({1,3}) are not allowed in lookbehinds', (string) $result->error);
    }

    public function test_invalid_range_start_greater_than_end(): void
    {
        $result = Regex::create()->validate('/[z-a]/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Invalid range "z-a"', (string) $result->error);
    }

    public function test_duplicate_group_name(): void
    {
        $result = Regex::create()->validate('/(?<n>a)(?<n>b)/');
        $this->assertFalse($result->isValid, 'Should be invalid');
        $this->assertStringContainsString('Duplicate group name "n"', (string) $result->error);
    }

    public function test_unconsumed_tokens(): void
    {
        // This uses parse(), so it SHOULD throw an exception if Parser is strict.
        // But currently Parser accepts extra flags.
        // Let's switch to validate() and expect failure due to invalid flags if we implement flag validation.
        // Or keep expectException if we fix Parser to throw.
        // For now, let's keep expectException but we know it fails.
        // I will implement flag validation in Parser next.
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown modifier');

        Regex::create()->parse('/foo/bar/');
    }

    public function test_valid_octal_is_accepted(): void
    {
        // \10 should be valid if there are 10 groups
        $pattern = '/(a)(a)(a)(a)(a)(a)(a)(a)(a)(a)\10/';
        $result = Regex::create()->validate($pattern);
        $this->assertTrue($result->isValid, 'Should be valid');
    }
}
