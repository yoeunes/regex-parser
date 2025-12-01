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
 * Represents a legacy octal character escape, such as `\0`, `\01`, or `\012`.
 *
 * Purpose: This node represents an older, often ambiguous syntax for specifying a character by its
 * octal code. The syntax `\0...` can be confused with a backreference to capture group 0. Modern
 * regex should prefer the brace syntax `\o{...}` (see `OctalNode`). This node exists to correctly
 * parse and represent this legacy syntax, allowing tools to identify and potentially warn about its use.
 */
readonly class OctalLegacyNode extends AbstractNode
{
    /**
     * Initializes a legacy octal escape node.
     *
     * Purpose: This constructor creates a node for a legacy octal escape sequence.
     *
     * @param string $code          The octal code itself (e.g., "0", "01", "012"). Note that the
     *                              leading `\` is not included.
     * @param int    $startPosition the zero-based byte offset where the octal sequence begins
     * @param int    $endPosition   the zero-based byte offset immediately after the octal sequence
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
     * process this `OctalLegacyNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitOctalLegacy` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitOctalLegacy($this);
    }
}
