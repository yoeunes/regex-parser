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

/**
 * An abstract base class for all nodes in the Abstract Syntax Tree (AST).
 *
 * Purpose: This class provides the foundational structure for every node in the AST.
 * It implements the `NodeInterface` and establishes the core properties that all nodes
 * must have: a start and end position. By centralizing this logic, we ensure that every
 * part of the parsed regex can be traced back to its exact location in the original
 * source string, which is critical for error reporting, analysis, and compilation.
 */
abstract readonly class AbstractNode implements NodeInterface
{
    /**
     * Initializes the node with its positional information within the original regex string.
     *
     * Purpose: This constructor is fundamental to the AST's integrity. It captures the precise
     * location of the regex component that the node represents. This positional data is used
     * by visitors for various purposes, such as highlighting syntax in an explanation or
     * pinpointing the source of a validation error.
     *
     * @param int $startPosition the zero-based byte offset where this node's corresponding syntax begins
     *                           in the original pattern string
     * @param int $endPosition   The zero-based byte offset where this node's corresponding syntax ends.
     *                           This position typically points to the character immediately after the node's syntax.
     */
    public function __construct(public int $startPosition, public int $endPosition) {}

    /**
     * Retrieves the starting position of the node in the original regex string.
     *
     * Purpose: This accessor provides a consistent way for visitors and other tools to get the
     * starting byte offset of the syntax this node represents. This is essential for any
     * operation that needs to map the AST back to the source text, such as error reporting
     * or visualization.
     *
     * @return int the zero-based starting byte offset
     */
    public function getStartPosition(): int
    {
        return $this->startPosition;
    }

    /**
     * Retrieves the ending position of the node in the original regex string.
     *
     * Purpose: This accessor provides a consistent way to get the ending byte offset of the
     * syntax this node represents. The end position is typically exclusive, meaning it is the
     * offset of the first character *after* the node's syntax. This allows for easy calculation
     * of the syntax length (`end - start`).
     *
     * @return int the zero-based ending byte offset (exclusive)
     */
    public function getEndPosition(): int
    {
        return $this->endPosition;
    }
}
