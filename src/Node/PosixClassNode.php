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
 * Represents a POSIX character class, such as `[:alpha:]` or `[:^digit:]`.
 *
 * Purpose: POSIX character classes are a special syntax, valid only inside a character class `[...]`,
 * for specifying sets of characters like letters, digits, or punctuation. They provide a more
 * portable alternative to some escaped character types. This node captures the name of the POSIX
 * class (e.g., "alpha") and whether it is negated (e.g., `[:^digit:]`).
 */
final readonly class PosixClassNode extends AbstractNode
{
    /**
     * Initializes a POSIX character class node.
     *
     * Purpose: This constructor creates a node for a `[:...:]` construct found inside a character class.
     *
     * @param string $class         The name of the POSIX class (e.g., "alpha", "digit", "^space"). The
     *                              surrounding `[:` and `:]` are not included. The `^` is included for
     *                              negated classes.
     * @param int    $startPosition the zero-based byte offset where the opening `[:` begins
     * @param int    $endPosition   the zero-based byte offset immediately after the closing `:]`
     */
    public function __construct(
        public string $class,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `PosixClassNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitPosixClass` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitPosixClass($this);
    }
}
