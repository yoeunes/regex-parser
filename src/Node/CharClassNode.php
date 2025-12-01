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
 * Represents a character class in a regular expression, such as `[a-z]` or `[^0-9]`.
 *
 * Purpose: This node defines a set of characters that can be matched at a single position in the
 * input string. It can contain individual literal characters, character types (`\d`), POSIX classes
 * (`[:digit:]`), and ranges (`a-z`). It can also be negated (`[^...]`) to match any character
 * *not* in the set. This is one of the most powerful and common components of regular expressions.
 */
readonly class CharClassNode extends AbstractNode
{
    /**
     * Initializes a character class node.
     *
     * Purpose: This constructor creates a node representing a `[...]` construct. The `Parser`
     * builds this node and populates it with the various components found between the brackets.
     *
     * @param array<NodeInterface> $parts         An array of nodes representing the contents of the character class.
     *                                            This can include `LiteralNode`, `CharTypeNode`, `RangeNode`,
     *                                            `PosixClassNode`, etc.
     * @param bool                 $isNegated     `true` if the class is negated (starts with `^`), meaning it matches
     *                                            any character *not* in the set. `false` otherwise.
     * @param int                  $startPosition the zero-based byte offset where the opening `[` appears
     * @param int                  $endPosition   the zero-based byte offset immediately after the closing `]`
     */
    public function __construct(
        public array $parts,
        public bool $isNegated,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `CharClassNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitCharClass` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitCharClass($this);
    }
}
