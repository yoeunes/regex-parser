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
 * Represents an alternation in a regular expression, denoted by the pipe `|` character.
 *
 * Purpose: This node is a container for multiple possible sub-patterns (alternatives).
 * It signifies a point in the regex where the engine must choose one of several paths.
 * For example, in `cat|dog`, this node would hold two children: a `LiteralNode` for "cat"
 * and a `LiteralNode` for "dog". It's a fundamental building block for creating flexible
 * and complex matching logic.
 */
readonly class AlternationNode extends AbstractNode
{
    /**
     * Initializes an alternation node with its possible sub-patterns.
     *
     * Purpose: This constructor creates a node that represents a choice between different
     * branches in the regex. The `Parser` creates this node when it encounters a `|` token.
     * Each element in the `$alternatives` array is a complete sub-pattern that could be
     * matched.
     *
     * @param array<NodeInterface> $alternatives  An ordered array of child nodes, where each node represents
     *                                            one of the possible choices in the alternation. For `a|b|c`,
     *                                            this array would contain three `SequenceNode` or `LiteralNode`
     *                                            children.
     * @param int                  $startPosition The zero-based byte offset where the first alternative begins.
     * @param int                  $endPosition   The zero-based byte offset where the last alternative ends.
     */
    public function __construct(
        public array $alternatives,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `AlternationNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitAlternation` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor The visitor object that is traversing the tree.
     *
     * @return T The result of the visitor's processing for this node.
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitAlternation($this);
    }
}
