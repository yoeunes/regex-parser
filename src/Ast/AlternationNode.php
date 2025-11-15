<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * @template TReturn
 *
 * @implements NodeInterface<TReturn>
 */
class AlternationNode implements NodeInterface
{
    /**
     * @param array<NodeInterface<TReturn>> $alternatives
     */
    public function __construct(public readonly array $alternatives)
    {
    }

    /**
     * @param VisitorInterface<TReturn> $visitor
     *
     * @return TReturn
     */
    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitAlternation($this);
    }
}
