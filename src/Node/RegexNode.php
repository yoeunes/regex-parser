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
 * Represents the root of a parsed regex, containing the pattern and any flags.
 */
class RegexNode implements NodeInterface
{
    /**
     * @param NodeInterface $pattern   The root node of the regex pattern (e.g., an AlternationNode or SequenceNode).
     * @param string        $flags     A string of flags (e.g., "imsU").
     * @param string        $delimiter The opening delimiter (e.g., "/", "~", "(").
     */
    public function __construct(
        public readonly NodeInterface $pattern,
        public readonly string $flags,
        public readonly string $delimiter,
    ) {
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
        return $visitor->visitRegex($this);
    }
}
