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
 * Represents a quantifier (e.g., "*", "+", "{1,3}").
 */
class QuantifierNode extends AbstractNode
{
    /**
     * @param NodeInterface  $node       the node to be quantified
     * @param string         $quantifier The quantifier string (e.g., "*", "{1,3}").
     * @param QuantifierType $type       The type of quantifier (greedy, lazy, possessive)
     * @param int            $startPos   The 0-based start offset
     * @param int            $endPos     The 0-based end offset (exclusive)
     */
    public function __construct(
        public readonly NodeInterface $node,
        public readonly string $quantifier,
        public readonly QuantifierType $type,
        int $startPos,
        int $endPos,
    ) {
        parent::__construct($startPos, $endPos);
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
        return $visitor->visitQuantifier($this);
    }
}
