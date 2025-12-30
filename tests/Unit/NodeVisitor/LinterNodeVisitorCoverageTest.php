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

namespace RegexParser\Tests\Unit\NodeVisitor;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharLiteralType;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\ClassOperationNode;
use RegexParser\Node\ClassOperationType;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\NodeVisitor\LinterNodeVisitor;
use RegexParser\ReDoS\CharSetAnalyzer;

final class LinterNodeVisitorCoverageTest extends TestCase
{
    public function test_unicode_escape_out_of_range_adds_issue(): void
    {
        $linter = new LinterNodeVisitor();

        $node = new UnicodeNode('\\u{110000}', 0, 0);
        $node->accept($linter);

        $warnings = $linter->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Suspicious Unicode escape', $warnings[0]);
    }

    public function test_unicode_escape_hex_and_braced_paths_are_parsed(): void
    {
        $linter = new LinterNodeVisitor();

        (new UnicodeNode('\\x41', 0, 0))->accept($linter);
        (new UnicodeNode('\\u{0041}', 0, 0))->accept($linter);

        $this->assertSame([], $linter->getWarnings());
    }

    public function test_octal_escape_out_of_range_adds_issue(): void
    {
        $linter = new LinterNodeVisitor();

        $node = new CharLiteralNode('\\777', 0x200, CharLiteralType::OCTAL, 0, 0);
        $node->accept($linter);

        $warnings = $linter->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Suspicious octal escape', $warnings[0]);
    }

    public function test_unicode_named_unknown_adds_issue(): void
    {
        if (!class_exists(\IntlChar::class)) {
            $this->markTestSkipped('IntlChar is not available.');
        }

        $linter = new LinterNodeVisitor();

        $node = new CharLiteralNode('\\N{NOT_A_NAME}', 0, CharLiteralType::UNICODE_NAMED, 0, 0);
        $node->accept($linter);

        $warnings = $linter->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Unknown Unicode character name', $warnings[0]);
    }

    public function test_count_capturing_groups_handles_conditionals(): void
    {
        $linter = new LinterNodeVisitor();
        $conditional = new ConditionalNode(
            new LiteralNode('a', 0, 0),
            new GroupNode(new LiteralNode('b', 0, 0), GroupType::T_GROUP_CAPTURING, null, null, 0, 0),
            new LiteralNode('c', 0, 0),
            0,
            0,
        );

        $regex = new RegexNode($conditional, '', '/', 0, 0);
        $regex->accept($linter);

        $this->assertSame([], $linter->getIssues());
    }

    public function test_is_consuming_recognizes_node_types(): void
    {
        $linter = new LinterNodeVisitor();
        $method = (new \ReflectionClass($linter))->getMethod('isConsuming');

        $this->assertTrue($method->invoke($linter, new CharClassNode(new LiteralNode('a', 0, 0), false, 0, 0)));
        $this->assertTrue($method->invoke($linter, new CharTypeNode('d', 0, 0)));
        $this->assertTrue($method->invoke($linter, new DotNode(0, 0)));
        $this->assertTrue($method->invoke($linter, new CharLiteralNode('\\x41', 0x41, CharLiteralType::UNICODE, 0, 0)));
        $this->assertTrue($method->invoke($linter, new UnicodePropNode('L', true, 0, 0)));
        $this->assertTrue($method->invoke($linter, new PosixClassNode('alpha', 0, 0)));
        $this->assertTrue($method->invoke($linter, new QuantifierNode(new LiteralNode('a', 0, 0), '+', QuantifierType::T_GREEDY, 0, 0)));
        $this->assertFalse($method->invoke($linter, new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_LOOKAHEAD_POSITIVE, null, null, 0, 0)));

        $alternation = new AlternationNode([
            new AnchorNode('^', 0, 0),
            new LiteralNode('a', 0, 0),
        ], 0, 0);
        $this->assertTrue($method->invoke($linter, $alternation));

