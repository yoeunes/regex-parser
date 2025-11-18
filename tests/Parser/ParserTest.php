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

namespace RegexParser\Tests\Parser;

use PHPUnit\Framework\TestCase;
use RegexParser\Exception\ParserException;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\Parser;

class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function test_parse_returns_regex_node_with_flags(): void
    {
        $parser = $this->createParser();
        $ast = $parser->parse('/foo/imsU');

        $this->assertSame('imsU', $ast->flags);
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
    }

    public function test_parse_literal(): void
    {
        $parser = $this->createParser();
        $ast = $parser->parse('/foo/');
        $pattern = $ast->pattern;

        // "foo" is a SEQUENCE of 3 literals
        $this->assertInstanceOf(SequenceNode::class, $pattern);
        $this->assertCount(3, $pattern->children);
    }

    public function test_parse_char_class(): void
    {
        $parser = $this->createParser();

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

    public function test_parse_negated_char_class(): void
    {
        $parser = $this->createParser();

        $ast = $parser->parse('/[^a-z]/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(CharClassNode::class, $pattern);
        $this->assertTrue($pattern->isNegated);
        $this->assertCount(1, $pattern->parts);
        $this->assertInstanceOf(RangeNode::class, $pattern->parts[0]);
    }

    public function test_parse_group_with_quantifier(): void
    {
        $parser = $this->createParser();

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

    public function test_parse_alternation(): void
    {
        $parser = $this->createParser();

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

    public function test_parse_operator_precedence(): void
    {
        $parser = $this->createParser();

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

    public function test_parse_char_types_and_dot(): void
    {
        $parser = $this->createParser();

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

    public function test_parse_anchors(): void
    {
        $parser = $this->createParser();

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

    public function test_parse_assertions(): void
    {
        $parser = $this->createParser();

        $ast = $parser->parse('/\Afoo\b/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(SequenceNode::class, $pattern);
        $this->assertCount(5, $pattern->children); // \A, f, o, o, \b

        $this->assertInstanceOf(AssertionNode::class, $pattern->children[0]);
        $this->assertSame('A', $pattern->children[0]->value);

        $this->assertInstanceOf(AssertionNode::class, $pattern->children[4]);
        $this->assertSame('b', $pattern->children[4]->value);
    }

    public function test_parse_unicode_prop(): void
    {
        $parser = $this->createParser();

        $ast = $parser->parse('/\p{L}/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(UnicodePropNode::class, $pattern);
        $this->assertSame('L', $pattern->prop);
    }

    public function test_parse_comment(): void
    {
        $parser = $this->createParser();

        $ast = $parser->parse('/(?#test)/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(CommentNode::class, $pattern);
        $this->assertSame('test', $pattern->comment);
    }

    public function test_parse_conditional(): void
    {
        $parser = $this->createParser();

        $ast = $parser->parse('/(?(1)a|b)/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(ConditionalNode::class, $pattern);
        $this->assertInstanceOf(BackrefNode::class, $pattern->condition);
        $this->assertSame('1', $pattern->condition->ref);

        // The "yes" branch is the alternation "a|b"
        $this->assertInstanceOf(AlternationNode::class, $pattern->yes);
        $this->assertCount(2, $pattern->yes->alternatives);
        $this->assertInstanceOf(LiteralNode::class, $pattern->yes->alternatives[0]);
        $this->assertSame('a', $pattern->yes->alternatives[0]->value);
        $this->assertInstanceOf(LiteralNode::class, $pattern->yes->alternatives[1]);
        $this->assertSame('b', $pattern->yes->alternatives[1]->value);

        // The "no" branch is empty
        $this->assertInstanceOf(LiteralNode::class, $pattern->no);
        $this->assertSame('', $pattern->no->value);
    }

    public function test_throws_on_unmatched_group(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected )');
        $parser = $this->createParser();
        $parser->parse('/(foo/');
    }

    public function test_throws_on_missing_closing_delimiter(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter "/" found.');
        $parser = $this->createParser();
        $parser->parse('/foo');
    }

    public function test_parse_escaped_chars(): void
    {
        $parser = $this->createParser();

        $ast = $parser->parse('/a\*b/');
        $pattern = $ast->pattern;

        // Sequence of 3 : 'a', '*', 'b'
        $this->assertInstanceOf(SequenceNode::class, $pattern);
        $this->assertCount(3, $pattern->children);

        $childStar = $pattern->children[1];
        $this->assertInstanceOf(LiteralNode::class, $childStar);
        $this->assertSame('*', $childStar->value);
    }

    public function test_parse_inline_flags(): void
    {
        $parser = $this->createParser();

        $ast = $parser->parse('/(?i:foo)/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(GroupNode::class, $pattern);
        $this->assertSame(GroupType::T_GROUP_INLINE_FLAGS, $pattern->type);
        $this->assertSame('i', $pattern->flags);
    }

    public function test_parse_named_group_with_single_quote(): void
    {
        $ast = $this->parser->parse("/(?P'name'a)/");
        $this->assertInstanceOf(GroupNode::class, $ast->pattern);
        $this->assertSame(GroupType::T_GROUP_NAMED, $ast->pattern->type);
        $this->assertSame('name', $ast->pattern->name);
    }

    public function test_parse_named_group_with_double_quote(): void
    {
        $ast = $this->parser->parse('/(?P"name"a)/');
        $this->assertInstanceOf(GroupNode::class, $ast->pattern);
        $this->assertSame(GroupType::T_GROUP_NAMED, $ast->pattern->type);
        $this->assertSame('name', $ast->pattern->name);
    }

    public function test_parse_g_references_as_backref(): void
    {
        $ast = $this->parser->parse('/a\g{1}b\g{-1}c/');
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
        $this->assertInstanceOf(BackrefNode::class, $ast->pattern->children[1]);
        $this->assertInstanceOf(BackrefNode::class, $ast->pattern->children[3]);
        $this->assertSame('\g{1}', $ast->pattern->children[1]->ref);
        $this->assertSame('\g{-1}', $ast->pattern->children[3]->ref);
    }

    public function test_parse_g_references_as_subroutine(): void
    {
        $ast = $this->parser->parse('/(a)\g<name>/');
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern->children[1]);
        $this->assertSame('name', $ast->pattern->children[1]->reference);
        $this->assertSame('g', $ast->pattern->children[1]->syntax);
    }

    public function test_parse_conditional_with_group_ref(): void
    {
        // (?(1)a|b)
        $ast = $this->parser->parse('/(?(1)a|b)/');
        $this->assertInstanceOf(ConditionalNode::class, $ast->pattern);
        $this->assertInstanceOf(BackrefNode::class, $ast->pattern->condition);
        $this->assertSame('1', $ast->pattern->condition->ref);
    }

    public function test_parse_conditional_with_named_group_ref(): void
    {
        // (?(<name>)a|b)
        $ast = $this->parser->parse('/(?<name>x)(?(<name>)a|b)/');
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
        $conditional = $ast->pattern->children[1];
        $this->assertInstanceOf(ConditionalNode::class, $conditional);
        $this->assertInstanceOf(BackrefNode::class, $conditional->condition);
        $this->assertSame('name', $conditional->condition->ref);
    }

    private function createParser(): Parser
    {
        return new Parser();
    }
}
