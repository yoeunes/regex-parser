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
 * Represents a PCRE "verb" which controls the matching engine's behavior.
 *
 * Purpose: PCRE verbs are special directives like `(*FAIL)`, `(*COMMIT)`, `(*THEN)`, or `(*MARK:name)`
 * that are not part of the standard regex syntax but provide powerful control over the backtracking
 * process. For example, `(*FAIL)` forces the current matching path to fail immediately, while `(*COMMIT)`
 * prevents the engine from backtracking past the commit point. This node captures these directives
 * so that they can be correctly handled by compilers or analysis tools.
 */
readonly class PcreVerbNode extends AbstractNode
{
    /**
     * Initializes a PCRE verb node.
     *
     * Purpose: This constructor creates a node for a `(*...)` verb.
     *
     * @param string $verb          The verb and any associated argument (e.g., "FAIL", "COMMIT", "MARK:name").
     *                              The surrounding `(*` and `)` are not included.
     * @param int    $startPosition the zero-based byte offset where the `(*` sequence begins
     * @param int    $endPosition   the zero-based byte offset immediately after the closing `)`
     */
    public function __construct(
        public string $verb,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * Purpose: This method is the entry point for any `NodeVisitorInterface` that needs to
     * process this `PcreVerbNode`. It allows for operations like compilation, validation,
     * or explanation to be performed without adding logic to the node itself. The method
     * simply dispatches the call to the appropriate `visitPcreVerb` method on the visitor.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitPcreVerb($this);
    }
}
