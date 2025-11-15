<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * Représente une séquence (concaténation) de nœuds.
 * Ex: "abc" est Sequence(Literal(a), Literal(b), Literal(c)).
 *
 * @template TReturn
 *
 * @implements NodeInterface<TReturn>
 */
class SequenceNode implements NodeInterface
{
    /**
     * @param array<NodeInterface<TReturn>> $children
     */
    public function __construct(public readonly array $children)
    {
    }

    /**
     * @param VisitorInterface<TReturn> $visitor
     *
     * @return TReturn
     */
    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitSequence($this);
    }
}
