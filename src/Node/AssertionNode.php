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
 * Represents a zero-width assertion like a word boundary (`\b`) or the start of the subject (`\A`).
 *
 * Purpose: This node represents special sequences that assert a condition about the text at the
 * current matching position but do not consume any characters. These are distinct from anchors
 * (`^`, `$`) and include assertions like `\b` (word boundary), `\B` (non-word boundary), `\A`
 * (start of subject), `\Z` (end of subject or before final newline), and `\z` (absolute end of subject).
 * They are powerful tools for creating precise and efficient patterns.
 */
readonly class AssertionNode extends AbstractNode
{
    /**
     * Initializes an assertion node.
     *
     * Purpose: This constructor creates a node for a zero-width assertion. The `Parser` generates
     * this node when it encounters an assertion token from the `Lexer` (e.g., `T_ASSERTION`).
     *
     * @param string $value         The character representing the assertion (e.g., 'b', 'A', 'Z'). Note that
     *                              the backslash is not included in this value.
     * @param int    $startPosition The zero-based byte offset where the assertion sequence (e.g., `\b`) begins.
     * @param int    $endPosition   The zero-based byte offset immediately after the assertion sequence.
     */
    public function __construct(
        public string $value,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `AssertionNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitAssertion` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor The visitor object that is traversing the tree.
     *
     * @return T The result of the visitor's processing for this node.
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitAssertion($this);
    }
}
