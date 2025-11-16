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

class UnicodeNode implements NodeInterface
{
    public function __construct(public readonly string $code)
    {
    }

    public function accept(NodeVisitorInterface $visitor)
    {
        return $visitor->visitUnicode($this);
    }
}
