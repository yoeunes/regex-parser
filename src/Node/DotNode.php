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
 * Represents the dot "." wildcard character.
 */
class DotNode extends AbstractNode
{
    /**
     * @param int $startPos    The 0-based start offset
     * @param int $endPosition The 0-based end offset (exclusive)
     */
    public function __construct(
        int $startPos,
        int $endPosition,
    ) {
        parent::__construct($startPos, $endPosition);
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
        return $visitor->visitDot($this);
    }
}
