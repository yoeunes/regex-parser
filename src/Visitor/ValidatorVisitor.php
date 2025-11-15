<?php

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\SequenceNode;
use RegexParser\Exception\ParserException;

/**
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
        // Valider si besoin, e.g., pas de chars interdits
        return;
    }

    public function visitQuantifier(QuantifierNode $node): void
    {
        if (\in_array($node->quantifier, ['*', '+', '?'], true)) {
            // OK
        } elseif (preg_match('/^{\d+(,\d*)?}$/', $node->quantifier)) {
            // Vérifier n <= m
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
