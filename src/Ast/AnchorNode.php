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
 * Represents an anchor (e.g., "^" or "$").
 */
class AnchorNode implements NodeInterface
{
    /**
     * @param string $value the anchor character ("^" or "$")
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
        return $visitor->visitAnchor($this);
    }
}
