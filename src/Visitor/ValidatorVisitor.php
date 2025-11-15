<?php

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\SequenceNode;
use RegexParser\Exception\ParserException;

/**
 * A visitor that validates semantic rules of the regex AST.
 * (e.g., quantifier ranges).
 *
 * @implements VisitorInterface<void>
 */
class ValidatorVisitor implements VisitorInterface
{
    public function visitAlternation(AlternationNode $node): void
    {
        foreach ($node->alternatives as $alt) {
            $alt->accept($this);
        }

        return;
    }

    public function visitGroup(GroupNode $node): void
    {
        $node->child->accept($this);

        return;
    }

    public function visitLiteral(LiteralNode $node): void
    {
        // Validation logic for literals could go here.
        return;
    }

    public function visitQuantifier(QuantifierNode $node): void
    {
        if (\in_array($node->quantifier, ['*', '+', '?'], true)) {
            // OK
        } elseif (preg_match('/^{\d+(,\d*)?}$/', $node->quantifier)) {
            // Check n <= m
            $parts = explode(',', trim($node->quantifier, '{}'));
            if (2 === \count($parts) && '' !== $parts[1] && (int) $parts[0] > (int) $parts[1]) {
                throw new ParserException('Invalid quantifier range: min > max');
            }
        } else {
            throw new ParserException('Invalid quantifier: '.$node->quantifier);
        }
        $node->node->accept($this);

        return;
    }

    public function visitSequence(SequenceNode $node): void
    {
        foreach ($node->children as $child) {
            $child->accept($this);
        }

        return;
    }
}
