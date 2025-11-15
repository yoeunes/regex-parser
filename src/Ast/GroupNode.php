<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * @template TReturn
 *
 * @implements NodeInterface<TReturn>
 */
class GroupNode implements NodeInterface
{
    /**
     * L'enfant est l'expression contenue dans le groupe,
     * qui est typiquement une AlternationNode ou SequenceNode.
     *
     * @param NodeInterface<TReturn> $child
     */
    public function __construct(public readonly NodeInterface $child)
    {
    }

    /**
     * @param VisitorInterface<TReturn> $visitor
     *
     * @return TReturn
     */
    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitGroup($this);
    }
}
