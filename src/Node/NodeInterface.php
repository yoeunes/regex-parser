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
 * Base interface for all AST nodes.
 */
interface NodeInterface
{
    /**
     * Accepts a visitor.
     *
     * Purpose: This method is a core component of the Visitor design pattern, which is central
     * to how the RegexParser library processes its Abstract Syntax Tree (AST). When a `NodeVisitorInterface`
     * traverses the AST, it calls `accept()` on each node. This method then dispatches the call
     * back to the visitor, invoking the specific `visit[NodeName]` method corresponding to the
     * current node's type. This decouples the operations (like compilation, validation, explanation)
     * from the node structure itself, making the AST easily extensible.
     *
     * @template T The return type of the visitor's `visit` methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is currently traversing the AST
     *
     * @return T the result returned by the specific `visit` method on the visitor for this node
     */
    public function accept(NodeVisitorInterface $visitor);

    /**
     * Gets the 0-based start offset of the node in the original pattern string.
     *
     * Purpose: This method provides the starting byte position of the regex syntax represented
     * by this node within the original input string. This positional information is crucial
     * for error reporting (pinpointing exact locations of issues), highlighting, and
     * reconstructing the original pattern.
     *
     * @return int the zero-based starting byte offset
     */
    public function getStartPosition(): int;

    /**
     * Gets the 0-based end offset (exclusive) of the node in the original pattern string.
     *
     * Purpose: This method provides the ending byte position of the regex syntax represented
     * by this node within the original input string. The end position is exclusive, meaning
     * it points to the byte *after* the last character of the node's syntax. This allows
     * for easy calculation of the node's length (`getEndPosition() - getStartPosition()`).
     * It's vital for accurate source mapping and reconstruction.
     *
     * @return int the zero-based ending byte offset (exclusive)
     */
    public function getEndPosition(): int;
}
