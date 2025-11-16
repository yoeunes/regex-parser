<?php

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
    private function validate(string $regex): void
    {
        $parser = new Parser();
        $ast = $parser->parse($regex);
        $visitor = new ValidatorNodeVisitor();
        $ast->accept($visitor);
    }

    #[DoesNotPerformAssertions]
    public function testValidateValid(): void
    {
        $this->validate('/foo{1,3}/ims');
    }

    public function testThrowsOnInvalidQuantifierRange(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid quantifier range "{3,1}": min > max');
        $this->validate('/foo{3,1}/');
    }

    public function testThrowsOnInvalidFlags(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unknown regex flag(s) found: "z"');
        $this->validate('/foo/imz');
    }

    public function testThrowsOnNestedQuantifiers(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Potential catastrophic backtracking: nested quantifiers detected.');
        $this->validate('/(a+)*b/');
    }

    #[DoesNotPerformAssertions]
    public function testAllowsNonNestedQuantifiers(): void
    {
        // (a*)(b*) is fine
        $this->validate('/(a*)(b*)/');
    }

    #[DoesNotPerformAssertions]
    public function testValidateValidCharClass(): void
    {
        $this->validate('/[a-z\d-]/');
    }

    public function testThrowsOnInvalidRange(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid range "z-a": start character comes after end character.');
        $this->validate('/[z-a]/');
    }

    public function testThrowsOnInvalidRangeWithCharType(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid range: ranges must be between literal characters');

        // This regex is invalid because \d is not a literal
        $this->validate('/[a-\d]/');
    }

    public function testThrowsOnInvalidBackref(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Backreference to non-existent group');
        $this->validate('/\2/'); // No group 2
    }

    public function testThrowsOnInvalidUnicodeProp(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid Unicode property');
        $this->validate('/\p{invalid}/');
    }

    #[DoesNotPerformAssertions]
    public function testValidateValidSubroutine(): void
    {
        $this->validate('/(a)(?1)/');
        $this->validate('/(a)(?-1)/');
        $this->validate('/(?<name>a)(?&name)/');
        $this->validate('/(?R)/');
    }

    public function testThrowsOnInvalidNumericSubroutine(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Subroutine call to non-existent group: 1');
        $this->validate('/(?1)/'); // No group 1
    }

    public function testThrowsOnInvalidNamedSubroutine(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Subroutine call to non-existent named group: name');
        $this->validate('/(?&name)/'); // No group "name"
    }

    public function testThrowsOnDuplicateGroupName(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Duplicate group name: name');
        $this->validate('/(?<name>a)(?<name>b)/');
    }
}
