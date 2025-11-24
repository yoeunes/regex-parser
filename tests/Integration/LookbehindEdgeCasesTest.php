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

namespace Yoeunes\RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Regex;

final class LookbehindEdgeCasesTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    public function test_fixed_length_literal_lookbehind(): void
    {
        $result = $this->regex->validate('/(?<=foo)bar/');

        $this->assertTrue($result->isValid, 'Fixed-length lookbehind should be valid');
    }

    public function test_fixed_length_digit_lookbehind(): void
    {
        $result = $this->regex->validate('/(?<=\d{3})test/');

        $this->assertTrue($result->isValid);
    }

    public function test_fixed_length_escaped_chars_lookbehind(): void
    {
        $result = $this->regex->validate('/(?<=\(\d{2}\))/');

        $this->assertTrue($result->isValid);
    }

    public function test_fixed_length_char_class_lookbehind(): void
    {
        $result = $this->regex->validate('/(?<=[a-z]{5})/');

        $this->assertTrue($result->isValid);
    }

    public function test_fixed_length_char_sequence_lookbehind(): void
    {
        $result = $this->regex->validate('/(?<=\w\d)/');

        $this->assertTrue($result->isValid);
    }

    public function test_fixed_length_with_escape_lookbehind(): void
    {
        $result = $this->regex->validate('/(?<=test\.)pattern/');

        $this->assertTrue($result->isValid);
    }

    public function test_fixed_length_backslash_lookbehind(): void
    {
        $result = $this->regex->validate('/(?<=\\\\)/');

        $this->assertTrue($result->isValid);
    }

    public function test_fixed_length_single_char_class_lookbehind(): void
    {
        $result = $this->regex->validate('/(?<=[A-Z])/');

        $this->assertTrue($result->isValid);
    }

    public function test_variable_length_star_quantifier_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=a*)b/');

        $this->assertFalse($result->isValid, 'Variable-length star quantifier should be invalid');
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_variable_length_plus_quantifier_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=a+)b/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_variable_length_optional_quantifier_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=a?)b/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_variable_length_unbounded_range_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=a{1,})b/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_variable_length_char_class_star_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=[a-z]*)test/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_variable_length_digit_plus_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=\d+)end/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_variable_length_alternation_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=(a|ab))c/');

        $this->assertFalse($result->isValid, 'Alternation with different lengths should be invalid in lookbehind');
        $this->assertNotNull($result->error);
    }

    public function test_variable_length_group_star_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=(test)*)/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_mixed_fixed_and_variable_length_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=\d{3}a+)/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_literal_repeat_plus_variable_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=[a]{3}b*)/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_optional_group_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=(?:test)?)/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
    }

    public function test_variable_range_bound_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<=\w{0,5})/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_negative_variable_lookbehind_is_invalid(): void
    {
        $result = $this->regex->validate('/(?<!a+)/');

        $this->assertFalse($result->isValid);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('lookbehind', strtolower($result->error));
    }

    public function test_fixed_length_exactly_three_chars(): void
    {
        $result = $this->regex->validate('/(?<=abc)/');

        $this->assertTrue($result->isValid);
    }

    public function test_fixed_length_multiple_digits(): void
    {
        $result = $this->regex->validate('/(?<=\d\d\d)/');

        $this->assertTrue($result->isValid);
    }

    public function test_fixed_range_lookbehind(): void
    {
        $result = $this->regex->validate('/(?<=[a-z]{3,3})/');

        $this->assertTrue($result->isValid);
    }

    public function test_complex_fixed_pattern(): void
    {
        $result = $this->regex->validate('/(?<=test\d{2})/');

        $this->assertTrue($result->isValid);
    }
}
