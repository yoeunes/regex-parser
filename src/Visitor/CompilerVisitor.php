<?php

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;
use RegexParser\Ast\SequenceNode;

class CompilerVisitor implements VisitorInterface
{
    public function visitAlternation(AlternationNode $node): string
    {
        return implode('|', array_map(fn ($alt) => $alt->accept($this), $node->alternatives));
    }

    public function visitGroup(GroupNode $node): string
    {
        // L'enfant du groupe est visité
        return '('.$node->child->accept($this).')';
    }

    public function visitLiteral(LiteralNode $node): string
    {
        // Échapper les méta-caractères si nécessaire (simplification)
        if (\in_array($node->value, ['(', ')', '[', ']', '*', '+', '?', '|', '\\'], true)) {
            return '\\'.$node->value;
        }

        return $node->value;
    }

    public function visitQuantifier(QuantifierNode $node): string
    {
        /** @var string $nodeCompiled */
        $nodeCompiled = $node->node->accept($this);

        return $nodeCompiled.$node->quantifier;
    }

    public function visitSequence(SequenceNode $node): string
    {
        // Concatène les résultats des enfants de la séquence
        return implode('', array_map(fn ($child) => $child->accept($this), $node->children));
    }
}
