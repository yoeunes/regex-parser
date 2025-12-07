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
 * Represents a script run verb (e.g., (*script_run:Latin), (*sr:Latin)).
 *
 * Purpose: This node represents PCRE2 script run assertions that ensure
 * all characters in the subject string belong to the same script.
 */
final readonly class ScriptRunNode extends AbstractNode
{
    /**
     * Initializes a script run node.
     *
     * @param string $script        The script name (e.g., 'Latin').
     * @param int    $startPosition the start position
     * @param int    $endPosition   the end position
     */
    public function __construct(
        public string $script,
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
        return $visitor->visitScriptRun($this);
    }
}
