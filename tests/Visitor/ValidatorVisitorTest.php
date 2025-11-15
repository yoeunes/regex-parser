<?php

namespace RegexParser\Tests\Visitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Parser\Parser;
use RegexParser\Visitor\ValidatorVisitor;

class ValidatorVisitorTest extends TestCase
{
    public function testValidateValid(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('foo{1,3}');
        $visitor = new ValidatorVisitor();
        $this->assertNull($ast->accept($visitor));
    }

    public function testThrowsOnInvalidQuantifier(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid quantifier range: min > max');
        $parser = new Parser();
        $ast = $parser->parse('foo{3,1}');
        $visitor = new ValidatorVisitor();
        $ast->accept($visitor);
    }
}
