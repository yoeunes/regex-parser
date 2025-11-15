<?php

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Exception\ParserException;

class ValidatorVisitor implements VisitorInterface
{
    public function visitAlternation(AlternationNode $node): mixed
    {
        foreach ($node->alternatives as $alt) {
            $alt->accept($this);
        }

        return null;
    }

    public function visitGroup(GroupNode $node): mixed
    {
        foreach ($node->children as $child) {
            $child->accept($this);
        }

        return null;
    }

    public function visitLiteral(LiteralNode $node): mixed
    {
        // Valider si besoin, e.g., pas de chars interdits
        return null;
    }

    public function visitQuantifier(QuantifierNode $node): mixed
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

        return null;
    }
}
