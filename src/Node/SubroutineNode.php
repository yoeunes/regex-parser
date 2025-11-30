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
 * Represents a subroutine call (e.g., "(?R)", "(?1)", "(?&name)", "(?P>name)").
 */
class SubroutineNode extends AbstractNode
{
    /**
     * @param string $reference     The group reference (e.g., "R", "0", "1", "name").
     * @param string $syntax        The original syntax (e.g., "&", "P>", "g", "").
     * @param int    $startPosition The 0-based start offset
     * @param int    $endPosition   The 0-based end offset (exclusive)
     */
    public function __construct(public readonly string $reference, public readonly string $syntax, int $startPosition, int $endPosition)
    {
        parent::__construct($startPosition, $endPosition);
    }

    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitSubroutine($this);
    }
}
