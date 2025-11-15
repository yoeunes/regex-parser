<?php

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
