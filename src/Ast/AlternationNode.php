<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

class AlternationNode implements NodeInterface
{
    /**
     * @param array<NodeInterface> $alternatives
     */
    public function __construct(public readonly array $alternatives)
    {
    }

    public function accept(VisitorInterface $visitor): mixed
    {
        return $visitor->visitAlternation($this);
    }
}
