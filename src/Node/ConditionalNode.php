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
 * Represents a conditional sub-pattern, such as `(?(condition)yes-pattern|no-pattern)`.
 *
 * Purpose: This node encapsulates complex branching logic within a regex. The `condition` can be
 * a lookaround assertion (e.g., `(?=...)`) or a backreference check (e.g., `(1)`). If the condition
 * is met, the regex engine attempts to match the `yes-pattern`. Otherwise, it attempts to match the
 * `no-pattern` if one is provided. This allows for creating highly adaptive and context-aware patterns.
 */
readonly class ConditionalNode extends AbstractNode
{
    /**
     * Initializes a conditional node.
     *
     * Purpose: This constructor creates a node representing a `(?(...)...)` structure. The `Parser`
     * builds this node to represent the three key parts of the conditional: the condition to check,
     * the pattern to match on success, and the pattern to match on failure.
     *
     * @param NodeInterface $condition     The node representing the condition to be evaluated. This is typically
     *                                     a `BackrefNode` or a lookaround `GroupNode`.
     * @param NodeInterface $yes           the sub-pattern to be matched if the condition is met
     * @param NodeInterface $no            The sub-pattern to be matched if the condition is not met. If no "else"
     *                                     branch is specified in the regex, this will be an empty `LiteralNode`.
     * @param int           $startPosition the zero-based byte offset where the `(?(` sequence begins
     * @param int           $endPosition   the zero-based byte offset immediately after the final `)`
     */
    public function __construct(
        public NodeInterface $condition,
        public NodeInterface $yes,
        public NodeInterface $no,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `ConditionalNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitConditional` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitConditional($this);
    }
}
