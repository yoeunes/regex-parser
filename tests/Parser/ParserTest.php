<?php

namespace RegexParser\Tests\Parser;

use PHPUnit\Framework\TestCase;
use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Exception\ParserException;
use RegexParser\Parser\Parser;

class ParserTest extends TestCase
{
    public function testParseLiteral(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('foo');
        $this->assertInstanceOf(LiteralNode::class, $ast);
        $this->assertSame('foo', $ast->value);
    }

    public function testParseGroupWithQuantifier(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('(bar)?');
        $this->assertInstanceOf(QuantifierNode::class, $ast);
        $this->assertSame('?', $ast->quantifier);
        $this->assertInstanceOf(GroupNode::class, $ast->node);
        $this->assertCount(1, $ast->node->children);
        $this->assertInstanceOf(LiteralNode::class, $ast->node->children[0]);
        $this->assertSame('bar', $ast->node->children[0]->value);
    }

    public function testParseAlternation(): void
    {
        $parser = new Parser();
        $ast = $parser->parse('foo|bar');
        $this->assertInstanceOf(AlternationNode::class, $ast);
        $this->assertCount(2, $ast->alternatives);
        $this->assertSame('foo', $ast->alternatives[0]->value);
        $this->assertSame('bar', $ast->alternatives[1]->value);
    }

    public function testThrowsOnUnmatchedGroup(): void
    {
        $this->expectException(ParserException::class);
        $parser = new Parser();
        $parser->parse('(foo');
    }
}
