<?php

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * Represents a group (e.g., "(...)").
 */
class GroupNode implements NodeInterface
{
    /**
     * The child is the expression contained within the group,
     * which is typically an AlternationNode or SequenceNode.
     */
    public function __construct(public readonly NodeInterface $child)
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
        return $visitor->visitGroup($this);
    }
}
