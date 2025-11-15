<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

/**
 * Represents a sequence (concatenation) of nodes.
 * Ex: "abc" is Sequence(Literal(a), Literal(b), Literal(c)).
 */
class SequenceNode implements NodeInterface
{
    /**
     * @param array<NodeInterface> $children the nodes in the sequence
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
