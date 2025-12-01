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
 * Represents a grouping construct in a regular expression, such as `(...)`, `(?:...)`, or `(?<name>...)`.
 *
 * Purpose: Groups serve multiple functions in regex: they can capture a sub-match, apply a
 * quantifier to a sequence of tokens, define a non-capturing block for logical grouping, or
 * implement complex features like lookarounds and atomic groups. This node is a versatile
 * container that captures the type of group and its contents, allowing visitors to understand
 * and correctly process its specific behavior.
 */
readonly class GroupNode extends AbstractNode
{
    /**
     * Initializes a group node with its content and semantic type.
     *
     * Purpose: This constructor creates a node representing any `(...)` construct. The `Parser`
     * determines the `GroupType` based on the syntax (e.g., `(?:` for non-capturing, `(?=` for
     * lookahead) and wraps the inner expression in this node.
     *
     * @param NodeInterface $child         the root node of the AST for the expression contained within the group's parentheses
     * @param GroupType     $type          An enum value that specifies the semantic meaning of the group (e.g., capturing,
     *                                     lookahead, atomic). This is critical for visitors to interpret the group correctly.
     * @param string|null   $name          If the group is a named capturing group (e.g., `(?<name>...)`), this property
     *                                     holds its name. It is `null` for all other group types.
     * @param string|null   $flags         If the group is an inline flag modifier (e.g., `(?i-s:...)`), this property
     *                                     holds the flag string (e.g., "i-s"). It is `null` otherwise.
     * @param int           $startPosition the zero-based byte offset where the opening `(` appears
     * @param int           $endPosition   the zero-based byte offset immediately after the closing `)`
     */
    public function __construct(
        public NodeInterface $child,
        public GroupType $type,
        public ?string $name = null,
        public ?string $flags = null,
        int $startPosition = 0,
        int $endPosition = 0
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `GroupNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitGroup` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitGroup($this);
    }
}
