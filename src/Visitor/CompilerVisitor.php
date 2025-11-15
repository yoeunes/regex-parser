<?php

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\SequenceNode;

/**
 * A visitor that recompiles the AST back into a regex string.
 *
 * @implements VisitorInterface<string>
 */
class CompilerVisitor implements VisitorInterface
{
    /**
     * @param AlternationNode $node
     */
    public function visitAlternation(AlternationNode $node): string
    {
        return implode('|', array_map(fn ($alt) => $alt->accept($this), $node->alternatives));
    }

    /**
     * @param GroupNode $node
     */
    public function visitGroup(GroupNode $node): string
    {
        // The child of the group is visited
        return '('.$node->child->accept($this).')';
    }

    /**
     * @param LiteralNode $node
     */
    public function visitLiteral(LiteralNode $node): string
    {
        // Re-escape special meta-characters
        if (\in_array($node->value, ['(', ')', '[', ']', '*', '+', '?', '|', '\\'], true)) {
            return '\\'.$node->value;
        }

        return $node->value;
    }

    /**
     * @param QuantifierNode $node
     */
    public function visitQuantifier(QuantifierNode $node): string
    {
        /** @var string $nodeCompiled */
        $nodeCompiled = $node->node->accept($this);

        return $nodeCompiled.$node->quantifier;
    }

    /**
     * @param SequenceNode $node
     */
    public function visitSequence(SequenceNode $node): string
    {
        // Concatenates the results of the sequence's children
        return implode('', array_map(fn ($child) => $child->accept($this), $node->children));
    }
}
