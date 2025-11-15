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
 * Represents a quantifier (e.g., "*", "+", "{1,3}").
 */
class QuantifierNode implements NodeInterface
{
    /**
     * @param NodeInterface  $node       the node to be quantified
     * @param string         $quantifier The quantifier string (e.g., "*", "{1,3}").
     * @param QuantifierType $type       The type of quantifier (greedy, lazy, possessive)
     */
    public function __construct(
        public readonly NodeInterface $node,
        public readonly string $quantifier,
        public readonly QuantifierType $type,
    ) {
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
