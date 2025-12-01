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
 * Represents a `(?(DEFINE)...)` block in a regular expression.
 *
 * Purpose: This special conditional block is used to define named sub-patterns that can be
 * referenced later as subroutines (e.g., `(?&name)`). The content inside the `DEFINE` block
 * is never matched directly; it only serves as a library of patterns. This is extremely
 * useful for organizing complex regexes and reusing common components, similar to functions
 * in a programming language.
 */
readonly class DefineNode extends AbstractNode
{
    /**
     * Initializes a `(?(DEFINE)...)` node.
     *
     * Purpose: This constructor creates a node that encapsulates the patterns defined within a
     * `DEFINE` block. The `Parser` creates this node when it identifies the `(?(DEFINE)...)`
     * structure.
     *
     * @param NodeInterface $content       The root node of the AST for the patterns defined inside the block.
     *                                     This is typically a `SequenceNode` containing one or more named
     *                                     capturing groups that serve as the definitions.
     * @param int           $startPosition the zero-based byte offset where the `(?(DEFINE)` sequence begins
     * @param int           $endPosition   the zero-based byte offset immediately after the final `)`
     */
    public function __construct(
        public NodeInterface $content,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `DefineNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitDefine` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor): mixed
    {
        return $visitor->visitDefine($this);
    }
}
