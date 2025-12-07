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
 * Represents a character class operation (intersection && or subtraction --).
 *
 * Purpose: This node handles extended character class operations in PCRE2,
 * allowing complex class combinations like [a&&b] or [a--b].
 */
final readonly class ClassOperationNode extends AbstractNode
{
    /**
     * Initializes a class operation node.
     *
     * @param ClassOperationType $type The operation type (intersection or subtraction).
     * @param NodeInterface      $left The left operand.
     * @param NodeInterface      $right The right operand.
     * @param int                $startPosition The start position.
     * @param int                $endPosition The end position.
     */
    public function __construct(
        public ClassOperationType $type,
        public NodeInterface $left,
        public NodeInterface $right,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitClassOperation($this);
    }
}