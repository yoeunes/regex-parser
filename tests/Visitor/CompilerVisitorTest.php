<?php

namespace RegexParser\Tests\Visitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Parser\Parser;
use RegexParser\Visitor\CompilerVisitor;

class CompilerVisitorTest extends TestCase
{
    public function testCompileSimple(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('foo');
        $visitor = new CompilerVisitor();
        $this->assertSame('foo', $ast->accept($visitor));
    }

    public function testCompileGroupAndAlternation(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('(foo|bar)?');
        $visitor = new CompilerVisitor();
        $this->assertSame('(foo|bar)?', $ast->accept($visitor));
    }
}
