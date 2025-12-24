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
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\RangeNode;
use RegexParser\NodeVisitor\ModernizerNodeVisitor;

final class ModernizerNodeVisitorTest extends TestCase
{
    private ModernizerNodeVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new ModernizerNodeVisitor();
    }

    public function test_modernizes_digit_range_to_char_type(): void
    {
        $range = new RangeNode(new LiteralNode('0', 0, 1), new LiteralNode('9', 2, 3), 0, 3);
        $charClass = new CharClassNode($range, false, 0, 4);

        $result = $charClass->accept($this->visitor);

        $this->assertInstanceOf(CharTypeNode::class, $result);
        $this->assertSame('d', $result->value);
    }

    public function test_removes_unnecessary_escaping(): void
    {
        $literal = new LiteralNode('\\@', 0, 2);

        $result = $literal->accept($this->visitor);

        $this->assertInstanceOf(LiteralNode::class, $result);
        /* @var LiteralNode $result */
        $this->assertSame('@', $result->value);
    }

    public function test_modernizes_numeric_backref(): void
    {
        $backref = new BackrefNode('1', 0, 2);

        $result = $backref->accept($this->visitor);

        $this->assertInstanceOf(BackrefNode::class, $result);
        /* @var BackrefNode $result */
        $this->assertSame('\g{1}', $result->ref);
    }

    public function test_preserves_named_backref(): void
    {
        $backref = new BackrefNode('name', 0, 6);

        $result = $backref->accept($this->visitor);

        $this->assertSame($backref, $result);
    }

    public function test_preserves_alternation(): void
    {
        $alternation = new \RegexParser\Node\AlternationNode(
            [new \RegexParser\Node\LiteralNode('a', 0, 1), new \RegexParser\Node\LiteralNode('b', 3, 4)],
            0, 5
        );

        $result = $alternation->accept($this->visitor);

        $this->assertInstanceOf(\RegexParser\Node\AlternationNode::class, $result);
    }

    public function test_preserves_sequence(): void
    {
        $sequence = new \RegexParser\Node\SequenceNode(
            [new \RegexParser\Node\LiteralNode('a', 0, 1), new \RegexParser\Node\LiteralNode('b', 2, 3)],
            0, 4
        );

        $result = $sequence->accept($this->visitor);

        $this->assertInstanceOf(\RegexParser\Node\SequenceNode::class, $result);
    }

    public function test_preserves_quantifier(): void
    {
        $quantifier = new \RegexParser\Node\QuantifierNode(
            new \RegexParser\Node\LiteralNode('a', 0, 1),
            '*',
            \RegexParser\Node\QuantifierType::T_QUANTIFIER_GREEDY,
            1,
            2
        );

        $result = $quantifier->accept($this->visitor);

        $this->assertInstanceOf(\RegexParser\Node\QuantifierNode::class, $result);
    }

    public function test_preserves_anchor(): void
    {
        $anchor = new \RegexParser\Node\AnchorNode('^', 0, 1);
        $result = $anchor->accept($this->visitor);

        $this->assertSame($anchor, $result);
    }

    public function test_preserves_assertion(): void
    {
        $assertion = new \RegexParser\Node\AssertionNode('b', 0, 2);
        $result = $assertion->accept($this->visitor);

        $this->assertSame($assertion, $result);
    }

    public function test_preserves_dot(): void
    {
        $dot = new \RegexParser\Node\DotNode(0, 1);
        $result = $dot->accept($this->visitor);

        $this->assertSame($dot, $result);
    }

    public function test_preserves_char_type(): void
    {
        $charType = new \RegexParser\Node\CharTypeNode('w', 0, 2);
        $result = $charType->accept($this->visitor);

        $this->assertSame($charType, $result);
    }

    public function test_preserves_range(): void
    {
        $range = new \RegexParser\Node\RangeNode(
            new \RegexParser\Node\LiteralNode('a', 0, 1),
            new \RegexParser\Node\LiteralNode('z', 3, 4),
            0, 5
        );
        $result = $range->accept($this->visitor);

        $this->assertSame($range, $result);
    }

    public function test_preserves_unicode_prop(): void
    {
        $unicodeProp = new \RegexParser\Node\UnicodePropNode('L', 0, 5);
        $result = $unicodeProp->accept($this->visitor);

        $this->assertSame($unicodeProp, $result);
    }

    public function test_preserves_char_literal(): void
    {
        $charLiteral = new \RegexParser\Node\CharLiteralNode('a', 0, 1);
        $result = $charLiteral->accept($this->visitor);

        $this->assertSame($charLiteral, $result);
    }

    public function test_preserves_posix_class(): void
    {
        $posixClass = new \RegexParser\Node\PosixClassNode('alpha', 0, 9);
        $result = $posixClass->accept($this->visitor);

        $this->assertSame($posixClass, $result);
    }

    public function test_preserves_comment(): void
    {
        $comment = new \RegexParser\Node\CommentNode('test comment', 0, 13);
        $result = $comment->accept($this->visitor);

        $this->assertSame($comment, $result);
    }

    public function test_preserves_conditional(): void
    {
        $conditional = new \RegexParser\Node\ConditionalNode(
            new \RegexParser\Node\LiteralNode('a', 1, 2),
            new \RegexParser\Node\LiteralNode('b', 4, 5),
            new \RegexParser\Node\LiteralNode('c', 7, 8),
            0, 10
        );
        $result = $conditional->accept($this->visitor);

        $this->assertInstanceOf(\RegexParser\Node\ConditionalNode::class, $result);
    }

    public function test_preserves_subroutine(): void
    {
        $subroutine = new \RegexParser\Node\SubroutineNode('1', 0, 4);
        $result = $subroutine->accept($this->visitor);

        $this->assertSame($subroutine, $result);
    }

    public function test_preserves_pcre_verb(): void
    {
        $pcreVerb = new \RegexParser\Node\PcreVerbNode('FAIL', 0, 6);
        $result = $pcreVerb->accept($this->visitor);

        $this->assertSame($pcreVerb, $result);
    }

    public function test_preserves_define(): void
    {
        $define = new \RegexParser\Node\DefineNode(
            new \RegexParser\Node\LiteralNode('test', 1, 5),
            0, 12
        );
        $result = $define->accept($this->visitor);

        $this->assertSame($define, $result);
    }

    public function test_preserves_limit_match(): void
    {
        $limitMatch = new \RegexParser\Node\LimitMatchNode(1000, 0, 17);
        $result = $limitMatch->accept($this->visitor);

        $this->assertSame($limitMatch, $result);
    }

    public function test_preserves_callout(): void
    {
        $callout = new \RegexParser\Node\CalloutNode(null, 0, 3);
        $result = $callout->accept($this->visitor);

        $this->assertSame($callout, $result);
    }

    public function test_preserves_script_run(): void
    {
        $scriptRun = new \RegexParser\Node\ScriptRunNode('Latin', 0, 20);
        $result = $scriptRun->accept($this->visitor);

        $this->assertSame($scriptRun, $result);
    }

    public function test_preserves_version_condition(): void
    {
        $versionCondition = new \RegexParser\Node\VersionConditionNode('>=', '10.0', 0, 15);
        $result = $versionCondition->accept($this->visitor);

        $this->assertSame($versionCondition, $result);
    }

    public function test_preserves_keep(): void
    {
        $keep = new \RegexParser\Node\KeepNode(0, 2);
        $result = $keep->accept($this->visitor);

        $this->assertSame($keep, $result);
    }

    public function test_preserves_control_char(): void
    {
        $controlChar = new \RegexParser\Node\ControlCharNode('n', 0, 3);
        $result = $controlChar->accept($this->visitor);

        $this->assertSame($controlChar, $result);
    }

    public function test_preserves_class_operation(): void
    {
        $classOperation = new \RegexParser\Node\ClassOperationNode(
            \RegexParser\Node\ClassOperationType::T_MINUS,
            0, 6
        );
        $result = $classOperation->accept($this->visitor);

        $this->assertSame($classOperation, $result);
    }

    public function test_modernizes_whitespace_char_class(): void
    {
        $whitespaceClass = new CharClassNode(
            new \RegexParser\Node\AlternationNode(
                [
                    new \RegexParser\Node\LiteralNode("\t", 1, 4),
                    new \RegexParser\Node\LiteralNode("\n", 5, 8),
                    new \RegexParser\Node\LiteralNode("\r", 9, 12),
                    new \RegexParser\Node\LiteralNode("\f", 13, 16),
                    new \RegexParser\Node\LiteralNode("\v", 17, 20),
                ],
                0, 21
            ),
            false,
            0, 22
        );

        $result = $whitespaceClass->accept($this->visitor);

        $this->assertInstanceOf(\RegexParser\Node\CharTypeNode::class, $result);
        $this->assertSame('s', $result->value);
    }
}
