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
 * Represents a Unicode character specified by its hexadecimal code point, such as `\x{2603}` or `\x41`.
 *
 * Purpose: This node allows for the precise specification of any Unicode character, which is
 * essential for internationalized and robust pattern matching. It handles different syntaxes,
 * including the two-digit form (`\xHH`), the braced form (`\x{...}`), and the `\u{...}` syntax.
 */
final readonly class UnicodeNode extends AbstractNode
{
    /**
     * Initializes a Unicode character escape node.
     *
     * Purpose: This constructor creates a node for a character specified by its hex code.
     *
     * @param string $code          The hexadecimal code point of the character. This is the raw value from the
     *                              token, which may include the surrounding braces (e.g., `{2603}`) or just the
     *                              digits (e.g., `41`).
     * @param int    $startPosition The zero-based byte offset where the Unicode escape sequence begins (e.g., at `\x`).
     * @param int    $endPosition   the zero-based byte offset immediately after the sequence
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
     * process this `UnicodeNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitUnicode` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitUnicode($this);
    }
}
