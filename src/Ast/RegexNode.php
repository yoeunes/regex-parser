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
 * Represents the root of a parsed regex, containing the pattern and any flags.
 */
class RegexNode implements NodeInterface
{
    /**
     * @param NodeInterface $pattern The root node of the regex pattern (e.g., an AlternationNode or SequenceNode).
     * @param string        $flags   A string of flags (e.g., "imsU").
     */
    public function __construct(
        public readonly NodeInterface $pattern,
        public readonly string $flags,
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
        return $visitor->visitRegex($this);
    }
}
