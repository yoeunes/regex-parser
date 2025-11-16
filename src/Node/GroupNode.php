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
 * Represents a group (e.g., "(...)", "(?:...)", "(?<name>...)").
 */
class GroupNode implements NodeInterface
{
    /**
     * @param NodeInterface $child    the expression contained within the group
     * @param GroupType     $type     The semantic type of the group
     * @param ?string       $name     The name, if it's a named group
     * @param ?string       $flags    Inline flags for (?i:...)
     * @param int           $startPos The 0-based start offset
     * @param int           $endPos   The 0-based end offset (exclusive)
     */
    public function __construct(
        public readonly NodeInterface $child,
        public readonly GroupType $type,
        public readonly ?string $name = null,
        public readonly ?string $flags = null,
        public readonly int $startPos = 0,
        public readonly int $endPos = 0,
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
        return $visitor->visitGroup($this);
    }

    public function getStartPosition(): int
    {
        return $this->startPos;
    }

    public function getEndPosition(): int
    {
        return $this->endPos;
    }
}
