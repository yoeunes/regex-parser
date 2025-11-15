<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

class GroupNode implements NodeInterface
{
    /**
     * @param array<NodeInterface> $children
     */
    public function __construct(public readonly array $children)
    {
    }

    public function accept(VisitorInterface $visitor): mixed
    {
        return $visitor->visitGroup($this);
    }
}
