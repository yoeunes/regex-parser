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
 * Represents a version condition in conditional groups (e.g., (?(VERSION>=10.0)...)).
 *
 * Purpose: This node represents PCRE2 version conditions that allow
 * conditional matching based on the PCRE library version.
 */
final readonly class VersionConditionNode extends AbstractNode
{
    /**
     * Initializes a version condition node.
     *
     * @param string $operator      The comparison operator (e.g., '>=').
     * @param string $version       The version string (e.g., '10.0').
     * @param int    $startPosition the start position
     * @param int    $endPosition   the end position
     */
    public function __construct(
        public string $operator,
        public string $version,
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
        return $visitor->visitVersionCondition($this);
    }
}
