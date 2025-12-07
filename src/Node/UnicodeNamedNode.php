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
 * Represents a named Unicode character, such as `\N{LATIN CAPITAL LETTER A}`.
 *
 * Purpose: This node allows for the specification of Unicode characters by their official names,
 * which is useful for readability and precision in regex patterns. It handles the `\N{...}` syntax.
 */
final readonly class UnicodeNamedNode extends AbstractNode
{
    /**
     * Initializes a named Unicode character escape node.
     *
     * Purpose: This constructor creates a node for a character specified by its Unicode name.
     *
     * @param string $name          The Unicode name of the character (e.g., "LATIN CAPITAL LETTER A").
     * @param int    $startPosition The zero-based byte offset where the Unicode escape sequence begins (e.g., at `\N`).
     * @param int    $endPosition   the zero-based byte offset immediately after the sequence
     */
    public function __construct(
        public string $name,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `UnicodeNamedNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitUnicodeNamed` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitUnicodeNamed($this);
    }
}
