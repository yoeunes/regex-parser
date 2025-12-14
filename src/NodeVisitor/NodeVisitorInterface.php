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

use RegexParser\Node;

/**
 * Defines the contract for a visitor that traverses the regex Abstract Syntax Tree (AST).
 *
 * @template-covariant TReturn The return type of the visitor's methods (e.g., `string`
 *                             for `CompilerNodeVisitor`, `void` for `ValidatorNodeVisitor`).
 */
interface NodeVisitorInterface
{
    /**
     * @return TReturn
     */
    public function visitRegex(Node\RegexNode $node);

    /**
     * @return TReturn
     */
    public function visitAlternation(Node\AlternationNode $node);

    /**
     * @return TReturn
     */
    public function visitSequence(Node\SequenceNode $node);

    /**
     * @return TReturn
     */
    public function visitGroup(Node\GroupNode $node);

    /**
     * @return TReturn
     */
    public function visitQuantifier(Node\QuantifierNode $node);

    /**
     * @return TReturn
     */
    public function visitLiteral(Node\LiteralNode $node);

    /**
     * @return TReturn
     */
    public function visitCharType(Node\CharTypeNode $node);

    /**
     * @return TReturn
     */
    public function visitDot(Node\DotNode $node);

    /**
     * @return TReturn
     */
    public function visitAnchor(Node\AnchorNode $node);

    /**
     * @return TReturn
     */
    public function visitAssertion(Node\AssertionNode $node);

    /**
     * @return TReturn
     */
    public function visitKeep(Node\KeepNode $node);

    /**
     * @return TReturn
     */
    public function visitCharClass(Node\CharClassNode $node);

    /**
     * @return TReturn
     */
    public function visitRange(Node\RangeNode $node);

    /**
     * @return TReturn
     */
    public function visitBackref(Node\BackrefNode $node);

    /**
     * @return TReturn
     */
    public function visitUnicode(Node\UnicodeNode $node);

    /**
     * @return TReturn
     */
    public function visitUnicodeNamed(Node\UnicodeNamedNode $node);

    /**
     * @return TReturn
     */
    public function visitClassOperation(Node\ClassOperationNode $node);

    /**
     * @return TReturn
     */
    public function visitControlChar(Node\ControlCharNode $node);

    /**
     * @return TReturn
     */
    public function visitScriptRun(Node\ScriptRunNode $node);

    /**
     * @return TReturn
     */
    public function visitVersionCondition(Node\VersionConditionNode $node);

    /**
     * @return TReturn
     */
    public function visitUnicodeProp(Node\UnicodePropNode $node);

    /**
     * @return TReturn
     */
    public function visitOctal(Node\OctalNode $node);

    /**
     * @return TReturn
     */
    public function visitOctalLegacy(Node\OctalLegacyNode $node);

    /**
     * @return TReturn
     */
    public function visitPosixClass(Node\PosixClassNode $node);

    /**
     * @return TReturn
     */
    public function visitComment(Node\CommentNode $node);

    /**
     * @return TReturn
     */
    public function visitConditional(Node\ConditionalNode $node);

    /**
     * @return TReturn
     */
    public function visitSubroutine(Node\SubroutineNode $node);

    /**
     * @return TReturn
     */
    public function visitPcreVerb(Node\PcreVerbNode $node);

    /**
     * @return TReturn
     */
    public function visitDefine(Node\DefineNode $node);

    /**
     * @return TReturn
     */
    public function visitLimitMatch(Node\LimitMatchNode $node);

    /**
     * @return TReturn
     */
    public function visitCallout(Node\CalloutNode $node);
}
