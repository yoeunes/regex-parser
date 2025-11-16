<?php
namespace RegexParser\Tests\Visitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Parser\Parser;
use RegexParser\Visitor\DumperVisitor;

class DumperVisitorTest extends TestCase
{
    public function testDumpSimple(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('/a(b|c)/');
        $dumper = new DumperVisitor();
        $dump = $ast->accept($dumper);
        $this->assertStringContainsString('Group(type: capturing)', $dump);
        $this->assertStringContainsString('Alternation', $dump);
    }
}
