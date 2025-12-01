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
 * Represents the `\K` "keep" assertion in a regular expression.
 *
 * Purpose: The `\K` assertion is a powerful feature that resets the beginning of the reported match.
 * Any characters matched before the `\K` are "kept" out of the final match result. This is often
 * used as a more efficient alternative to lookbehinds for discarding a prefix. For example, in
 * `foo\Kbar`, the engine matches "foobar", but only "bar" is returned as the result.
 */
readonly class KeepNode extends AbstractNode
{
    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `KeepNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitKeep` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor The visitor object that is traversing the tree.
     *
     * @return T The result of the visitor's processing for this node.
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitKeep($this);
    }
}
