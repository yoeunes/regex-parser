<?php

namespace RegexParser\Tests\Visitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Lexer\Lexer;
use RegexParser\Parser\Parser;
use RegexParser\Visitor\CompilerVisitor;

class CompilerVisitorTest extends TestCase
{
    private function compile(string $regex): string
    {
        $parser = new Parser(new Lexer($regex));
        $ast = $parser->parse($regex);
        $visitor = new CompilerVisitor();

        return $ast->accept($visitor);
    }

    public function testCompileSimple(): void
    {
        $this->assertSame('foo', $this->compile('/foo/'));
    }

    public function testCompileGroupAndAlternation(): void
    {
        $this->assertSame('(foo|bar)?', $this->compile('/(foo|bar)?/'));
    }

    public function testCompilePrecedence(): void
    {
        $this->assertSame('ab*c', $this->compile('/ab*c/'));
    }

    public function testCompileEscaped(): void
    {
        // The compiler must re-escape special characters
        $this->assertSame('a\*c', $this->compile('/a\*c/'));
    }
}
