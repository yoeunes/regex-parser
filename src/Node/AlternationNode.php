<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Node;

use RegexParser\NodeVisitor\NodeVisitorInterface;

/**
 * Represents an alternation (e.g., "a|b").
 */
class AlternationNode implements NodeInterface
{
    /**
     * @param array<NodeInterface> $alternatives the nodes in the alternation
     */
    public function __construct(public readonly array $alternatives)
    {
    }

    /**
     * @template T
     *
     * @param NodeVisitorInterface<T> $visitor
     *
     * @return T
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitAlternation($this);
    }
}
