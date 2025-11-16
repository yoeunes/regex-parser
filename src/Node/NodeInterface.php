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
 * Base interface for all AST nodes.
 */
interface NodeInterface
{
    /**
     * Accepts a visitor.
     *
     * @template T
     *
     * @param NodeVisitorInterface<T> $visitor
     *
     * @return T
     */
    public function accept(NodeVisitorInterface $visitor);

    /**
     * Gets the 0-based start offset of the node in the original pattern string.
     */
    public function getStartPosition(): int;

    /**
     * Gets the 0-based end offset (exclusive) of the node in the original pattern string.
     */
    public function getEndPosition(): int;
}
