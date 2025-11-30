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

class DefineNode implements NodeInterface
{
    /**
     * @param NodeInterface $content // tout ce qui est à l'intérieur du (?(DEFINE) ... )
     */
    public function __construct(
        public readonly NodeInterface $content,
        public readonly int $startPosition,
        public readonly int $endPosition,
    ) {}

    public function accept(NodeVisitorInterface $visitor): mixed
    {
        return $visitor->visitDefine($this);
    }

    public function getStartPosition(): int
    {
        return $this->startPosition;
    }

    public function getEndPosition(): int
    {
        return $this->endPosition;
    }
}
