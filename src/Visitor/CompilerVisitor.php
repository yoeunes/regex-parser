<?php

namespace RegexParser\Visitor;

use RegexParser\Ast\AlternationNode;
use RegexParser\Ast\GroupNode;
use RegexParser\Ast\LiteralNode;
use RegexParser\Ast\QuantifierNode;

class CompilerVisitor implements VisitorInterface
{
    public function visitAlternation(AlternationNode $node): string
    {
        return implode('|', array_map(fn ($alt) => $alt->accept($this), $node->alternatives));
    }

    public function visitGroup(GroupNode $node): string
    {
        $compiled = '(';
        foreach ($node->children as $child) {
            /** @var string $childCompiled */
            $childCompiled = $child->accept($this);
            $compiled .= $childCompiled;
        }
        $compiled .= ')';

        return $compiled;
    }

    public function visitLiteral(LiteralNode $node): string
    {
        return $node->value;
    }

    public function visitQuantifier(QuantifierNode $node): string
    {
        /** @var string $nodeCompiled */
        $nodeCompiled = $node->node->accept($this);

        return $nodeCompiled.$node->quantifier;
    }
}
