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

use RegexParser\Visitor\VisitorInterface;

class OctalNode implements NodeInterface
{
    public function __construct(public readonly string $code)
    {
    }

    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitOctal($this);
    }
}
