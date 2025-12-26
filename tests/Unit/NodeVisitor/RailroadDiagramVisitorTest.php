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
use RegexParser\Node\GroupNode;
use RegexParser\Node\GroupType;
use RegexParser\Node\AlternationNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\QuantifierType;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharLiteralNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\KeepNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\ClassOperationNode;
use RegexParser\Node\ClassOperationType;
use RegexParser\Node\ControlCharNode;
use RegexParser\Node\ScriptRunNode;
use RegexParser\Node\VersionConditionNode;
use RegexParser\Node\UnicodePropNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\LimitMatchNode;
use RegexParser\Node\CalloutNode;
use RegexParser\NodeVisitor\RailroadDiagramVisitor;
use RegexParser\Regex;

final class RailroadDiagramVisitorTest extends TestCase
{
    public function test_diagram_renders_basic_tree(): void
    {
        $ast = Regex::create()->parse('/^a+$/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $expected = <<<'TEXT'
            Regex
            \-- Sequence
                |-- Anchor (^)
                |-- Quantifier (+, greedy)
                |   \-- Literal ('a')
                \-- Anchor ($)
            TEXT;

        $this->assertSame($expected, $diagram);
    }

    public function test_diagram_with_flags(): void
    {
        $ast = Regex::create()->parse('/^a+$/im');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Regex (flags: im)', $diagram);
        $this->assertStringContainsString('Anchor (^)', $diagram);
    }

    public function test_diagram_with_alternation(): void
    {
        $ast = Regex::create()->parse('/a|b|c/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Alternation', $diagram);
        $this->assertStringContainsString('Literal', $diagram);
    }

    public function test_diagram_with_named_group(): void
    {
        $ast = Regex::create()->parse('/(?<name>abc)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Group (named) name="name"', $diagram);
    }

    public function test_diagram_with_inline_flags_group(): void
    {
        $ast = Regex::create()->parse('/(?im:abc)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Group (inline flags) flags="im"', $diagram);
    }

    public function test_diagram_with_lookahead(): void
    {
        $ast = Regex::create()->parse('/a(?=b)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Group (positive lookahead)', $diagram);
    }

    public function test_diagram_with_negative_lookahead(): void
    {
        $ast = Regex::create()->parse('/a(?!b)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Group (negative lookahead)', $diagram);
    }

    public function test_diagram_with_lookbehind(): void
    {
        $ast = Regex::create()->parse('/(?<=a)b/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Group (positive lookbehind)', $diagram);
    }

    public function test_diagram_with_negative_lookbehind(): void
    {
        $ast = Regex::create()->parse('/(?<!a)b/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Group (negative lookbehind)', $diagram);
    }

    public function test_diagram_with_atomic_group(): void
    {
        $ast = Regex::create()->parse('/(?>abc)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Group (atomic)', $diagram);
    }

    public function test_diagram_with_branch_reset(): void
    {
        $ast = Regex::create()->parse('/(?|a|b)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Group (branch reset)', $diagram);
    }

    public function test_diagram_with_char_class(): void
    {
        $ast = Regex::create()->parse('/[abc]/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('CharClass', $diagram);
    }

    public function test_diagram_with_negated_char_class(): void
    {
        $ast = Regex::create()->parse('/[^abc]/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('CharClass (negated)', $diagram);
    }

    public function test_diagram_with_range(): void
    {
        $ast = Regex::create()->parse('/[a-z]/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Range', $diagram);
    }

    public function test_diagram_with_backref(): void
    {
        $ast = Regex::create()->parse('/(a)\1/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Backref (\\1)', $diagram);
    }

    public function test_diagram_with_class_operation(): void
    {
        $ast = Regex::create()->parse('/[a&&[b-z]]/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('ClassOperation (intersection)', $diagram);
    }

    public function test_diagram_with_control_char(): void
    {
        $ast = Regex::create()->parse('/\\cM/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('ControlChar (\\cM)', $diagram);
    }

    public function test_diagram_with_script_run(): void
    {
        $ast = Regex::create()->parse('/(*script_run:abc)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('ScriptRun (abc)', $diagram);
    }

    public function test_diagram_with_version_condition(): void
    {
        $ast = Regex::create()->parse('/(1.0)a(*LIMIT_MATCH=x)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('VersionCondition (>= 1.0)', $diagram);
    }

    public function test_diagram_with_unicode_prop(): void
    {
        $ast = Regex::create()->parse('/\\p{Letter}/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('UnicodeProperty (\\p{Letter})', $diagram);
    }

    public function test_diagram_with_posix_class(): void
    {
        $ast = Regex::create()->parse('/[:alpha:]/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('PosixClass ([:alpha:])', $diagram);
    }

    public function test_diagram_with_comment(): void
    {
        $ast = Regex::create()->parse('/(?#comment)a/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Comment', $diagram);
    }

    public function test_diagram_with_conditional(): void
    {
        $ast = Regex::create()->parse('/(?(a)x|y)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Conditional', $diagram);
    }

    public function test_diagram_with_subroutine(): void
    {
        $ast = Regex::create()->parse('/(?(1)a)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Subroutine (1)', $diagram);
    }

    public function test_diagram_with_pcre_verb(): void
    {
        $ast = Regex::create()->parse('/(*PRUNE)a/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('PCREVerb (*PRUNE)', $diagram);
    }

    public function test_diagram_with_define(): void
    {
        $ast = Regex::create()->parse('/(?(DEFINE)(?P<name>abc))/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Define', $diagram);
    }

    public function test_diagram_with_limit_match(): void
    {
        $ast = Regex::create()->parse('/(*LIMIT_MATCH=100)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('LimitMatch (*LIMIT_MATCH=100)', $diagram);
    }

    public function test_diagram_with_callout_no_identifier(): void
    {
        $ast = Regex::create()->parse('/(?C)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Callout (?C)', $diagram);
    }

    public function test_diagram_with_callout_string_identifier(): void
    {
        $ast = Regex::create()->parse('/(?C"test")/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Callout (?C="test")', $diagram);
    }

    public function test_diagram_with_callout_numeric_identifier(): void
    {
        $ast = Regex::create()->parse('/(?C123)/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Callout (?C123)', $diagram);
    }

    public function test_diagram_with_keep(): void
    {
        $ast = Regex::create()->parse('/a\\Kb/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Keep (\\K)', $diagram);
    }

    public function test_diagram_with_assertion(): void
    {
        $ast = Regex::create()->parse('/\\bword\\b/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Assertion (\\b)', $diagram);
    }

    public function test_diagram_with_unicode_escape(): void
    {
        $ast = Regex::create()->parse('/\\x20AC/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Unicode (\\x20AC)', $diagram);
    }

    public function test_diagram_with_dot(): void
    {
        $ast = Regex::create()->parse('/a.b/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Dot (.)', $diagram);
    }

    public function test_diagram_with_quantifiers(): void
    {
        $ast = Regex::create()->parse('/a* b+ c? d{3} e{2,5} f{2,}/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Quantifier (*, greedy)', $diagram);
        $this->assertStringContainsString('Quantifier (+, greedy)', $diagram);
        $this->assertStringContainsString('Quantifier (?, greedy)', $diagram);
        $this->assertStringContainsString('Quantifier ({3}, greedy)', $diagram);
        $this->assertStringContainsString('Quantifier ({2,5}, greedy)', $diagram);
        $this->assertStringContainsString('Quantifier ({2,}, greedy)', $diagram);
    }

    public function test_diagram_with_lazy_quantifiers(): void
    {
        $ast = Regex::create()->parse('/a*? b+?/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Quantifier (*, lazy)', $diagram);
        $this->assertStringContainsString('Quantifier (+, lazy)', $diagram);
    }

    public function test_diagram_with_possessive_quantifiers(): void
    {
        $ast = Regex::create()->parse('/a*+ b++/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Quantifier (*, possessive)', $diagram);
        $this->assertStringContainsString('Quantifier (+, possessive)', $diagram);
    }

    public function test_diagram_complex_nested_structure(): void
    {
        $ast = Regex::create()->parse('/^(?:a|(?:b|c))+$/');
        $diagram = $ast->accept(new RailroadDiagramVisitor());

        $this->assertStringContainsString('Regex', $diagram);
        $this->assertStringContainsString('Anchor (^)', $diagram);
        $this->assertStringContainsString('Group (non-capturing)', $diagram);
        $this->assertStringContainsString('Alternation', $diagram);
        $this->assertStringContainsString('Anchor ($)', $diagram);
    }
}
