<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

class QuantifierNode implements NodeInterface
{
    public function __construct(public readonly NodeInterface $node, public readonly string $quantifier)
    {
    }

    public function accept(VisitorInterface $visitor): mixed
    {
        return $visitor->visitQuantifier($this);
    }
}
