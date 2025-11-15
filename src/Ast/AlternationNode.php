<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * Represents an alternation (e.g., "a|b").
 */
class AlternationNode implements NodeInterface
{
    /**
     * @param array<NodeInterface> $alternatives
     */
    public function __construct(public readonly array $alternatives)
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
        return $visitor->visitAlternation($this);
    }
}
