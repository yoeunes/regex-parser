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

namespace RegexParser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\NodeVisitor\CompilerNodeVisitor;
use RegexParser\NodeVisitor\ExplainVisitor;
use RegexParser\NodeVisitor\HtmlExplainVisitor;

class ExhaustiveVisitorTest extends TestCase
{
    public function test_compiler_special_literals(): void
    {
        $compiler = new CompilerNodeVisitor();

        // Special case: ']' is not escaped outside a char class
        $node = new LiteralNode(']', 0, 0);
        $this->assertSame(']', $node->accept($compiler));

        // Escaped characters outside char class
        $node = new LiteralNode('.', 0, 0);
        $this->assertSame('\.', $node->accept($compiler));
    }

    public function test_compiler_unicode_properties(): void
    {
        $compiler = new CompilerNodeVisitor();

        // \p{L} (short)
        $node = new UnicodePropNode('L', 0, 0);
        $this->assertSame('\pL', $node->accept($compiler));

        // \p{Lu} (long)
        $node = new UnicodePropNode('Lu', 0, 0);
        $this->assertSame('\p{Lu}', $node->accept($compiler));

        // \P{L} (negated)
        $node = new UnicodePropNode('^L', 0, 0);
        $this->assertSame('\p{^L}', $node->accept($compiler));
    }

    public function test_compiler_subroutines_syntax(): void
    {
        $compiler = new CompilerNodeVisitor();

        // (?&name)
        $node = new SubroutineNode('name', '&', 0, 0);
        $this->assertSame('(?&name)', $node->accept($compiler));

        // (?P>name)
        $node = new SubroutineNode('name', 'P>', 0, 0);
        $this->assertSame('(?P>name)', $node->accept($compiler));

        // \g<name>
        $node = new SubroutineNode('name', 'g', 0, 0);
        $this->assertSame('\g<name>', $node->accept($compiler));

        // (?1) default
        $node = new SubroutineNode('1', '', 0, 0);
        $this->assertSame('(?1)', $node->accept($compiler));
    }

    public function test_compiler_conditionals(): void
    {
        $compiler = new CompilerNodeVisitor();

        $condition = new LiteralNode('cond', 0, 0);
        $yes = new LiteralNode('yes', 0, 0);
        $no = new LiteralNode('no', 0, 0);

        // With Else
        $node = new ConditionalNode($condition, $yes, $no, 0, 0);
        $this->assertSame('(?(cond)yes|no)', $node->accept($compiler));

        // Without Else (empty string)
        $emptyNo = new LiteralNode('', 0, 0);
        $node = new ConditionalNode($condition, $yes, $emptyNo, 0, 0);
        $this->assertSame('(?(cond)yes)', $node->accept($compiler));
    }

    public function test_explain_all_char_types_and_assertions(): void
    {
        $explainer = new ExplainVisitor();
        $htmlExplainer = new HtmlExplainVisitor();

        // Char Types: d, D, s, S, w, W, h, H, v, V, R
        $types = ['d', 'D', 's', 'S', 'w', 'W', 'h', 'H', 'v', 'V', 'R'];
        foreach ($types as $type) {
            $node = new CharTypeNode($type, 0, 0);
            $this->assertNotEmpty($node->accept($explainer));
            $this->assertNotEmpty($node->accept($htmlExplainer));
        }

        // Unknown Char Type
        $node = new CharTypeNode('?', 0, 0);
        $this->assertStringContainsString('unknown', $node->accept($explainer));

        // Assertions: A, z, Z, G, b, B
        $assertions = ['A', 'z', 'Z', 'G', 'b', 'B'];
        foreach ($assertions as $val) {
            $node = new AssertionNode($val, 0, 0);
            $this->assertNotEmpty($node->accept($explainer));
            $this->assertNotEmpty($node->accept($htmlExplainer));
        }

        // Unknown Assertion
        $node = new AssertionNode('?', 0, 0);
        $this->assertStringContainsString('\?', $node->accept($explainer));
    }

    public function test_explain_group_types(): void
    {
        $explainer = new ExplainVisitor();

        // Lookbehind Positive
        $node = new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_LOOKBEHIND_POSITIVE);
        $this->assertStringContainsString('Positive Lookbehind', $node->accept($explainer));

        // Lookbehind Negative
        $node = new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_LOOKBEHIND_NEGATIVE);
        $this->assertStringContainsString('Negative Lookbehind', $node->accept($explainer));

        // Atomic
        $node = new GroupNode(new LiteralNode('a', 0, 0), GroupType::T_GROUP_ATOMIC);
        $this->assertStringContainsString('Atomic', $node->accept($explainer));
    }

    public function test_explain_quantifiers(): void
    {
        $explainer = new ExplainVisitor();
        $node = new LiteralNode('a', 0, 0);

        // Range {1,3}
        $q = new QuantifierNode($node, '{1,3}', QuantifierType::T_GREEDY, 0, 0);
        $this->assertStringContainsString('between 1 and 3', $q->accept($explainer));

        // At least {1,}
        $q = new QuantifierNode($node, '{1,}', QuantifierType::T_GREEDY, 0, 0);
        $this->assertStringContainsString('at least 1', $q->accept($explainer));

        // Exact {5}
        $q = new QuantifierNode($node, '{5}', QuantifierType::T_GREEDY, 0, 0);
        $this->assertStringContainsString('exactly 5', $q->accept($explainer));
    }
}
