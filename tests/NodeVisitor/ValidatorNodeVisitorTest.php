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
use RegexParser\Parser;

class ValidatorNodeVisitorTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function test_validate_valid(): void
    {
        $this->validate('/foo{1,3}/ims');
    }

    public function test_throws_on_invalid_quantifier_range(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid quantifier range "{3,1}": min > max at position 2.');
        $this->validate('/foo{3,1}/');
    }

    public function test_throws_on_invalid_flags(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "z"');
        $this->validate('/foo/imz');
    }

    public function test_throws_on_nested_quantifiers(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Potential catastrophic backtracking (ReDoS): nested unbounded quantifier "+" at position 1.');
        $this->validate('/(a+)*b/');
    }

    #[DoesNotPerformAssertions]
    public function test_allows_non_nested_quantifiers(): void
    {
        // (a*)(b*) is fine
        $this->validate('/(a*)(b*)/');
    }

    #[DoesNotPerformAssertions]
    public function test_validate_valid_char_class(): void
    {
        $this->validate('/[a-z\d-]/');
    }

    public function test_throws_on_invalid_range(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid range "z-a" at position 1: start character comes after end character.');
        $this->validate('/[z-a]/');
    }

    public function test_throws_on_invalid_range_with_char_type(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid range at position 1: ranges must be between literal characters (e.g., "a-z"). Found non-literal.');

        // This regex is invalid because \d is not a literal
        $this->validate('/[a-\d]/');
    }

    public function test_throws_on_invalid_backref(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Backreference to non-existent group: \2 at position 0.');
        $this->validate('/\2/'); // No group 2
    }

    public function test_throws_on_invalid_unicode_prop(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid or unsupported Unicode property: \p{invalid} at position 0.');
        $this->validate('/\p{invalid}/');
    }

    #[DoesNotPerformAssertions]
    public function test_validate_valid_subroutine(): void
    {
        $this->validate('/(a)(?1)/');
        $this->validate('/(a)(?-1)/');
        $this->validate('/(?<name>a)(?&name)/');
        $this->validate('/(?R)/');
    }

    public function test_throws_on_invalid_numeric_subroutine(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Subroutine call to non-existent group: 1 at position 0.');
        $this->validate('/(?1)/'); // No group 1
    }

    public function test_throws_on_invalid_named_subroutine(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Subroutine call to non-existent named group: "name" at position 0.');
        $this->validate('/(?&name)/'); // No group "name"
    }

    public function test_throws_on_duplicate_group_name(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Duplicate group name "name" at position 10.');
        $this->validate('/(?<name>a)(?<name>b)/');
    }

    private function validate(string $regex): void
    {
        $parser = new Parser();
        $ast = $parser->parse($regex);
        $visitor = new ValidatorNodeVisitor();
        $ast->accept($visitor);
    }
}
