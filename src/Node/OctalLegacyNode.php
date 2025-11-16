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
 * Represents a legacy octal escape (e.g., \0, \01, \012).
 */
class OctalLegacyNode implements NodeInterface
{
    /**
     * @param string $code The octal code (e.g., "0", "01", "012")
     */
    public function __construct(public readonly string $code)
    {
    }

    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitOctalLegacy($this);
    }
}
