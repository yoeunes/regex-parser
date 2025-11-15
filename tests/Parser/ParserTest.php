<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Tests\Parser;

use PHPUnit\Framework\TestCase;
use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\AnchorNode;
use RegexParser\Ast\CharClassNode;
use RegexParser\Ast\CharTypeNode;
use RegexParser\Ast\DotNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\RangeNode;
use RegexParser\Ast\RegexNode;
use RegexParser\Ast\SequenceNode;
use RegexParser\Exception\ParserException;
use RegexParser\Parser\Parser;

class ParserTest extends TestCase
{
    private function createParser(): Parser
    {
        return new Parser();
    }

    public function testParseReturnsRegexNodeWithFlags(): void
    {
        $parser = $this->createParser();
        $ast = $parser->parse('/foo/imsU');

        $this->assertInstanceOf(RegexNode::class, $ast);
        $this->assertSame('imsU', $ast->flags);
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
    }

    public function testParseLiteral(): void
    {
        $parser = $this->createParser();
        /** @var RegexNode $ast */
        $ast = $parser->parse('/foo/');
        $pattern = $ast->pattern;

        // "foo" is a SEQUENCE of 3 literals
        $this->assertInstanceOf(SequenceNode::class, $pattern);
        $this->assertCount(3, $pattern->children);
    }

