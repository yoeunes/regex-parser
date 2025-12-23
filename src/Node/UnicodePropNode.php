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
 * Represents a Unicode property match.
 */
final readonly class UnicodePropNode extends AbstractNode
{
    public bool $hasBraces;

    public function __construct(
        public string $prop,
        int|bool $hasBraces,
        int $startPosition,
        ?int $endPosition = null
    ) {
        // Backward compatibility: allow 3-argument form new UnicodePropNode($prop, $start, $end)
        if (null === $endPosition && \is_int($hasBraces)) {
            $endPosition = $startPosition;
            $startPosition = $hasBraces;
            $hasBraces = str_starts_with($prop, '{') && str_ends_with($prop, '}');
        }

        $this->hasBraces = (bool) $hasBraces;

        parent::__construct($startPosition, $endPosition ?? $startPosition);
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
        return $visitor->visitUnicodeProp($this);
    }
}
