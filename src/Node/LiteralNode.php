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
 * Represents a literal character (e.g., "a", "1", or an escaped "\*").
 */
class LiteralNode implements NodeInterface
{
    /**
     * @param string $value    the literal character
     * @param int    $startPos The 0-based start offset
     * @param int    $endPos   The 0-based end offset (exclusive)
     */
    public function __construct(
        public readonly string $value,
        public readonly int $startPos,
        public readonly int $endPos,
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
        return $visitor->visitLiteral($this);
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
