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
 * Represents a control character escape sequence (e.g., \cM).
 *
 * Purpose: This node represents control character escapes in PCRE2,
 * where \c followed by a letter produces the corresponding control character.
 */
final readonly class ControlCharNode extends AbstractNode
{
    /**
     * Initializes a control character node.
     *
     * @param string $char          The control character identifier (e.g., 'M' for \cM).
     * @param int    $startPosition the start position
     * @param int    $endPosition   the end position
     */
    public function __construct(
        public string $char,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    /**
     * Implements the visitor pattern for traversing the AST.
     *
     * @template T The return type of the visitor's methods.
     *
     * @param NodeVisitorInterface<T> $visitor the visitor object that is traversing the tree
     *
     * @return T the result of the visitor's processing for this node
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitControlChar($this);
    }
}
