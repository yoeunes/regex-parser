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
 * Represents an inline comment in a regular expression, such as `(?#this is a comment)`.
 *
 * Purpose: This node captures comments within a regex pattern. While comments do not affect
 * the matching logic, preserving them in the AST is crucial for tools that aim for full
 * fidelity, such as a pretty-printer or a tool that analyzes and then reconstructs the
 * original pattern. It ensures that no information is lost during the parsing process.
 */
readonly class CommentNode extends AbstractNode
{
    /**
     * Initializes a comment node.
     *
     * Purpose: This constructor creates a node to hold the content of a `(?#...)` comment.
     * The `Parser` generates this node to ensure that comments are part of the AST, even
     * though they are ignored by the matching engine.
     *
     * @param string $comment       the raw text content of the comment, excluding the `(?#` and `)` delimiters
     * @param int    $startPosition the zero-based byte offset where the `(?#` sequence begins
     * @param int    $endPosition   the zero-based byte offset immediately after the closing `)`
     */
    public function __construct(
        public string $comment,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `CommentNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitComment` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitComment($this);
    }
}
