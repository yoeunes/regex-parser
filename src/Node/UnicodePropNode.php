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

class UnicodePropNode extends AbstractNode
{
    /**
     * @param int $startPosition The 0-based start offset
     * @param int $endPosition   The 0-based end offset (exclusive)
     */
    public function __construct(
        public readonly string $prop,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitUnicodeProp($this);
    }
}
