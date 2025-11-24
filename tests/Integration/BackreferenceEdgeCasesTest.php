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
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\Regex;

final class BackreferenceEdgeCasesTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    public function test_basic_backreference(): void
    {
        $pattern = '/(a)\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled, 'Basic backreference should round-trip correctly');

        $this->assertMatchesRegularExpression($pattern, 'aa');
        $this->assertDoesNotMatchRegularExpression($pattern, 'ab');
    }

    public function test_double_digit_backreference(): void
    {
        $pattern = '/(\d\d)\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, '1212');
        $this->assertDoesNotMatchRegularExpression($pattern, '1234');
    }

    public function test_backreference_with_quantifier(): void
    {
        $pattern = '/([a-z])\1{2}/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'aaa');
        $this->assertDoesNotMatchRegularExpression($pattern, 'abc');
    }

    public function test_backreference_to_alternation(): void
    {
        $pattern = '/(foo|bar)\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'foofoo');
        $this->assertMatchesRegularExpression($pattern, 'barbar');
        $this->assertDoesNotMatchRegularExpression($pattern, 'foobar');
    }

    public function test_multiple_different_backreferences(): void
    {
        $pattern = '/(a)(b)\1\2/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'abab');
        $this->assertDoesNotMatchRegularExpression($pattern, 'abba');
    }

    public function test_backreferences_reverse_order(): void
    {
        $pattern = '/(a)(b)(c)\3\2\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'abccba');
    }

    public function test_mixed_order_backreferences(): void
    {
        $pattern = '/(a)(b)\2(c)\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'abbca');
    }

    public function test_backreference_to_nested_quantifier(): void
    {
        $pattern = '/((?:a+)+)\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);
    }

    public function test_backreference_inside_quantifier(): void
    {
        $pattern = '/(?:(a)\1)+/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'aaaa');
    }

    public function test_nested_groups_with_multiple_backreferences(): void
    {
        $pattern = '/((a))\1\2/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'aaaa');
    }

    public function test_complex_backreference_pattern(): void
    {
        $pattern = '/(test)(\w+)\2\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);
    }

    public function test_backreference_any_char(): void
    {
        $pattern = '/(.)\\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'aa');
        $this->assertMatchesRegularExpression($pattern, '##');
    }

    public function test_backreference_with_char_class_and_quantifier(): void
    {
        $pattern = '/([a-z]+)\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'testtest');
        $this->assertDoesNotMatchRegularExpression($pattern, 'testword');
    }

    public function test_backreference_to_word(): void
    {
        $pattern = '/(test)\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'testtest');
        $this->assertDoesNotMatchRegularExpression($pattern, 'testing');
    }

    public function test_alternation_with_backreferences(): void
    {
        $pattern = '/(a|b)(a|b)\1\2/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);
    }

    public function test_complex_mixed_backreferences(): void
    {
        $pattern = '/(a)(b)\2(c)\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'abbca');
        $this->assertDoesNotMatchRegularExpression($pattern, 'abcba');
    }

    public function test_two_digit_backreference(): void
    {
        $pattern = '/(a)(b)(c)(d)(e)(f)(g)(h)(i)(j)\10/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);
    }

    public function test_backreference_with_alternation_in_group(): void
    {
        $pattern = '/(a|b|c)\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);
    }

    public function test_multiple_consecutive_backreferences(): void
    {
        $pattern = '/(x)(y)(z)\1\2\3/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'xyzxyz');
    }

    public function test_backreference_with_digit_class(): void
    {
        $pattern = '/(\d+)\1/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, '123123');
    }

    public function test_backreference_in_complex_pattern(): void
    {
        $pattern = '/^(a+)b\1$/';
        $compiled = $this->roundTrip($pattern);

        $this->assertSame($pattern, $compiled);

        $this->assertMatchesRegularExpression($pattern, 'aabaa');
    }

    private function roundTrip(string $pattern): string
    {
        $ast = $this->regex->parse($pattern);
        $compiler = new CompilerNodeVisitor();

        return $ast->accept($compiler);
    }
}
