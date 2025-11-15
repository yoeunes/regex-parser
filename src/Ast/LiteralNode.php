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
 * Represents a literal character (e.g., "a", "1", or an escaped "\*").
 */
class LiteralNode implements NodeInterface
{
    /**
     * @param string $value the literal character
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
        return $visitor->visitLiteral($this);
    }
}
