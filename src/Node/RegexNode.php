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
 * Represents the root of a parsed regex, containing the pattern and any flags.
 */
class RegexNode extends AbstractNode
{
    /**
     * @param NodeInterface $pattern       The root node of the regex pattern (e.g., an AlternationNode or SequenceNode).
     * @param string        $flags         A string of flags (e.g., "imsU").
     * @param string        $delimiter     The opening delimiter (e.g., "/", "~", "(").
     * @param int           $startPosition The 0-based start offset
     * @param int           $endPosition   The 0-based end offset (exclusive)
     */
    public function __construct(public readonly NodeInterface $pattern, public readonly string $flags, public readonly string $delimiter, int $startPosition, int $endPosition)
    {
        parent::__construct($startPosition, $endPosition);
    }

    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitRegex($this);
    }
}
