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
 * Represents a subroutine call to another part of the pattern, such as `(?R)`, `(?1)`, or `(?&name)`.
 *
 * Purpose: Subroutines are a powerful PCRE feature that allow a part of the regex to be "called"
 * from another point, similar to a function call. This is useful for matching recursive structures
 * (e.g., nested parentheses) or for reusing a complex pattern defined in a capturing group or a
 * `(?(DEFINE)...)` block. This node captures the reference to the pattern being called.
 */
final readonly class SubroutineNode extends AbstractNode
{
    /**
     * Initializes a subroutine call node.
     *
     * Purpose: This constructor creates a node representing a call to a sub-pattern.
     *
     * @param string $reference     The identifier of the group being called. This can be a number (e.g., "1", "-1"),
     *                              a name (e.g., "myPattern"), or a special character like "R" (recurse whole pattern)
     *                              or "0" (same meaning as "R").
     * @param string $syntax        The specific syntax used for the call (e.g., "&" for `(?&name)`, "P>" for `(?P>name)`,
     *                              "g" for `\g<name>`). This helps in accurately reconstructing the original pattern.
     * @param int    $startPosition the zero-based byte offset where the subroutine call begins
     * @param int    $endPosition   the zero-based byte offset immediately after the subroutine call
     */
    public function __construct(
        public string $reference,
        public string $syntax,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `SubroutineNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitSubroutine` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitSubroutine($this);
    }
}
