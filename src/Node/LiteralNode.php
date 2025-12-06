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
 * Represents a literal character or sequence of characters in a regular expression.
 *
 * Purpose: This is one of the most fundamental nodes in the AST. It represents any character
 * that is meant to be matched exactly as it is, such as `a`, `1`, or an escaped metacharacter
 * like `\*`. The `OptimizerNodeVisitor` often works by merging adjacent `LiteralNode`
 * instances into a single node for efficiency.
 */
final readonly class LiteralNode extends AbstractNode
{
    /**
     * Initializes a literal node.
     *
     * Purpose: This constructor creates a node to represent a literal character or string.
     * The `Parser` generates these nodes for any characters that are not special regex
     * metacharacters.
     *
     * @param string $value         The literal character or string of characters to be matched. This can include
     *                              the content of escaped literals (e.g., the value for `\t` would be the tab character).
     * @param int    $startPosition the zero-based byte offset where the literal character(s) begin
     * @param int    $endPosition   the zero-based byte offset immediately after the literal character(s)
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
     * process this `LiteralNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitLiteral` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitLiteral($this);
    }
}
