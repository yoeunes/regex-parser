<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * Represents a quantifier (e.g., "*", "+", "{1,3}").
 */
class QuantifierNode implements NodeInterface
{
    public function __construct(public readonly NodeInterface $node, public readonly string $quantifier)
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
        return $visitor->visitQuantifier($this);
    }
}
