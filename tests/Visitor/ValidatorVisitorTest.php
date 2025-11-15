<?php

namespace RegexParser\Tests\Visitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Lexer\Lexer;
use RegexParser\Parser\Parser;
use RegexParser\Visitor\ValidatorVisitor;

class ValidatorVisitorTest extends TestCase
{
    private function validate(string $regex): void
    {
        $parser = new Parser(new Lexer($regex));
        $ast = $parser->parse($regex);
        $visitor = new ValidatorVisitor();
        $ast->accept($visitor);
    }

    public function testValidateValid(): void
    {
        $this->validate('/foo{1,3}/');
        $this->assertNull(null); // Si aucune exception n'est levée
    }

    public function testThrowsOnInvalidQuantifier(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Invalid quantifier range: min > max');
        $this->validate('/foo{3,1}/');
    }
}
