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
 * Represents a subroutine call (e.g., "(?R)", "(?1)", "(?&name)", "(?P>name)").
 */
class SubroutineNode implements NodeInterface
{
    /**
     * @param string $reference The group reference (e.g., "R", "0", "1", "name").
     * @param string $syntax    The original syntax (e.g., "&", "P>", "").
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $syntax = '',
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
        return $visitor->visitSubroutine($this);
    }
}
