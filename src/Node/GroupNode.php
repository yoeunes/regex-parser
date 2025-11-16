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
 * Represents a group (e.g., "(...)", "(?:...)", "(?<name>...)").
 */
class GroupNode implements NodeInterface
{
    /**
     * @param NodeInterface $child the expression contained within the group
     * @param GroupType     $type  The semantic type of the group
     * @param ?string       $name  The name, if it's a named group
     * @param ?string       $flags Inline flags for (?i:...)
     */
    public function __construct(
        public readonly NodeInterface $child,
        public readonly GroupType $type,
        public readonly ?string $name = null,
        public readonly ?string $flags = null,
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
        return $visitor->visitGroup($this);
    }
}