        $nonConsumingAlt = new AlternationNode([
            new AnchorNode('$', 0, 0),
        ], 0, 0);
        $this->assertFalse($method->invoke($linter, $nonConsumingAlt));

        $sequence = new SequenceNode([
            new AnchorNode('^', 0, 0),
            new LiteralNode('b', 0, 0),
        ], 0, 0);
        $this->assertTrue($method->invoke($linter, $sequence));

        $nonConsumingSeq = new SequenceNode([
            new AnchorNode('^', 0, 0),
        ], 0, 0);
        $this->assertFalse($method->invoke($linter, $nonConsumingSeq));
    }

    public function test_char_class_part_has_letters_for_literal(): void
    {
        $linter = new LinterNodeVisitor();
        $method = (new \ReflectionClass($linter))->getMethod('charClassPartHasLetters');

        $this->assertTrue($method->invoke($linter, new LiteralNode('A', 0, 0)));
    }

    public function test_lint_alternation_skips_empty_literals(): void
    {
        $linter = new LinterNodeVisitor();
        $alternation = new AlternationNode([
            new LiteralNode('', 0, 0),
            new LiteralNode('a', 0, 0),
        ], 0, 0);

        $this->invokePrivate($linter, 'lintAlternation', [$alternation]);

        $this->assertIsArray($linter->getIssues());
    }

    public function test_lint_redundant_char_class_handles_ranges_and_literals(): void
    {
        $linter = new LinterNodeVisitor();

        $parts = [
            new LiteralNode('c', 0, 0),
            new RangeNode(new LiteralNode('a', 0, 0), new LiteralNode('f', 0, 0), 0, 0),
            new LiteralNode('b', 0, 0),
            new RangeNode(new LiteralNode('ab', 0, 0), new LiteralNode('z', 0, 0), 0, 0),
            new RangeNode(new LiteralNode('z', 0, 0), new LiteralNode('a', 0, 0), 0, 0),
        ];

        $charClass = new CharClassNode(new SequenceNode($parts, 0, 0), false, 0, 0);

        $this->invokePrivate($linter, 'lintRedundantCharClass', [$charClass]);

        $this->assertNotEmpty($linter->getIssues());
    }

    public function test_lint_redundant_char_class_returns_on_class_operation(): void
    {
        $linter = new LinterNodeVisitor();
        $operation = new ClassOperationNode(
            ClassOperationType::INTERSECTION,
            new LiteralNode('a', 0, 0),
            new LiteralNode('b', 0, 0),
            0,
            0,
        );
        $charClass = new CharClassNode($operation, false, 0, 0);

        $this->invokePrivate($linter, 'lintRedundantCharClass', [$charClass]);

        $this->assertSame([], $linter->getIssues());
    }

    public function test_collect_char_class_parts_sequence(): void
    {
        $linter = new LinterNodeVisitor();
        $sequence = new SequenceNode([new LiteralNode('a', 0, 0)], 0, 0);

        $parts = $this->invokePrivate($linter, 'collectCharClassParts', [$sequence]);

        $this->assertIsArray($parts);
        $this->assertCount(1, $parts);
    }

    public function test_lint_inline_flags_branches(): void
    {
        $linter = new LinterNodeVisitor();

        $emptyFlags = new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_NON_CAPTURING, null, null, 0, 0);
        $this->invokePrivate($linter, 'lintInlineFlags', [$emptyFlags]);

        $resetFlags = new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_NON_CAPTURING, null, '^im', 0, 0);
        $this->invokePrivate($linter, 'lintInlineFlags', [$resetFlags]);

        $unsetFlag = new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_NON_CAPTURING, null, '-i', 0, 0);
        $this->invokePrivate($linter, 'lintInlineFlags', [$unsetFlag]);
        $this->assertNotEmpty($linter->getIssues());
    }

    public function test_is_redundant_group_recurses_through_sequence(): void
    {
        $linter = new LinterNodeVisitor();
        $sequence = new SequenceNode([new LiteralNode('a', 0, 0)], 0, 0);

        $result = $this->invokePrivate($linter, 'isRedundantGroup', [$sequence]);

        $this->assertTrue($result);
    }

    public function test_find_nested_quantifier_handles_conditional_define_and_atomic_group(): void
    {
        $linter = new LinterNodeVisitor();
        $quant = new QuantifierNode(new LiteralNode('a', 0, 0), '+', QuantifierType::T_GREEDY, 0, 0);

        $atomic = new GroupNode($quant, GroupType::T_GROUP_ATOMIC, null, null, 0, 0);
        $this->assertNull($this->invokePrivate($linter, 'findNestedQuantifier', [$atomic]));

        $conditional = new ConditionalNode(new LiteralNode('a', 0, 0), $quant, new LiteralNode('b', 0, 0), 0, 0);
        $this->assertSame($quant, $this->invokePrivate($linter, 'findNestedQuantifier', [$conditional]));

        $define = new DefineNode($quant, 0, 0);
        $this->assertSame($quant, $this->invokePrivate($linter, 'findNestedQuantifier', [$define]));
    }

    public function test_find_sequence_for_nested_quantifier_returns_null_for_non_sequence(): void
    {
        $linter = new LinterNodeVisitor();
        $quant = new QuantifierNode(new LiteralNode('a', 0, 0), '+', QuantifierType::T_GREEDY, 0, 0);

        $result = $this->invokePrivate($linter, 'findSequenceForNestedQuantifier', [new LiteralNode('a', 0, 0), $quant]);

        $this->assertNull($result);
    }

    public function test_unwrap_transparent_node_unwraps_group_and_sequence(): void
    {
        $linter = new LinterNodeVisitor();
        $inner = new LiteralNode('a', 0, 0);
        $group = new GroupNode($inner, GroupType::T_GROUP_NON_CAPTURING, null, null, 0, 0);
        $sequence = new SequenceNode([$group], 0, 0);

        $unwrapped = $this->invokePrivate($linter, 'unwrapTransparentNode', [$sequence]);

        $this->assertSame($inner, $unwrapped);
    }

    public function test_is_transparent_group_false_for_lookaround(): void
    {
        $linter = new LinterNodeVisitor();

        $result = $this->invokePrivate($linter, 'isTransparentGroup', [GroupType::T_GROUP_LOOKAHEAD_NEGATIVE]);

        $this->assertFalse($result);
    }

    public function test_is_exclusive_separator_returns_false_for_optional_or_unknown(): void
    {
        $linter = new LinterNodeVisitor();
        $innerBoundary = (new CharSetAnalyzer())->firstChars(new LiteralNode('a', 0, 0));

        $optionalSeparator = new LiteralNode('', 0, 0);
        $this->assertFalse($this->invokePrivate($linter, 'isExclusiveSeparator', [$optionalSeparator, $innerBoundary]));

        $unknownSeparator = new UnicodePropNode('L', true, 0, 0);
        $this->assertFalse($this->invokePrivate($linter, 'isExclusiveSeparator', [$unknownSeparator, $innerBoundary]));
    }

    public function test_is_optional_node_branches(): void
    {
        $linter = new LinterNodeVisitor();

        $quant = new QuantifierNode(new LiteralNode('a', 0, 0), '*', QuantifierType::T_GREEDY, 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'isOptionalNode', [$quant]));

        $transparentGroup = new GroupNode(new LiteralNode('', 0, 0), GroupType::T_GROUP_NON_CAPTURING, null, null, 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'isOptionalNode', [$transparentGroup]));

        $nonTransparentGroup = new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_LOOKAHEAD_POSITIVE, null, null, 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'isOptionalNode', [$nonTransparentGroup]));

        $sequence = new SequenceNode([new LiteralNode('', 0, 0)], 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'isOptionalNode', [$sequence]));

        $alternation = new AlternationNode([new LiteralNode('', 0, 0)], 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'isOptionalNode', [$alternation]));

        $conditional = new ConditionalNode(new LiteralNode('a', 0, 0), new LiteralNode('', 0, 0), new LiteralNode('b', 0, 0), 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'isOptionalNode', [$conditional]));

        $anchor = new AnchorNode('^', 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'isOptionalNode', [$anchor]));
    }

    public function test_is_optional_node_returns_false_for_non_optional_sequence(): void
    {
        $linter = new LinterNodeVisitor();
        $sequence = new SequenceNode([new LiteralNode('a', 0, 0), new LiteralNode('', 0, 0)], 0, 0);

        $this->assertFalse($this->invokePrivate($linter, 'isOptionalNode', [$sequence]));
    }

    public function test_is_optional_node_returns_false_for_non_optional_alternation(): void
    {
        $linter = new LinterNodeVisitor();
        $alternation = new AlternationNode([new LiteralNode('a', 0, 0), new LiteralNode('b', 0, 0)], 0, 0);

        $this->assertFalse($this->invokePrivate($linter, 'isOptionalNode', [$alternation]));
    }

    public function test_is_safely_separated_nested_quantifier_returns_false_for_unknown_boundary(): void
    {
        $linter = new LinterNodeVisitor();
        $ref = new \ReflectionClass($linter);
        $charSetAnalyzer = $ref->getProperty('charSetAnalyzer');
        $charSetAnalyzer->setValue($linter, new CharSetAnalyzer('u'));

        $nested = new QuantifierNode(new CharTypeNode('d', 0, 0), '+', QuantifierType::T_GREEDY, 0, 0);
        $sequence = new SequenceNode([$nested], 0, 0);
        $outer = new QuantifierNode($sequence, '+', QuantifierType::T_GREEDY, 0, 0);

        $result = $this->invokePrivate($linter, 'isSafelySeparatedNestedQuantifier', [$outer, $nested]);

        $this->assertFalse($result);
    }

    public function test_is_safely_separated_nested_quantifier_checks_next_neighbor(): void
    {
        $linter = new LinterNodeVisitor();
        $nested = new QuantifierNode(new LiteralNode('a', 0, 0), '+', QuantifierType::T_GREEDY, 0, 0);
        $sequence = new SequenceNode([$nested, new LiteralNode('b', 0, 0)], 0, 0);
        $outer = new QuantifierNode($sequence, '+', QuantifierType::T_GREEDY, 0, 0);

        $result = $this->invokePrivate($linter, 'isSafelySeparatedNestedQuantifier', [$outer, $nested]);

        $this->assertIsBool($result);
    }

    public function test_find_sequence_for_nested_quantifier_returns_null_when_not_found(): void
    {
        $linter = new LinterNodeVisitor();
        $nested = new QuantifierNode(new LiteralNode('a', 0, 0), '+', QuantifierType::T_GREEDY, 0, 0);
        $sequence = new SequenceNode([new LiteralNode('b', 0, 0)], 0, 0);

        $result = $this->invokePrivate($linter, 'findSequenceForNestedQuantifier', [$sequence, $nested]);

        $this->assertNull($result);
    }

    public function test_contains_dot_star_branches(): void
    {
        $linter = new LinterNodeVisitor();
        $dotStar = new QuantifierNode(new DotNode(0, 0), '*', QuantifierType::T_GREEDY, 0, 0);

        $sequence = new SequenceNode([$dotStar], 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'containsDotStar', [$sequence]));

        $alternation = new AlternationNode([$dotStar], 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'containsDotStar', [$alternation]));

        $conditional = new ConditionalNode(new LiteralNode('a', 0, 0), $dotStar, new LiteralNode('b', 0, 0), 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'containsDotStar', [$conditional]));

        $define = new DefineNode($dotStar, 0, 0);
        $this->assertTrue($this->invokePrivate($linter, 'containsDotStar', [$define]));
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivate(object $target, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionClass($target);
        $refMethod = $ref->getMethod($method);

        return $refMethod->invokeArgs($target, $args);
    }
}
