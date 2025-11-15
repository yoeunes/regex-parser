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

/**
 * Base interface for all AST nodes.
 */
interface NodeInterface
{
    /**
     * Accepts a visitor.
     *
     * @template T
     *
     * @param VisitorInterface<T> $visitor
     *
     * @return T
     */
    public function accept(VisitorInterface $visitor);
}
