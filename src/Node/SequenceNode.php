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
 * Represents a sequence (concatenation) of nodes.
 * Ex: "abc" is Sequence(Literal(a), Literal(b), Literal(c)).
 */
class SequenceNode extends AbstractNode
{
    /**
     * @param array<NodeInterface> $children the nodes in the sequence
     * @param int                  $startPos The 0-based start offset
     * @param int                  $endPos   The 0-based end offset (exclusive)
     */
    public function __construct(
        public readonly array $children,
        int $startPos,
        int $endPos,
    ) {
        parent::__construct($startPos, $endPos);
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
        return $visitor->visitSequence($this);
    }
}
