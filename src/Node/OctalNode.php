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
 * Represents a modern octal character escape using brace syntax, such as `\o{101}`.
 *
 * Purpose: This node represents the unambiguous, modern syntax for specifying a character by its
 * octal code. Unlike the legacy `\0...` syntax, the `\o{...}` form clearly distinguishes itself
 * from backreferences and is the recommended way to write octal escapes in PCRE.
 */
final readonly class OctalNode extends AbstractNode
{
    /**
     * Initializes a modern octal escape node.
     *
     * Purpose: This constructor creates a node for a `\o{...}` octal escape sequence.
     *
     * @param string $code          The octal code from within the braces (e.g., "101"). The surrounding
     *                              `\o{...}` syntax is not included.
     * @param int    $startPosition the zero-based byte offset where the `\o{` sequence begins
     * @param int    $endPosition   the zero-based byte offset immediately after the closing `}`
     */
    public function __construct(
        public string $code,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `OctalNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitOctal` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitOctal($this);
    }
}
