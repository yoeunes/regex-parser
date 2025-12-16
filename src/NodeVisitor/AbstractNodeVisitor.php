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
 * Base visitor that returns a default value for every node.
 *
 * @template TReturn
 *
 * @implements NodeVisitorInterface<TReturn>
 */
abstract class AbstractNodeVisitor implements NodeVisitorInterface
{
    public function visitRegex(Node\RegexNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitAlternation(Node\AlternationNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitSequence(Node\SequenceNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitGroup(Node\GroupNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitQuantifier(Node\QuantifierNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitLiteral(Node\LiteralNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitCharLiteral(Node\CharLiteralNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitCharType(Node\CharTypeNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitUnicode(Node\UnicodeNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitDot(Node\DotNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitAnchor(Node\AnchorNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitAssertion(Node\AssertionNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitKeep(Node\KeepNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitCharClass(Node\CharClassNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitRange(Node\RangeNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitBackref(Node\BackrefNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitClassOperation(Node\ClassOperationNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitControlChar(Node\ControlCharNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitScriptRun(Node\ScriptRunNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitVersionCondition(Node\VersionConditionNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitUnicodeProp(Node\UnicodePropNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitPosixClass(Node\PosixClassNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitComment(Node\CommentNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitConditional(Node\ConditionalNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitSubroutine(Node\SubroutineNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitPcreVerb(Node\PcreVerbNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitDefine(Node\DefineNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitLimitMatch(Node\LimitMatchNode $node)
    {
        return $this->defaultReturn();
    }

    public function visitCallout(Node\CalloutNode $node)
    {
        return $this->defaultReturn();
    }

    /**
     * @return TReturn
     */
    protected function defaultReturn()
    {
        /** @var TReturn $result */
        $result = null;

        return $result;
    }
}
