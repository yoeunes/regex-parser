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

/**
 * Base class for all AST nodes.
 * Implements the NodeInterface and provides shared position logic.
 */
abstract class AbstractNode implements NodeInterface
{
    /**
     * @param int $startPosition The 0-based start offset
     * @param int $endPosition   The 0-based end offset (exclusive)
     */
    public function __construct(
        public readonly int $startPosition,
        public readonly int $endPosition,
    ) {}

    public function getStartPosition(): int
    {
        return $this->startPosition;
    }

    public function getEndPosition(): int
    {
        return $this->endPosition;
    }
}
