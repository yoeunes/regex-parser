<?php

/*
 * This file is part of the RegexParser package.
 *
 * (c) Younes ENNAJI <younes.ennaji.pro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RegexParser\Ast;

use RegexParser\Visitor\VisitorInterface;

class CommentNode implements NodeInterface
{
    public function __construct(public readonly string $comment)
    {
    }

    public function accept(VisitorInterface $visitor)
    {
        return $visitor->visitComment($this);
    }
}
