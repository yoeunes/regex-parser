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

class BackrefNode extends AbstractNode
{
    /**
     * @param int $startPos The 0-based start offset
     * @param int $endPos   The 0-based end offset (exclusive)
     */
    public function __construct(
        public readonly string $ref,
        int $startPos,
        int $endPos,
    ) {
        parent::__construct($startPos, $endPos);
    }

    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitBackref($this);
    }
}
