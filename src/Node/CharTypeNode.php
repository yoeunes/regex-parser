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
 * Represents an escaped character class type (e.g., "\d", "\s", "\W").
 */
class CharTypeNode implements NodeInterface
{
    /**
     * @param string $value The character type (e.g., "d", "s", "W").
     */
    public function __construct(public readonly string $value)
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
        return $visitor->visitCharType($this);
    }
}
