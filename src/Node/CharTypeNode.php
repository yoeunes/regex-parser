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
 * Represents a predefined, escaped character type, such as `\d` (digit), `\s` (whitespace), or `\W` (non-word).
 *
 * Purpose: This node is a shorthand for common character classes. For example, `\d` is often
 * equivalent to `[0-9]`. The parser creates this node for these common escape sequences,
 * providing a more semantic representation than a simple literal. This allows visitors to apply
 * specific logic for these well-known types, such as in sample generation or explanation.
 */
final readonly class CharTypeNode extends AbstractNode
{
    /**
     * Initializes a character type node.
     *
     * Purpose: This constructor creates a node representing a predefined character class like `\d` or `\s`.
     *
     * @param string $value         The character representing the type (e.g., 'd', 's', 'W'). Note that the
     *                              backslash is not included in this value.
     * @param int    $startPosition The zero-based byte offset where the sequence (e.g., `\d`) begins.
     * @param int    $endPosition   the zero-based byte offset immediately after the sequence
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
     * process this `CharTypeNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitCharType` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitCharType($this);
    }
}
