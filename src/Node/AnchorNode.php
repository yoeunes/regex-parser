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
 * Represents an anchor in a regular expression, such as `^` (start of string/line) or `$` (end of string/line).
 *
 * Purpose: This node represents a zero-width assertion that constrains the match to a specific
 * position without consuming any characters. Anchors are fundamental for ensuring that a pattern
 * matches at the beginning or end of the input, which is a common requirement in validation
* and routing.
 */
readonly class AnchorNode extends AbstractNode
{
    /**
     * Initializes an anchor node.
     *
     * Purpose: This constructor creates a node representing a positional anchor. The `Parser`
     * generates this node when it encounters an anchor token like `^` or `$`.
     *
     * @param string $value         The anchor character itself (e.g., "^", "$"). This value is used by
     *                              visitors to determine the type of anchor.
     * @param int    $startPosition The zero-based byte offset where the anchor character appears.
     * @param int    $endPosition   The zero-based byte offset immediately after the anchor character.
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
     * process this `AnchorNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitAnchor` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor The visitor object that is traversing the tree.
     *
     * @return T The result of the visitor's processing for this node.
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitAnchor($this);
    }
}
