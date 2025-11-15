<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

class GroupNode implements NodeInterface
{
    /**
     * L'enfant est l'expression contenue dans le groupe,
     * qui est typiquement une AlternationNode ou SequenceNode.
     */
    public function __construct(public readonly NodeInterface $child)
    {
    }

    public function accept(VisitorInterface $visitor): mixed
    {
        return $visitor->visitGroup($this);
    }
}
