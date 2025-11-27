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

namespace RegexParser\NodeVisitor;

use RegexParser\Node\AlternationNode;
use RegexParser\Node\AnchorNode;
use RegexParser\Node\AssertionNode;
use RegexParser\Node\BackrefNode;
use RegexParser\Node\CharClassNode;
use RegexParser\Node\CharTypeNode;
use RegexParser\Node\CommentNode;
use RegexParser\Node\ConditionalNode;
use RegexParser\Node\DefineNode;
use RegexParser\Node\DotNode;
use RegexParser\Node\GroupNode;
use RegexParser\Node\KeepNode;
use RegexParser\Node\LiteralNode;
use RegexParser\Node\OctalLegacyNode;
use RegexParser\Node\OctalNode;
use RegexParser\Node\PcreVerbNode;
use RegexParser\Node\PosixClassNode;
use RegexParser\Node\QuantifierNode;
use RegexParser\Node\RangeNode;
use RegexParser\Node\RegexNode;
use RegexParser\Node\SequenceNode;
use RegexParser\Node\SubroutineNode;
use RegexParser\Node\UnicodeNode;
use RegexParser\Node\UnicodePropNode;

/**
 * Defines the Visitor interface for traversing the AST.
 * Uses the Visitor design pattern.
 *
 * @template-covariant TReturn The return type of the visitor (e.g., 'string' for Compiler, 'void' for Validator)
 */
interface NodeVisitorInterface
{
    /**
     * @return TReturn
     */
    public function visitRegex(RegexNode $node);

    /**
     * @return TReturn
     */
    public function visitAlternation(AlternationNode $node);

    /**
     * @return TReturn
     */
    public function visitSequence(SequenceNode $node);

    /**
     * @return TReturn
     */
    public function visitGroup(GroupNode $node);

    /**
     * @return TReturn
     */
    public function visitQuantifier(QuantifierNode $node);

    /**
     * @return TReturn
     */
    public function visitLiteral(LiteralNode $node);

    /**
     * @return TReturn
     */
    public function visitCharType(CharTypeNode $node);

    /**
     * @return TReturn
     */
    public function visitDot(DotNode $node);

    /**
     * @return TReturn
     */
    public function visitAnchor(AnchorNode $node);

    /**
     * @return TReturn
     */
    public function visitAssertion(AssertionNode $node);

    /**
     * @return TReturn
     */
    public function visitKeep(KeepNode $node);

    /**
     * @return TReturn
     */
    public function visitCharClass(CharClassNode $node);

    /**
     * @return TReturn
     */
    public function visitRange(RangeNode $node);

    /**
     * @return TReturn
     */
    public function visitBackref(BackrefNode $node);

    /**
     * @return TReturn
     */
    public function visitUnicode(UnicodeNode $node);

    /**
     * @return TReturn
     */
    public function visitUnicodeProp(UnicodePropNode $node);

    /**
     * @return TReturn
     */
    public function visitOctal(OctalNode $node);

    /**
     * @return TReturn
     */
    public function visitOctalLegacy(OctalLegacyNode $node);

    /**
     * @return TReturn
     */
    public function visitPosixClass(PosixClassNode $node);

    /**
     * @return TReturn
     */
    public function visitComment(CommentNode $node);

    /**
     * @return TReturn
     */
    public function visitConditional(ConditionalNode $node);

    /**
     * @return TReturn
     */
    public function visitSubroutine(SubroutineNode $node);

    /**
     * @return TReturn
     */
    public function visitPcreVerb(PcreVerbNode $node);

    /**
     * @return TReturn
     */
    public function visitDefine(DefineNode $node);
}
