<?php

namespace RegexParser\Tests\Parser;

use PHPUnit\Framework\TestCase;
use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\SequenceNode;
use RegexParser\Exception\ParserException;
use RegexParser\Lexer\Lexer;
use RegexParser\Parser\Parser;

class ParserTest extends TestCase
{
    private readonly Parser $parser;

    protected function setUp(): void
    {
        // Le Parser est maintenant créé une fois avec son Lexer
        // On ne peut pas le réutiliser car le Lexer est lié à l'input.
        // On va l'instancier dans chaque test.
    }

    private function createParser(string $input): Parser
    {
        return new Parser(new Lexer($input));
    }

    public function testParseLiteral(): void
    {
        $parser = $this->createParser('/foo/');
        $ast = $parser->parse('/foo/');

        // "foo" est une SÉQUENCE de 3 littéraux
        $this->assertInstanceOf(SequenceNode::class, $ast);
        $this->assertCount(3, $ast->children);
        $this->assertInstanceOf(LiteralNode::class, $ast->children[0]);
        $this->assertSame('f', $ast->children[0]->value);
    }

    public function testParseGroupWithQuantifier(): void
    {
        $parser = $this->createParser('/(bar)?/');
        $ast = $parser->parse('/(bar)?/');

        $this->assertInstanceOf(QuantifierNode::class, $ast);
        $this->assertSame('?', $ast->quantifier);

        // Le nœud quantifié est un groupe
        $this->assertInstanceOf(GroupNode::class, $ast->node);
        $groupNode = $ast->node;

        // L'enfant du groupe est une séquence "bar"
        $this->assertInstanceOf(SequenceNode::class, $groupNode->child);
        $this->assertCount(3, $groupNode->child->children);
        $this->assertSame('b', $groupNode->child->children[0]->value);
    }

    public function testParseAlternation(): void
    {
        $parser = $this->createParser('/foo|bar/');
        $ast = $parser->parse('/foo|bar/');

        $this->assertInstanceOf(AlternationNode::class, $ast);
        $this->assertCount(2, $ast->alternatives);

        // La première alternative est une séquence "foo"
        $this->assertInstanceOf(SequenceNode::class, $ast->alternatives[0]);
        $this->assertCount(3, $ast->alternatives[0]->children);
        $this->assertSame('f', $ast->alternatives[0]->children[0]->value);

        // La seconde est une séquence "bar"
        $this->assertInstanceOf(SequenceNode::class, $ast->alternatives[1]);
        $this->assertCount(3, $ast->alternatives[1]->children);
        $this->assertSame('b', $ast->alternatives[1]->children[0]->value);
    }

    public function testParseOperatorPrecedence(): void
    {
        $parser = $this->createParser('/ab*c/');
        $ast = $parser->parse('/ab*c/');

        // L'AST doit être une Séquence
        $this->assertInstanceOf(SequenceNode::class, $ast);
        $this->assertCount(3, $ast->children);

        // Enfant 1: Literal 'a'
        $this->assertInstanceOf(LiteralNode::class, $ast->children[0]);
        $this->assertSame('a', $ast->children[0]->value);

        // Enfant 2: Quantifier '*'
        $this->assertInstanceOf(QuantifierNode::class, $ast->children[1]);
        $this->assertSame('*', $ast->children[1]->quantifier);

        // ... qui quantifie un Literal 'b'
        $this->assertInstanceOf(LiteralNode::class, $ast->children[1]->node);
        $this->assertSame('b', $ast->children[1]->node->value);

        // Enfant 3: Literal 'c'
        $this->assertInstanceOf(LiteralNode::class, $ast->children[2]);
        $this->assertSame('c', $ast->children[2]->value);
    }

    public function testThrowsOnUnmatchedGroup(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected )');
        $parser = $this->createParser('/(foo');
        $parser->parse('/(foo');
    }

    public function testThrowsOnMissingClosingDelimiter(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected closing delimiter');
        $parser = $this->createParser('/foo');
        $parser->parse('/foo');
    }

    public function testParseEscapedChars(): void
    {
        $parser = $this->createParser('/a\*b/');
        $ast = $parser->parse('/a\*b/');

        // Séquence de 3 : 'a', '*', 'b'
        $this->assertInstanceOf(SequenceNode::class, $ast);
        $this->assertCount(3, $ast->children);
        $this->assertInstanceOf(LiteralNode::class, $ast->children[1]);
        $this->assertSame('*', $ast->children[1]->value);
    }
}
