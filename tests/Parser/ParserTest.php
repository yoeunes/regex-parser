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

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
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
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\Regex;

final class ParserTest extends TestCase
{
    private Regex $regex;

    protected function setUp(): void
    {
        $this->regex = Regex::create();
    }

    public function test_parse_returns_regex_node_with_flags(): void
    {
        $ast = $this->parse('/foo/imsU');

        $this->assertSame('imsU', $ast->flags);
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
    }

    public function test_parse_literal(): void
    {
        $ast = $this->parse('/foo/');
        $pattern = $ast->pattern;

        // "foo" is a SEQUENCE of 3 literals
        $this->assertInstanceOf(SequenceNode::class, $pattern);
        $this->assertCount(3, $pattern->children);
    }

    public function test_parse_char_class(): void
    {
        $ast = $this->parse('/[a-z\d-]/');
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
        $ast = $this->parse('/[^a-z]/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(CharClassNode::class, $pattern);
        $this->assertTrue($pattern->isNegated);
        $this->assertCount(1, $pattern->parts);
        $this->assertInstanceOf(RangeNode::class, $pattern->parts[0]);
    }

    public function test_parse_group_with_quantifier(): void
    {
        $ast = $this->parse('/(bar)?/');
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
        $ast = $this->parse('/foo|bar/');
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
        $ast = $this->parse('/ab*c/');
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
        $ast = $this->parse('/.\d\S/');
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
        $ast = $this->parse('/^foo$/');
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
        $ast = $this->parse('/\Afoo\b/');
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
        $ast = $this->parse('/\p{L}/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(UnicodePropNode::class, $pattern);
        $this->assertSame('L', $pattern->prop);
    }

    public function test_parse_comment(): void
    {
        $ast = $this->parse('/(?#test)/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(CommentNode::class, $pattern);
        $this->assertSame('test', $pattern->comment);
    }

    public function test_parse_conditional(): void
    {
        $ast = $this->parse('/(?(1)a|b)/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(ConditionalNode::class, $pattern);
        $this->assertInstanceOf(BackrefNode::class, $pattern->condition);
        $this->assertSame('1', $pattern->condition->ref);

        // The "yes" and "no" branches are split properly
        $this->assertInstanceOf(LiteralNode::class, $pattern->yes);
        $this->assertSame('a', $pattern->yes->value);
        $this->assertInstanceOf(LiteralNode::class, $pattern->no);
        $this->assertSame('b', $pattern->no->value);
    }

    public function test_throws_on_unmatched_group(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Expected )');
        $this->parse('/(foo/');
    }

    public function test_throws_on_missing_closing_delimiter(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('No closing delimiter "/" found.');
        $this->parse('/foo');
    }

    public function test_parse_escaped_chars(): void
    {
        $ast = $this->parse('/a\*b/');
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
        $ast = $this->parse('/(?i:foo)/');
        $pattern = $ast->pattern;

        $this->assertInstanceOf(GroupNode::class, $pattern);
        $this->assertSame(GroupType::T_GROUP_INLINE_FLAGS, $pattern->type);
        $this->assertSame('i', $pattern->flags);
    }

    public function test_parse_named_group_with_single_quote(): void
    {
        $ast = $this->parse("/(?P'name'a)/");
        $this->assertInstanceOf(GroupNode::class, $ast->pattern);
        $this->assertSame(GroupType::T_GROUP_NAMED, $ast->pattern->type);
        $this->assertSame('name', $ast->pattern->name);
    }

    public function test_parse_named_group_with_double_quote(): void
    {
        $ast = $this->parse('/(?P"name"a)/');
        $this->assertInstanceOf(GroupNode::class, $ast->pattern);
        $this->assertSame(GroupType::T_GROUP_NAMED, $ast->pattern->type);
        $this->assertSame('name', $ast->pattern->name);
    }

    public function test_parse_g_references_as_backref(): void
    {
        $ast = $this->parse('/a\g{1}b\g{-1}c/');
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
        $this->assertInstanceOf(BackrefNode::class, $ast->pattern->children[1]);
        $this->assertInstanceOf(BackrefNode::class, $ast->pattern->children[3]);
        $this->assertSame('\g{1}', $ast->pattern->children[1]->ref);
        $this->assertSame('\g{-1}', $ast->pattern->children[3]->ref);
    }

    public function test_parse_g_references_as_subroutine(): void
    {
        $ast = $this->parse('/(a)\g<name>/');
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
        $this->assertInstanceOf(SubroutineNode::class, $ast->pattern->children[1]);
        $this->assertSame('name', $ast->pattern->children[1]->reference);
        $this->assertSame('g', $ast->pattern->children[1]->syntax);
    }

    public function test_parse_conditional_with_group_ref(): void
    {
        // (?(1)a|b)
        $ast = $this->parse('/(?(1)a|b)/');
        $this->assertInstanceOf(ConditionalNode::class, $ast->pattern);
        $this->assertInstanceOf(BackrefNode::class, $ast->pattern->condition);
        $this->assertSame('1', $ast->pattern->condition->ref);
        $this->assertInstanceOf(LiteralNode::class, $ast->pattern->yes);
        $this->assertSame('a', $ast->pattern->yes->value);
        $this->assertInstanceOf(LiteralNode::class, $ast->pattern->no);
        $this->assertSame('b', $ast->pattern->no->value);
    }

    public function test_parse_conditional_with_multiple_else_alternatives(): void
    {
        $ast = $this->parse('/(?(1)a|b|c)/');

        $this->assertInstanceOf(ConditionalNode::class, $ast->pattern);
        $this->assertInstanceOf(LiteralNode::class, $ast->pattern->yes);
        $this->assertSame('a', $ast->pattern->yes->value);

        $this->assertInstanceOf(AlternationNode::class, $ast->pattern->no);
        $this->assertCount(2, $ast->pattern->no->alternatives);
        $this->assertInstanceOf(LiteralNode::class, $ast->pattern->no->alternatives[0]);
        $this->assertInstanceOf(LiteralNode::class, $ast->pattern->no->alternatives[1]);
        $this->assertSame('b', $ast->pattern->no->alternatives[0]->value);
        $this->assertSame('c', $ast->pattern->no->alternatives[1]->value);
    }

    public function test_parse_conditional_without_else_defaults_to_empty_literal(): void
    {
        $ast = $this->parse('/(?(1)a)/');

        $this->assertInstanceOf(ConditionalNode::class, $ast->pattern);
        $this->assertInstanceOf(LiteralNode::class, $ast->pattern->yes);
        $this->assertSame('a', $ast->pattern->yes->value);

        $this->assertInstanceOf(LiteralNode::class, $ast->pattern->no);
        $this->assertSame('', $ast->pattern->no->value);
    }

    public function test_parse_conditional_with_named_group_ref(): void
    {
        // (?(<name>)a|b)
        $ast = $this->parse('/(?<name>x)(?(<name>)a|b)/');
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
        $conditional = $ast->pattern->children[1];
        $this->assertInstanceOf(ConditionalNode::class, $conditional);
        $this->assertInstanceOf(BackrefNode::class, $conditional->condition);
        $this->assertSame('name', $conditional->condition->ref);
    }

    public function test_parse_conditional_lookaround_tracks_branch_and_offsets(): void
    {
        $pattern = '(?(?=a)b|c)';
        $ast = $this->parse('/'.$pattern.'/');

        $this->assertInstanceOf(ConditionalNode::class, $ast->pattern);
        /** @var ConditionalNode $conditional */
        $conditional = $ast->pattern;
        $condition = $conditional->condition;
        $this->assertInstanceOf(GroupNode::class, $condition);
        $this->assertSame('(?=', substr($pattern, $condition->getStartPosition(), 3));
        $this->assertSame('(?=a)', substr($pattern, $condition->getStartPosition(), $condition->getEndPosition() - $condition->getStartPosition()));

        $this->assertInstanceOf(LiteralNode::class, $conditional->yes);
        $this->assertSame('b', $conditional->yes->value);
        $this->assertInstanceOf(LiteralNode::class, $conditional->no);
        $this->assertSame('c', $conditional->no->value);
    }

    public function test_parse_conditional_with_recursion_condition_variants(): void
    {
        $ast = $this->parse('/(?(R)a|b)/');
        $this->assertInstanceOf(ConditionalNode::class, $ast->pattern);
        /** @var ConditionalNode $cond */
        $cond = $ast->pattern;
        $this->assertInstanceOf(SubroutineNode::class, $cond->condition);
        $this->assertSame('R', $cond->condition->reference);

        $relative = $this->parse('/(?(R-1)a|b)/');
        $this->assertInstanceOf(ConditionalNode::class, $relative->pattern);
        /** @var ConditionalNode $relativeCond */
        $relativeCond = $relative->pattern;
        $this->assertInstanceOf(SubroutineNode::class, $relativeCond->condition);
        $this->assertSame('R-1', $relativeCond->condition->reference);
    }

    public function test_parse_conditional_with_bare_name_condition(): void
    {
        $ast = $this->parse('/(?<name>x)(?(name)a|b)/');
        $this->assertInstanceOf(SequenceNode::class, $ast->pattern);
        $conditional = $ast->pattern->children[1];
        $this->assertInstanceOf(ConditionalNode::class, $conditional);
        $this->assertInstanceOf(BackrefNode::class, $conditional->condition);
        $this->assertSame('name', $conditional->condition->ref);
    }

    public function test_parse_python_backreference_is_rejected(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Backreferences (?P=name) are not supported yet.');

        $this->parse('/(?P=name)a/');
    }

    public function test_python_named_group_syntax(): void
    {
        // (?P<name>...)
        $ast = $this->parse('/(?P<foo>a)/');
        $this->assertInstanceOf(GroupNode::class, $ast->pattern);
        $this->assertSame('foo', $ast->pattern->name);

        // (?P'name'...)
        $ast = $this->parse("/(?P'bar'a)/");
        $this->assertInstanceOf(GroupNode::class, $ast->pattern);
        $this->assertSame('bar', $ast->pattern->name);

        // (?P"name"...)
        $ast = $this->parse('/(?P"baz"a)/');
        $this->assertInstanceOf(GroupNode::class, $ast->pattern);
        $this->assertSame('baz', $ast->pattern->name);
    }

    public function test_python_named_group_invalid_syntax(): void
    {
        $this->expectException(ParserException::class);
        // Missing name quotes or brackets
        $this->parse('/(?P=foo)/'); // Backref syntax, not currently supported in parser logic for groups
    }

    public function test_max_pattern_length(): void
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Regex pattern exceeds maximum length');

        // maxPatternLength is now enforced by the Regex class
        $regex = Regex::create(['max_pattern_length' => 5]);
        $regex->parse('/toolong/');
    }

    public function test_invalid_range_codepoints_are_parsed_but_invalid(): void
    {
        // The parser itself allows [z-a], checking semantics is done by the Validator.
        // So we assert that it parses into a RangeNode successfully.
        $ast = $this->parse('/[z-a]/');

        $this->assertInstanceOf(CharClassNode::class, $ast->pattern);
        $this->assertNotEmpty($ast->pattern->parts);
        $this->assertInstanceOf(RangeNode::class, $ast->pattern->parts[0]);
    }

    #[DoesNotPerformAssertions]
    public function test_parse_conditional_define(): void
    {
        // (?(DEFINE)...)
        $this->parse('/(?(DEFINE)(?<A>a))(?&A)/');
    }

    /**
     * Helper method to parse a regex string using the decoupled Lexer and Parser.
     */
    private function parse(string $regex): RegexNode
    {
        return $this->regex->parse($regex);
    }
}
