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
 * Represents the root node of a parsed regular expression's Abstract Syntax Tree (AST).
 *
 * Purpose: This node serves as the entry point and container for the entire parsed regex.
 * It holds the main pattern's AST as its child and also stores metadata about the regex,
 * such as its delimiters and flags (e.g., `i`, `m`, `s`). All visitors start their
 * traversal from this node.
 */
readonly class RegexNode extends AbstractNode
{
    /**
     * Initializes the root node of the regex AST.
     *
     * Purpose: This constructor creates the top-level container for the parsed pattern. The `Parser`
     * produces this node as its final output.
     *
     * @param NodeInterface $pattern       The root node of the parsed pattern itself. This is typically an
     *                                     `AlternationNode` or `SequenceNode` that contains the rest of the AST.
     * @param string        $flags         A string containing all the flags that modify the regex's behavior
     *                                     (e.g., "imsU").
     * @param string        $delimiter     The opening delimiter character used in the original regex string
     *                                     (e.g., "/", "~", "{"). This is preserved for accurate reconstruction.
     * @param int           $startPosition the zero-based byte offset where the pattern (inside the delimiters) begins
     * @param int           $endPosition   the zero-based byte offset immediately after the pattern content
     */
    public function __construct(
        public NodeInterface $pattern,
        public string $flags,
        public string $delimiter,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `RegexNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitRegex` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitRegex($this);
    }
}
