<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * Represents a sequence (concatenation) of nodes.
 * Ex: "abc" is Sequence(Literal(a), Literal(b), Literal(c)).
 */
class SequenceNode implements NodeInterface
{
    /**
     * @param array<NodeInterface> $children
     */
    public function __construct(public readonly array $children)
    {
    }

    /**
     * @template T
     *
     * @param VisitorInterface<T> $visitor
     *
     * @return T
     */
    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitSequence($this);
    }
}
