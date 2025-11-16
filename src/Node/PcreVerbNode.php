<?php

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
 * Represents a PCRE verb (e.g., "(*FAIL)", "(*COMMIT)").
 */
class PcreVerbNode implements NodeInterface
{
    /**
     * @param string $verb The verb name (e.g., "FAIL", "COMMIT")
     */
    public function __construct(public readonly string $verb)
    {
    }

    /**
     * @template T
     *
     * @param NodeVisitorInterface<T> $visitor
     *
     * @return T
     */
    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitPcreVerb($this);
    }
}
