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

namespace RegexParser\Tests\NodeVisitor;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\NodeVisitor\ValidatorNodeVisitor;
use RegexParser\Regex;

final class ValidatorEdgeCaseTest extends TestCase
{
    private Regex $regex;

    private ValidatorNodeVisitor $validator;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
        $this->validator = new ValidatorNodeVisitor();
    }

    public function test_invalid_quantifier_range(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('min > max');
        $this->validate('/a{5,2}/');
    }

    public function test_invalid_backreference_zero(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Octal escape \0 is not allowed');
        $this->validate('/\0/');
    }

    public function test_invalid_backreference_out_of_bounds(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Backreference to non-existent group');
        $this->validate('/\5/'); // Group 5 doesn't exist
    }

    public function test_invalid_relative_backref(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('exceeds total group count');
        $this->validate('/(a)\g{-5}/');
    }

    public function test_invalid_named_backref(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('non-existent named group');
        $this->validate('/(a)\k<foo>/');
    }

    #[DoesNotPerformAssertions]
    public function test_variable_quantifier_in_lookbehind(): void
    {
        // PCRE2 (PHP 7.3+) supports variable-length lookbehinds
        // This should NOT throw an exception
        $this->validate('/(?<=a*)/');
    }

    public function test_keep_in_lookbehind(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('\K (keep) is not allowed in lookbehinds');
        $this->validate('/(?<=a\K)/');
    }

    public function test_invalid_posix_class(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid POSIX class');
        $this->validate('/[[:fake:]]/');
    }

    public function test_invalid_posix_negation_of_word(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Negation of POSIX class "word" is not supported');
        $this->validate('/[[:^word:]]/');
    }

    public function test_invalid_conditional_condition(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid conditional construct');
        // Literal 'a' is not a valid condition
        $this->validate('/(?(a)b)/');
    }

    public function test_invalid_unicode_codepoint(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('out of range');
        $this->validate('/\u{110000}/u');
    }

    public function test_invalid_unicode_property(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid or unsupported Unicode property');
        $this->validate('/\p{InvalidProp}/u');
    }

    private function validate(string $regex): void
    {
        $ast = $this->regex->parse($regex);
        $ast->accept($this->validator);
    }
}
