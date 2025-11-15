<?php

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\SequenceNode;

/**
 * Defines the Visitor interface for traversing the AST.
 * Uses the Visitor design pattern.
 *
 * @template-covariant TReturn The return type of the visitor (e.g., 'string' for Compiler, 'void' for Validator)
 */
interface VisitorInterface
{
    /**
     * @param AlternationNode<TReturn> $node
     *
     * @return TReturn
     */
    public function visitAlternation(AlternationNode $node);

    /**
     * @param GroupNode<TReturn> $node
     *
     * @return TReturn
     */
    public function visitGroup(GroupNode $node);

    /**
     * @param LiteralNode<TReturn> $node
     *
     * @return TReturn
     */
    public function visitLiteral(LiteralNode $node);

    /**
     * @param QuantifierNode<TReturn> $node
     *
     * @return TReturn
     */
    public function visitQuantifier(QuantifierNode $node);

    /**
     * @param SequenceNode<TReturn> $node
     *
     * @return TReturn
     */
    public function visitSequence(SequenceNode $node);
}
