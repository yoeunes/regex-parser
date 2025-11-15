<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * @template TReturn
 *
 * @implements NodeInterface<TReturn>
 */
class QuantifierNode implements NodeInterface
{
    /**
     * @param NodeInterface<TReturn> $node
     */
    public function __construct(public readonly NodeInterface $node, public readonly string $quantifier)
    {
    }

    /**
     * @param VisitorInterface<TReturn> $visitor
     *
     * @return TReturn
     */
    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitQuantifier($this);
    }
}
