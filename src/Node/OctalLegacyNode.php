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
 * Represents a legacy octal escape (e.g., \0, \01, \012).
 */
readonly class OctalLegacyNode extends AbstractNode
{
    /**
     * @param string $code          The octal code (e.g., "0", "01", "012")
     * @param int    $startPosition The 0-based start offset
     * @param int    $endPosition   The 0-based end offset (exclusive)
     */
    public function __construct(
        public string $code,
        int $startPosition,
        int $endPosition
    ) {
        parent::__construct($startPosition, $endPosition);
    }

    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitOctalLegacy($this);
    }
}
