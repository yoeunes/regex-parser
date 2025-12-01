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
 * Represents a backreference to a previously captured group (e.g., `\1`, `\k<name>`).
 *
 * Purpose: This node allows a regex to match the exact same text that was previously captured by a
 * capturing group. This is essential for patterns that need to find repeated words or enforce
 * symmetry, such as matching opening and closing HTML tags. The backreference can be identified
 * by number (relative or absolute) or by name.
 */
readonly class BackrefNode extends AbstractNode
{
    /**
     * Initializes a backreference node.
     *
     * Purpose: This constructor creates a node that refers to a capturing group. The `Parser`
     * generates this node when it encounters a backreference token, such as `\1` or `\k<name>`.
     *
     * @param string $ref           The identifier of the group being referenced. This can be a numeric string
     *                              (e.g., "1", "-1") or a group name (e.g., "tagName"). The raw value from the
     *                              token is stored, including syntax like `\g{1}`.
     * @param int    $startPosition The zero-based byte offset where the backreference sequence begins.
     * @param int    $endPosition   The zero-based byte offset immediately after the backreference sequence.
     */
    public function __construct(
        public string $ref,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `BackrefNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitBackref` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor The visitor object that is traversing the tree.
     *
     * @return T The result of the visitor's processing for this node.
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitBackref($this);
    }
}
