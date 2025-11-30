<?php

declare(strict_types=1);

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
 * Represents a range inside a character class (e.g., "a-z").
 */
class RangeNode extends AbstractNode
{
    /**
     * @param NodeInterface $start         the start of the range (LiteralNode or CharTypeNode)
     * @param NodeInterface $end           the end of the range (LiteralNode or CharTypeNode)
     * @param int           $startPosition The 0-based start offset
     * @param int           $endPosition   The 0-based end offset (exclusive)
     */
    public function __construct(
        public readonly NodeInterface $start,
        public readonly NodeInterface $end,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitRange($this);
    }
}
