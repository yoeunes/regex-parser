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

use RegexParser\Visitor\VisitorInterface;

/**
 * Represents a character class (e.g., "[a-z]", "[^0-9]").
 */
class CharClassNode implements NodeInterface
{
    /**
     * @param array<NodeInterface> $parts     the parts of the class (LiteralNode, CharTypeNode, RangeNode)
     * @param bool                 $isNegated Whether the class is negated (e.g., "[^...]"
     */
    public function __construct(
        public readonly array $parts,
        public readonly bool $isNegated,
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
        return $visitor->visitCharClass($this);
    }
}
