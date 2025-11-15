<?php

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;

class CompilerVisitor implements VisitorInterface
{
    public function visitAlternation(AlternationNode $node): mixed
    {
        return implode('|', array_map(fn ($alt) => $alt->accept($this), $node->alternatives));
    }

    public function visitGroup(GroupNode $node): mixed
    {
        $compiled = '(';
        foreach ($node->children as $child) {
            $compiled .= $child->accept($this);
        }
        $compiled .= ')';

        return $compiled;
    }

    public function visitLiteral(LiteralNode $node): mixed
    {
        return $node->value;
    }

    public function visitQuantifier(QuantifierNode $node): mixed
    {
        return $node->node->accept($this).$node->quantifier;
    }
}