    public function testParseCharClass(): void
    {
        $parser = $this->createParser();
        /** @var RegexNode $ast */
        $ast = $parser->parse('/[a-z\d-]/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(CharClassNode::class, $pattern);
        $this->assertFalse($pattern->isNegated);
        $this->assertCount(3, $pattern->parts);

        // 1. RangeNode
        $this->assertInstanceOf(RangeNode::class, $pattern->parts[0]);
        $this->assertInstanceOf(LiteralNode::class, $pattern->parts[0]->start);
        $this->assertSame('a', $pattern->parts[0]->start->value);
        $this->assertInstanceOf(LiteralNode::class, $pattern->parts[0]->end);
        $this->assertSame('z', $pattern->parts[0]->end->value);

        // 2. CharTypeNode
        $this->assertInstanceOf(CharTypeNode::class, $pattern->parts[1]);
        $this->assertSame('d', $pattern->parts[1]->value);

        // 3. LiteralNode
        $this->assertInstanceOf(LiteralNode::class, $pattern->parts[2]);
        $this->assertSame('-', $pattern->parts[2]->value);
    }

    public function testParseNegatedCharClass(): void
    {
        $parser = $this->createParser();
        /** @var RegexNode $ast */
        $ast = $parser->parse('/[^a-z]/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(CharClassNode::class, $pattern);
        $this->assertTrue($pattern->isNegated);
        $this->assertCount(1, $pattern->parts);
        $this->assertInstanceOf(RangeNode::class, $pattern->parts[0]);
    }

    public function testParseGroupWithQuantifier(): void
    {
        $parser = $this->createParser();
        /** @var RegexNode $ast */
        $ast = $parser->parse('/(bar)?/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(QuantifierNode::class, $pattern);
        $this->assertSame('?', $pattern->quantifier);

        // The quantified node is a group
        $this->assertInstanceOf(GroupNode::class, $pattern->node);
        $groupNode = $pattern->node;

        // The group's child is a sequence "bar"
        $this->assertInstanceOf(SequenceNode::class, $groupNode->child);
        $this->assertCount(3, $groupNode->child->children);

        $sequenceChild = $groupNode->child->children[0];
        $this->assertInstanceOf(LiteralNode::class, $sequenceChild);
        $this->assertSame('b', $sequenceChild->value);
    }

    public function testParseAlternation(): void
    {
        $parser = $this->createParser();
        /** @var RegexNode $ast */
        $ast = $parser->parse('/foo|bar/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(AlternationNode::class, $pattern);
        $this->assertCount(2, $pattern->alternatives);

        // Alt 1 is a sequence "foo"
        $this->assertInstanceOf(SequenceNode::class, $pattern->alternatives[0]);
        $this->assertCount(3, $pattern->alternatives[0]->children);

        $alt1Child = $pattern->alternatives[0]->children[0];
        $this->assertInstanceOf(LiteralNode::class, $alt1Child);
        $this->assertSame('f', $alt1Child->value);

        // Alt 2 is a sequence "bar"
        $this->assertInstanceOf(SequenceNode::class, $pattern->alternatives[1]);
        $this->assertCount(3, $pattern->alternatives[1]->children);

        $alt2Child = $pattern->alternatives[1]->children[0];
        $this->assertInstanceOf(LiteralNode::class, $alt2Child);
        $this->assertSame('b', $alt2Child->value);
    }

    public function testParseOperatorPrecedence(): void
    {
        $parser = $this->createParser();
        /** @var RegexNode $ast */
        $ast = $parser->parse('/ab*c/');
        $pattern = $ast->pattern;

        // The AST must be a Sequence
        $this->assertInstanceOf(SequenceNode::class, $pattern);
        $this->assertCount(3, $pattern->children);

        // Child 1: Literal 'a'
        $childA = $pattern->children[0];
        $this->assertInstanceOf(LiteralNode::class, $childA);
        $this->assertSame('a', $childA->value);

        // Child 2: Quantifier '*'
        $this->assertInstanceOf(QuantifierNode::class, $pattern->children[1]);
        $this->assertSame('*', $pattern->children[1]->quantifier);

        // ... which quantifies a Literal 'b'
        $quantifiedNode = $pattern->children[1]->node;
        $this->assertInstanceOf(LiteralNode::class, $quantifiedNode);
        $this->assertSame('b', $quantifiedNode->value);

        // Child 3: Literal 'c'
        $childC = $pattern->children[2];
        $this->assertInstanceOf(LiteralNode::class, $childC);
        $this->assertSame('c', $childC->value);
    }

    public function testParseCharTypesAndDot(): void
    {
        $parser = $this->createParser();
        /** @var RegexNode $ast */
        $ast = $parser->parse('/.\d\S/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(SequenceNode::class, $pattern);
        $this->assertCount(3, $pattern->children);

        $this->assertInstanceOf(DotNode::class, $pattern->children[0]);
        $this->assertInstanceOf(CharTypeNode::class, $pattern->children[1]);
        $this->assertSame('d', $pattern->children[1]->value);
        $this->assertInstanceOf(CharTypeNode::class, $pattern->children[2]);
        $this->assertSame('S', $pattern->children[2]->value);
    }

    public function testParseAnchors(): void
    {
        $parser = $this->createParser();
        /** @var RegexNode $ast */
        $ast = $parser->parse('/^foo$/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(SequenceNode::class, $pattern);
        $this->assertCount(5, $pattern->children); // ^, f, o, o, $

        $this->assertInstanceOf(AnchorNode::class, $pattern->children[0]);
        $this->assertSame('^', $pattern->children[0]->value);

        $this->assertInstanceOf(LiteralNode::class, $pattern->children[1]); // "f"
        $this->assertInstanceOf(LiteralNode::class, $pattern->children[2]); // "o"
        $this->assertInstanceOf(LiteralNode::class, $pattern->children[3]); // "o"

        $this->assertInstanceOf(AnchorNode::class, $pattern->children[4]);
        $this->assertSame('$', $pattern->children[4]->value);
    }

    public function testThrowsOnUnmatchedGroup(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected )');
        $parser = $this->createParser();
        $parser->parse('/(foo/');
    }

    public function testThrowsOnMissingClosingDelimiter(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter "/" found.');
        $parser = $this->createParser();
        $parser->parse('/foo');
    }

    public function testParseEscapedChars(): void
    {
        $parser = $this->createParser();
        /** @var RegexNode $ast */
        $ast = $parser->parse('/a\*b/');
        $pattern = $ast->pattern;

        // Sequence of 3 : 'a', '*', 'b'
        $this->assertInstanceOf(SequenceNode::class, $pattern);
        $this->assertCount(3, $pattern->children);

        $childStar = $pattern->children[1];
        $this->assertInstanceOf(LiteralNode::class, $childStar);
        $this->assertSame('*', $childStar->value);
    }
}
